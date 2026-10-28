<?php

namespace App\Services\Access;

use App\Exceptions\NotFound;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\AuditLogger;
use App\Services\Auth\VerificationCodeService;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * O ciclo do convite: criar, reenviar, cancelar, aceitar e recusar.
 *
 * A versão anterior tinha 591 linhas e 31 métodos, metade deles pares
 * gêmeos — `createForCompany`/`createForStation`,
 * `activateCompanyMembership`/`activateStationMembership` — que existiam só
 * porque empresa e posto eram tabelas diferentes. Com `organizations` e
 * `memberships` únicos, cada par virou um método.
 */
class InvitationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly VerificationCodeService $codes,
    ) {}

    /** @return Collection<int, Invitation> */
    public function listFor(Organization $organization): Collection
    {
        return Invitation::query()
            ->where('organization_id', $organization->getKey())
            ->with('invitedBy')
            ->latest('id')
            ->get()
            ->each(fn (Invitation $invitation) => $this->expireIfNeeded($invitation));
    }

    public function create(Organization $organization, string $email, ?User $invitedBy): Invitation
    {
        $email = mb_strtolower(trim($email));

        return DB::transaction(function () use ($organization, $email, $invitedBy) {
            // Trava a organização para que dois convites simultâneos ao
            // mesmo e-mail não passem os dois pela verificação.
            Organization::query()->whereKey($organization->getKey())->lockForUpdate()->first();

            $this->assertNotMember($organization, $email);
            $this->assertNoPendingInvitation($organization, $email);

            $plainToken = Str::random(64);

            $invitation = Invitation::query()->create([
                'organization_id' => $organization->getKey(),
                'email' => $email,
                'token_hash' => hash('sha256', $plainToken),
                'status' => Invitation::PENDING,
                'expires_at' => now()->addDays(Invitation::DAYS),
                'invited_by' => $invitedBy?->getKey(),
            ]);

            $invitation->plainToken = $plainToken;
            $invitation->accountExists = $this->accountExists($email);

            $this->audit->log('invitation.created', $invitation, ['email' => $email], $organization->getKey());
            $this->sendMail($invitation);

            return $invitation;
        });
    }

    /** Gera um token novo e invalida o anterior. */
    public function resend(Invitation $invitation): Invitation
    {
        return DB::transaction(function () use ($invitation) {
            $invitation->refresh();

            if (! $invitation->isPending()) {
                throw new DomainException('Este convite não está mais pendente');
            }

            $plainToken = Str::random(64);

            $invitation->update([
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addDays(Invitation::DAYS),
            ]);

            $invitation->plainToken = $plainToken;
            $invitation->accountExists = $this->accountExists($invitation->email);

            $this->audit->log('invitation.resent', $invitation, [], $invitation->organization_id);
            $this->sendMail($invitation);

            return $invitation;
        });
    }

    public function revoke(Invitation $invitation): void
    {
        DB::transaction(function () use ($invitation) {
            $invitation->refresh();

            if (! $invitation->isPending()) {
                throw new DomainException('Este convite não está mais pendente');
            }

            $invitation->update([
                'status' => Invitation::REVOKED,
                'responded_at' => now(),
            ]);

            $this->audit->log('invitation.revoked', $invitation, [], $invitation->organization_id);
        });
    }

    /**
     * O que a tela pública do convite mostra antes de qualquer ação.
     *
     * @return array<string, mixed>
     */
    public function preview(string $plainToken): array
    {
        $invitation = $this->findByToken($plainToken);
        $this->expireIfNeeded($invitation);

        return [
            'organization' => $invitation->organization->displayName(),
            'organization_type' => $invitation->organization->type->value,
            'invited_by' => $invitation->invitedBy?->name,
            'email' => $invitation->email,
            'status' => $invitation->status,
            'expires_at' => $invitation->expires_at,
            'account_exists' => $this->accountExists($invitation->email),
        ];
    }

    /**
     * Cria a conta e entra na organização.
     *
     * A conferência do código acontece antes da transação, porque o palpite
     * errado precisa ficar contado mesmo quando o registro falha logo a
     * seguir — dentro dela, o rollback desfazia a contagem. Marcar o código
     * como gasto continua lá dentro: se a criação falhar, ele vale ainda.
     *
     * O convite é lido duas vezes de propósito: a primeira só para saber
     * para qual e-mail o código foi emitido, e a segunda com `lockPending`,
     * que é a que vale.
     */
    public function registerAndAccept(string $plainToken, string $name, string $password, mixed $code): User
    {
        $email = $this->findByToken($plainToken)->email;

        if ($this->accountExists($email)) {
            throw new DomainException('Este e-mail já tem conta. Entre para aceitar o convite');
        }

        $verified = $this->codes->verify($email, VerificationCode::INVITATION_REGISTER, $code);

        return DB::transaction(function () use ($plainToken, $name, $password, $verified) {
            $invitation = $this->lockPending($plainToken);

            if ($this->accountExists($invitation->email)) {
                throw new DomainException('Este e-mail já tem conta. Entre para aceitar o convite');
            }

            $this->codes->markUsed($verified);

            $user = User::query()->create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $this->acceptLocked($invitation, $user);

            return $user;
        });
    }

    public function accept(string $plainToken, User $user): Invitation
    {
        return DB::transaction(function () use ($plainToken, $user) {
            $invitation = $this->lockPending($plainToken);
            $this->assertSameEmail($invitation, $user);
            $this->acceptLocked($invitation, $user);

            return $invitation;
        });
    }

    /** Quem tem o link pode recusar sem precisar entrar. */
    public function decline(string $plainToken, ?User $user = null): void
    {
        DB::transaction(function () use ($plainToken, $user) {
            $invitation = $this->lockPending($plainToken);

            if ($user) {
                $this->assertSameEmail($invitation, $user);
            }

            $invitation->update([
                'status' => Invitation::DECLINED,
                'responded_at' => now(),
            ]);

            $this->audit->log('invitation.declined', $invitation, [], $invitation->organization_id);
        });
    }

    /**
     * Os convites pendentes do usuário logado, que aparecem no seletor de
     * organização.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingFor(User $user): array
    {
        return Invitation::query()
            ->where('email', $user->email)
            ->where('status', Invitation::PENDING)
            ->where('expires_at', '>', now())
            ->with(['organization', 'invitedBy'])
            ->get()
            ->map(fn (Invitation $invitation) => [
                'uuid' => $invitation->uuid,
                'organization' => $invitation->organization->displayName(),
                'organization_type' => $invitation->organization->type->value,
                'invited_by' => $invitation->invitedBy?->name,
                'expires_at' => $invitation->expires_at,
            ])
            ->all();
    }

    public function acceptForUser(User $user, string $uuid): Invitation
    {
        return DB::transaction(function () use ($user, $uuid) {
            $invitation = $this->lockPendingByUuid($user, $uuid);
            $this->acceptLocked($invitation, $user);

            return $invitation;
        });
    }

    public function declineForUser(User $user, string $uuid): void
    {
        DB::transaction(function () use ($user, $uuid) {
            $invitation = $this->lockPendingByUuid($user, $uuid);

            $invitation->update([
                'status' => Invitation::DECLINED,
                'responded_at' => now(),
            ]);

            $this->audit->log('invitation.declined', $invitation, [], $invitation->organization_id);
        });
    }

    public function findFor(Organization $organization, string $uuid): Invitation
    {
        return Invitation::query()
            ->where('organization_id', $organization->getKey())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    // ================================ INTERNALS ================================

    /**
     * Cria ou restaura o vínculo. Um membro suspenso não é reativado por
     * aceitar um convite: quem suspendeu precisa reativar.
     */
    private function acceptLocked(Invitation $invitation, User $user): void
    {
        $membership = Membership::withTrashed()
            ->where('organization_id', $invitation->organization_id)
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        if ($membership === null) {
            Membership::query()->create([
                'organization_id' => $invitation->organization_id,
                'user_id' => $user->getKey(),
                'status' => Membership::ACTIVE,
            ]);
        } elseif ($membership->trashed()) {
            $membership->restore();
            $membership->update(['status' => Membership::ACTIVE]);
        }

        $invitation->update([
            'status' => Invitation::ACCEPTED,
            'responded_at' => now(),
        ]);

        $this->audit->log('invitation.accepted', $invitation, [
            'user_id' => $user->getKey(),
        ], $invitation->organization_id);
    }

    private function sendMail(Invitation $invitation): void
    {
        $url = $invitation->inviteUrl();

        if (! $url) {
            return;
        }

        $invitation->loadMissing(['organization', 'invitedBy']);

        Mail::to($invitation->email)->queue(new InvitationMail(
            organization: $invitation->organization->displayName(),
            url: $url,
            expiresAt: $invitation->expires_at?->timezone(config('app.timezone'))->format('d/m/Y \à\s H:i') ?? '',
            invitedBy: $invitation->invitedBy?->name,
            accountExists: (bool) $invitation->accountExists,
            organizationKind: $invitation->organization->type->label(),
        ));
    }

    private function findByToken(string $plainToken): Invitation
    {
        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->with(['organization', 'invitedBy'])
            ->first();

        if ($invitation === null) {
            throw new NotFound('Convite não encontrado ou já utilizado');
        }

        return $invitation;
    }

    private function lockPending(string $plainToken): Invitation
    {
        $invitation = $this->findByToken($plainToken);

        $locked = Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first();
        $this->expireIfNeeded($locked);

        if (! $locked->isPending()) {
            throw new DomainException($this->closedMessage($locked));
        }

        return $locked->load(['organization', 'invitedBy']);
    }

    private function lockPendingByUuid(User $user, string $uuid): Invitation
    {
        $invitation = Invitation::query()
            ->where('uuid', $uuid)
            ->where('email', $user->email)
            ->lockForUpdate()
            ->first();

        if ($invitation === null) {
            throw new NotFound('Convite não encontrado');
        }

        $this->expireIfNeeded($invitation);

        if (! $invitation->isPending()) {
            throw new DomainException($this->closedMessage($invitation));
        }

        return $invitation;
    }

    private function expireIfNeeded(Invitation $invitation): void
    {
        if ($invitation->isPending() && $invitation->hasExpired()) {
            $invitation->update(['status' => Invitation::EXPIRED]);
        }
    }

    private function closedMessage(Invitation $invitation): string
    {
        return match ($invitation->status) {
            Invitation::ACCEPTED => 'Este convite já foi aceito',
            Invitation::DECLINED => 'Este convite foi recusado',
            Invitation::REVOKED => 'Este convite foi cancelado',
            Invitation::EXPIRED => 'Este convite expirou. Peça um novo',
            default => 'Este convite não está mais disponível',
        };
    }

    private function assertSameEmail(Invitation $invitation, User $user): void
    {
        if ($invitation->email !== mb_strtolower($user->email)) {
            throw new DomainException('Este convite foi enviado para outro e-mail');
        }
    }

    private function assertNotMember(Organization $organization, string $email): void
    {
        $isMember = Membership::query()
            ->where('organization_id', $organization->getKey())
            ->whereHas('user', fn ($query) => $query->where('email', $email))
            ->exists();

        if ($isMember) {
            throw new DomainException('Esta pessoa já faz parte desta organização');
        }
    }

    private function assertNoPendingInvitation(Organization $organization, string $email): void
    {
        $pending = Invitation::query()
            ->where('organization_id', $organization->getKey())
            ->where('email', $email)
            ->where('status', Invitation::PENDING)
            ->exists();

        if ($pending) {
            throw new DomainException('Já existe um convite pendente para este e-mail');
        }
    }

    private function accountExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }
}
