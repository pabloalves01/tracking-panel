<?php

namespace App\Services\Access;

use App\Models\Invitation;
use App\Models\User;
use App\Support\MembershipContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public const INVALID = 'Este link de convite não é válido. Peça um novo ao administrador.';

    // ===================== ADMIN DA EMPRESA, DO POSTO OU DA LOGIV =====================

    /**
     * Convites pendentes, inclusive os expirados.
     */
    public function list(MembershipContext $context): Collection
    {
        return Invitation::query()
            ->where($context->column(), $context->id)
            ->where('status', 'pending')
            ->with('inviter')
            ->latest()
            ->get();
    }

    /**
     * Cria o convite e devolve o token, que só aparece agora.
     *
     * @return array{0: Invitation, 1: string}
     */
    public function create(MembershipContext $context, string $email, string $role, ?User $inviter): array
    {
        return DB::transaction(function () use ($context, $email, $role, $inviter) {
            // Trava a empresa ou o posto: dois convites para o mesmo e-mail ao mesmo tempo
            // esperam um pelo outro, e o segundo já encontra o pendente.
            DB::table($context->isCompany() ? 'companies' : 'fuel_stations')
                ->where('id', $context->id)
                ->lockForUpdate()
                ->first();

            $userId = User::query()->where('email', $email)->value('id');

            if ($userId && $context->members()->where('user_id', $userId)->exists()) {
                throw ValidationException::withMessages([
                    'email' => 'Este e-mail já faz parte '.$context->ofLabel().'.',
                ]);
            }

            $pending = Invitation::query()
                ->where($context->column(), $context->id)
                ->where('email', $email)
                ->where('status', 'pending')
                ->exists();

            if ($pending) {
                throw ValidationException::withMessages([
                    'email' => 'Já existe um convite pendente para este e-mail. Use “Copiar novo link” na lista.',
                ]);
            }

            $token = Str::random(48);

            $invitation = Invitation::query()->create([
                $context->column() => $context->id,
                'email' => $email,
                'role' => $role,
                'token_hash' => hash('sha256', $token),
                'status' => 'pending',
                'invited_by' => $inviter?->id,
                'expires_at' => now()->addDays(Invitation::VALID_DAYS),
            ]);

            return [$invitation, $token];
        });
    }

    /**
     * Novo token e novo prazo. O link anterior deixa de funcionar.
     *
     * @return array{0: Invitation, 1: string}
     */
    public function renew(MembershipContext $context, string $uuid): array
    {
        $invitation = $this->find($context, $uuid);
        $token = Str::random(48);

        $updated = Invitation::query()
            ->whereKey($invitation->id)
            ->where('status', 'pending')
            ->update([
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(Invitation::VALID_DAYS),
            ]);

        if ($updated === 0) {
            abort(422, 'Este convite não está mais pendente');
        }

        return [$invitation->fresh('inviter'), $token];
    }

    public function cancel(MembershipContext $context, string $uuid): void
    {
        $invitation = $this->find($context, $uuid);

        $updated = Invitation::query()
            ->whereKey($invitation->id)
            ->where('status', 'pending')
            ->update(['status' => 'canceled']);

        if ($updated === 0) {
            abort(422, 'Este convite não está mais pendente');
        }
    }

    // ===================== QUEM RECEBEU O LINK =====================

    /**
     * Convite pendente de uma empresa ou posto ativo. Cancelado, aceito,
     * inexistente ou de contexto inativo dão a mesma resposta.
     */
    public function findByToken(string $token): Invitation
    {
        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->with(['company', 'fuelStation'])
            ->first();

        $place = $invitation?->company ?? $invitation?->fuelStation;

        if (! $invitation
            || $invitation->status !== 'pending'
            || ! $place
            || $place->trashed()
            || $place->status !== 'active') {
            abort(404, self::INVALID);
        }

        return $invitation;
    }

    public function contextOf(Invitation $invitation): MembershipContext
    {
        return $invitation->company_id
            ? MembershipContext::company($invitation->company_id)
            : MembershipContext::station($invitation->fuel_station_id);
    }

    public function placeName(Invitation $invitation): string
    {
        return $invitation->company_id
            ? ($invitation->company->trade_name ?: $invitation->company->legal_name)
            : ($invitation->fuelStation->trade_name ?: $invitation->fuelStation->corporate_name);
    }

    /**
     * Cria a conta com o e-mail do convite e já aceita.
     */
    public function register(string $token, string $name, string $password): array
    {
        $invitation = $this->findByToken($token);
        $this->ensureNotExpired($invitation);
        $context = $this->contextOf($invitation);
        $accountExists = 'Este e-mail já tem conta. Entre com ela para aceitar o convite.';

        try {
            return DB::transaction(function () use ($invitation, $context, $name, $password, $accountExists) {
                if (User::query()->where('email', $invitation->email)->exists()) {
                    throw ValidationException::withMessages(['email' => $accountExists]);
                }

                $user = User::query()->create([
                    'name' => $name,
                    'email' => $invitation->email,
                    'password' => $password,
                ]);

                $this->markAccepted($invitation);

                $context->members()->create([
                    $context->column() => $context->id,
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                ]);

                return [$user, $context];
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => $accountExists]);
        }
    }

    /**
     * Aceite pela conta logada com o mesmo e-mail. Se já for membro, só marca o convite.
     *
     * @return array{0: MembershipContext, 1: bool}
     */
    public function accept(string $token, User $user): array
    {
        $invitation = $this->findByToken($token);
        $this->ensureNotExpired($invitation);

        if ($invitation->email !== $user->email) {
            abort(403, "Este convite é para {$invitation->email}, mas você entrou como {$user->email}.");
        }

        $context = $this->contextOf($invitation);

        try {
            $alreadyMember = DB::transaction(function () use ($invitation, $context, $user) {
                $this->markAccepted($invitation);

                if ($context->members()->where('user_id', $user->id)->exists()) {
                    return true;
                }

                $context->members()->create([
                    $context->column() => $context->id,
                    'user_id' => $user->id,
                    'role' => $invitation->role,
                ]);

                return false;
            });
        } catch (UniqueConstraintViolationException) {
            // Entrou por outro caminho ao mesmo tempo: já é membro.
            $this->markAccepted($invitation);
            $alreadyMember = true;
        }

        return [$context, $alreadyMember];
    }

    private function ensureNotExpired(Invitation $invitation): void
    {
        if ($invitation->isExpired()) {
            $of = $invitation->company_id ? 'da' : 'do';

            abort(422, "Este convite expirou. Peça um novo link ao administrador {$of} {$this->placeName($invitation)}.");
        }
    }

    /**
     * Update condicional: o convite só é aceito uma vez.
     */
    private function markAccepted(Invitation $invitation): void
    {
        $updated = Invitation::query()
            ->whereKey($invitation->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->update([
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

        if ($updated === 0) {
            abort(422, self::INVALID);
        }
    }

    private function find(MembershipContext $context, string $uuid): Invitation
    {
        $invitation = Invitation::query()
            ->where($context->column(), $context->id)
            ->where('uuid', $uuid)
            ->first();

        if (! $invitation) {
            abort(404, 'Convite não encontrado');
        }

        return $invitation;
    }
}
