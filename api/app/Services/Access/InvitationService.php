<?php

namespace App\Services\Access;

use App\Mail\InvitationMail;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Invitation;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\AuditLogger;
use App\Services\Auth\VerificationCodeService;
use App\Support\Membership;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvitationService
{
    public function __construct(private AuditLogger $audit, private VerificationCodeService $codes) {}

    /**
     * @return Collection<int, Invitation>
     */
    public function listForCompany(Company $company): Collection
    {
        return $this->openQuery()->where('company_id', $company->id)->latest()->get();
    }

    /**
     * @return Collection<int, Invitation>
     */
    public function listForStation(FuelStation $station): Collection
    {
        return $this->openQuery()->where('fuel_station_id', $station->id)->latest()->get();
    }

    public function createForCompany(Company $company, string $email, string $role, ?User $invitedBy, bool $allowOwner = false): Invitation
    {
        $this->assertRole($role, $allowOwner);

        return $this->create($email, $role, $invitedBy, $company, null);
    }

    public function createForStation(FuelStation $station, string $email, string $role, ?User $invitedBy): Invitation
    {
        $this->assertRole($role, false);

        return $this->create($email, $role, $invitedBy, null, $station);
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return array<int, array<string, mixed>>
     */
    public function createManyForCompany(Company $company, array $emails, string $role, ?User $invitedBy, bool $allowOwner = false): array
    {
        return $this->createMany($emails, $role, $invitedBy, $company, null, $allowOwner);
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return array<int, array<string, mixed>>
     */
    public function createManyForStation(FuelStation $station, array $emails, string $role, ?User $invitedBy): array
    {
        return $this->createMany($emails, $role, $invitedBy, null, $station, false);
    }

    public function resend(Invitation $invitation): Invitation
    {
        if ($invitation->status === Invitation::REVOKED) {
            abort(422, 'Este convite foi cancelado');
        }

        if ($invitation->status === Invitation::ACCEPTED) {
            abort(422, 'Este convite já foi aceito');
        }

        $plain = Str::random(48);

        try {
            DB::transaction(function () use ($invitation, $plain) {
                $invitation->forceFill([
                    'token_hash' => hash('sha256', $plain),
                    'status' => Invitation::PENDING,
                    'expires_at' => now()->addDays(Invitation::DAYS),
                    'responded_at' => null,
                ])->save();

                $this->audit->log('invitation.resent', $invitation, [
                    'email' => $invitation->email,
                    'role' => $invitation->role,
                ], $invitation->company_id, $invitation->fuel_station_id);
            });
        } catch (UniqueConstraintViolationException) {
            abort(422, 'Já existe um convite pendente para este e-mail');
        }

        $invitation->plainToken = $plain;
        $this->sendInvitationMail($invitation);

        return $invitation;
    }

    public function revoke(Invitation $invitation): void
    {
        if ($invitation->status !== Invitation::PENDING) {
            abort(422, 'Só é possível cancelar um convite pendente');
        }

        DB::transaction(function () use ($invitation) {
            $updated = Invitation::query()
                ->whereKey($invitation->id)
                ->where('status', Invitation::PENDING)
                ->update([
                    'status' => Invitation::REVOKED,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                abort(422, 'Só é possível cancelar um convite pendente');
            }

            $this->audit->log('invitation.revoked', $invitation, [
                'email' => $invitation->email,
            ], $invitation->company_id, $invitation->fuel_station_id);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(string $plainToken): array
    {
        $invitation = $this->findByToken($plainToken);
        $this->expireIfNeeded($invitation);
        $invitation->refresh();

        $company = $invitation->company;
        $station = $invitation->fuelStation;

        return [
            'email' => $invitation->email,
            'role' => $invitation->role,
            'status' => $invitation->status,
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'account_exists' => User::query()->where('email', $invitation->email)->exists(),
            'organization' => $company ? [
                'type' => 'company',
                'name' => $company->trade_name ?: $company->legal_name,
            ] : [
                'type' => 'fuel_station',
                'name' => $station?->trade_name ?: $station?->corporate_name,
            ],
        ];
    }

    public function registerAndAccept(string $plainToken, string $name, string $password, mixed $code): User
    {
        $invitation = $this->lockPending($plainToken);

        if (User::query()->where('email', $invitation->email)->exists()) {
            abort(422, 'Este e-mail já tem conta. Entre para aceitar o convite');
        }

        $this->codes->consume($invitation->email, VerificationCode::INVITATION_REGISTER, $code);

        return DB::transaction(function () use ($invitation, $name, $password) {
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

    public function accept(string $plainToken, User $user): void
    {
        $invitation = $this->lockPending($plainToken);
        $this->assertSameEmail($invitation, $user);
        $this->acceptLocked($invitation, $user);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function pendingFor(User $user): array
    {
        return Invitation::query()
            ->where('email', Text::email($user->email))
            ->where('status', Invitation::PENDING)
            ->where('expires_at', '>', now())
            ->with(['company', 'fuelStation'])
            ->latest('id')
            ->get()
            ->map(fn (Invitation $invitation) => [
                'uuid' => $invitation->uuid,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'organization' => $invitation->company_id ? [
                    'type' => 'company',
                    'name' => $invitation->company?->trade_name ?: $invitation->company?->legal_name,
                ] : [
                    'type' => 'fuel_station',
                    'name' => $invitation->fuelStation?->trade_name ?: $invitation->fuelStation?->corporate_name,
                ],
            ])
            ->all();
    }

    public function acceptForUser(User $user, string $uuid): Invitation
    {
        $invitation = $this->invitationForUser($user, $uuid);
        $this->acceptLocked($invitation, $user);

        return $invitation->fresh();
    }

    public function declineForUser(User $user, string $uuid): void
    {
        $invitation = $this->invitationForUser($user, $uuid);

        DB::transaction(function () use ($invitation) {
            $updated = Invitation::query()
                ->whereKey($invitation->id)
                ->where('status', Invitation::PENDING)
                ->update([
                    'status' => Invitation::DECLINED,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                abort(422, 'Este convite não está pendente');
            }

            $this->audit->log('invitation.declined', $invitation, [
                'email' => $invitation->email,
            ], $invitation->company_id, $invitation->fuel_station_id);
        });
    }

    public function decline(string $plainToken, ?User $user = null): void
    {
        $invitation = $this->findByToken($plainToken);
        $this->expireIfNeeded($invitation);

        if ($user) {
            $this->assertSameEmail($invitation, $user);
        }

        if ($invitation->status !== Invitation::PENDING) {
            abort(422, $this->closedMessage($invitation));
        }

        DB::transaction(function () use ($invitation) {
            $updated = Invitation::query()
                ->whereKey($invitation->id)
                ->where('status', Invitation::PENDING)
                ->update([
                    'status' => Invitation::DECLINED,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                abort(422, 'Este convite não está pendente');
            }

            $this->audit->log('invitation.declined', $invitation, [
                'email' => $invitation->email,
            ], $invitation->company_id, $invitation->fuel_station_id);
        });
    }

    public function findForCompany(Company $company, string $uuid): Invitation
    {
        return $this->findScoped($uuid, 'company_id', $company->id);
    }

    public function findForStation(FuelStation $station, string $uuid): Invitation
    {
        return $this->findScoped($uuid, 'fuel_station_id', $station->id);
    }

    private function create(string $email, string $role, ?User $invitedBy, ?Company $company, ?FuelStation $station): Invitation
    {
        $email = strtolower(trim($email));
        $this->assertNotMember($email, $company, $station);

        $plain = Str::random(48);

        try {
            $invitation = DB::transaction(function () use ($email, $role, $invitedBy, $company, $station, $plain) {
                if ($company) {
                    Company::query()->whereKey($company->id)->lockForUpdate()->first();
                } else {
                    FuelStation::query()->whereKey($station->id)->lockForUpdate()->first();
                }

                if ($this->pendingExists($email, $company, $station)) {
                    abort(422, 'Já existe um convite pendente para este e-mail');
                }

                $invitation = Invitation::query()->create([
                    'company_id' => $company?->id,
                    'fuel_station_id' => $station?->id,
                    'email' => $email,
                    'role' => $role,
                    'status' => Invitation::PENDING,
                    'token_hash' => hash('sha256', $plain),
                    'expires_at' => now()->addDays(Invitation::DAYS),
                    'invited_by' => $invitedBy?->id,
                ]);

                $this->audit->log('invitation.created', $invitation, [
                    'email' => $email,
                    'role' => $role,
                ], $company?->id, $station?->id);

                return $invitation;
            });
        } catch (UniqueConstraintViolationException) {
            abort(422, 'Já existe um convite pendente para este e-mail');
        }

        $invitation->plainToken = $plain;
        $this->sendInvitationMail($invitation);

        return $invitation;
    }

    /**
     * @param  array<int, mixed>  $emails
     * @return array<int, array<string, mixed>>
     */
    private function createMany(array $emails, string $role, ?User $invitedBy, ?Company $company, ?FuelStation $station, bool $allowOwner): array
    {
        $results = [];

        foreach ($emails as $raw) {
            $email = Text::email($raw);

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $results[] = [
                    'email' => is_scalar($raw) ? Text::plain($raw) : '',
                    'ok' => false,
                    'account_exists' => false,
                    'message' => 'Informe um e-mail válido',
                ];

                continue;
            }

            try {
                $invitation = $company
                    ? $this->createForCompany($company, $email, $role, $invitedBy, $allowOwner)
                    : $this->createForStation($station, $email, $role, $invitedBy);
                $accountExists = User::query()->where('email', $email)->exists();
                $results[] = [
                    'email' => $email,
                    'ok' => true,
                    'account_exists' => $accountExists,
                    'message' => $accountExists ? 'Já tem conta, só vai aceitar' : 'Convite enviado',
                    'invite_url' => $invitation->inviteUrl(),
                    'uuid' => $invitation->uuid,
                ];
            } catch (HttpException $exception) {
                $results[] = [
                    'email' => $email,
                    'ok' => false,
                    'account_exists' => User::query()->where('email', $email)->exists(),
                    'message' => $exception->getMessage() ?: 'Não foi possível convidar',
                ];
            }
        }

        return $results;
    }

    private function sendInvitationMail(Invitation $invitation): void
    {
        $url = $invitation->inviteUrl();

        if (! $url) {
            return;
        }

        $invitation->loadMissing(['company', 'fuelStation']);
        $organization = $invitation->company
            ? ($invitation->company->trade_name ?: $invitation->company->legal_name)
            : ($invitation->fuelStation?->trade_name ?: $invitation->fuelStation?->corporate_name);

        Mail::to($invitation->email)->queue(new InvitationMail(
            (string) $organization,
            $url,
            $invitation->expires_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? '',
        ));
    }

    private function invitationForUser(User $user, string $uuid): Invitation
    {
        if (! Str::isUuid($uuid)) {
            abort(404, 'Convite não encontrado');
        }

        $invitation = Invitation::query()
            ->where('uuid', $uuid)
            ->where('email', Text::email($user->email))
            ->first();

        if (! $invitation) {
            abort(404, 'Convite não encontrado');
        }

        $this->expireIfNeeded($invitation);
        $invitation->refresh();

        if ($invitation->status !== Invitation::PENDING) {
            abort(422, $this->closedMessage($invitation));
        }

        return $invitation;
    }

    private function acceptLocked(Invitation $invitation, User $user): void
    {
        DB::transaction(function () use ($invitation, $user) {
            Invitation::query()->whereKey($invitation->id)->lockForUpdate()->first();

            $updated = Invitation::query()
                ->whereKey($invitation->id)
                ->where('status', Invitation::PENDING)
                ->where('expires_at', '>', now())
                ->update([
                    'status' => Invitation::ACCEPTED,
                    'responded_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                abort(422, 'Este convite não está pendente');
            }

            if ($invitation->company_id) {
                $this->activateCompanyMembership($invitation, $user);
            } else {
                $this->activateStationMembership($invitation, $user);
            }

            $this->audit->log('invitation.accepted', $invitation, [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'user_uuid' => $user->uuid,
            ], $invitation->company_id, $invitation->fuel_station_id);
        });
    }

    private function activateCompanyMembership(Invitation $invitation, User $user): void
    {
        $membership = CompanyUser::withTrashed()->firstOrNew([
            'company_id' => $invitation->company_id,
            'user_id' => $user->id,
        ]);
        $membership->role = $invitation->role;
        $membership->status = Membership::ACTIVE;
        $membership->deleted_at = null;
        $membership->save();

        $this->audit->log('membership.activated', $membership, [
            'role' => $invitation->role,
            'user_uuid' => $user->uuid,
        ], $invitation->company_id, null);
    }

    private function activateStationMembership(Invitation $invitation, User $user): void
    {
        $membership = FuelStationUser::query()->firstOrNew([
            'fuel_station_id' => $invitation->fuel_station_id,
            'user_id' => $user->id,
        ]);
        $membership->role = $invitation->role;
        $membership->status = Membership::ACTIVE;
        $membership->save();

        $this->audit->log('membership.activated', $membership, [
            'role' => $invitation->role,
            'user_uuid' => $user->uuid,
        ], null, $invitation->fuel_station_id);
    }

    private function lockPending(string $plainToken): Invitation
    {
        $invitation = $this->findByToken($plainToken);
        $this->expireIfNeeded($invitation);
        $invitation->refresh();

        if ($invitation->status !== Invitation::PENDING) {
            abort(422, $this->closedMessage($invitation));
        }

        return $invitation;
    }

    private function findByToken(string $plainToken): Invitation
    {
        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if (! $invitation) {
            abort(404, 'Convite não encontrado');
        }

        return $invitation;
    }

    private function expireIfNeeded(Invitation $invitation): void
    {
        if ($invitation->status !== Invitation::PENDING || $invitation->expires_at->isFuture()) {
            return;
        }

        Invitation::query()
            ->whereKey($invitation->id)
            ->where('status', Invitation::PENDING)
            ->update([
                'status' => Invitation::EXPIRED,
                'updated_at' => now(),
            ]);
    }

    private function assertSameEmail(Invitation $invitation, User $user): void
    {
        if (strtolower($user->email) !== strtolower($invitation->email)) {
            abort(422, 'Este convite é para outro e-mail');
        }
    }

    private function closedMessage(Invitation $invitation): string
    {
        return match ($invitation->status) {
            Invitation::ACCEPTED => 'Este convite já foi aceito',
            Invitation::DECLINED => 'Este convite foi recusado',
            Invitation::REVOKED => 'Este convite foi cancelado',
            Invitation::EXPIRED => 'Este convite expirou',
            default => 'Este convite não está pendente',
        };
    }

    private function assertRole(string $role, bool $allowOwner): void
    {
        $allowed = $allowOwner
            ? [Membership::OWNER, Membership::ADMIN]
            : [Membership::ADMIN, Membership::MEMBER];

        if (! in_array($role, $allowed, true)) {
            abort(422, 'Papel inválido para este convite');
        }
    }

    private function assertNotMember(string $email, ?Company $company, ?FuelStation $station): void
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return;
        }

        if ($company && $user->companies()->whereKey($company->id)->exists()) {
            abort(422, 'Esta pessoa já faz parte');
        }

        if ($station && $user->fuelStations()->whereKey($station->id)->exists()) {
            abort(422, 'Esta pessoa já faz parte');
        }
    }

    private function pendingExists(string $email, ?Company $company, ?FuelStation $station): bool
    {
        return Invitation::query()
            ->where('email', $email)
            ->where('status', Invitation::PENDING)
            ->when($company, fn (Builder $query) => $query->where('company_id', $company->id))
            ->when($station, fn (Builder $query) => $query->where('fuel_station_id', $station->id))
            ->lockForUpdate()
            ->exists();
    }

    private function findScoped(string $uuid, string $column, int $id): Invitation
    {
        if (! Str::isUuid($uuid)) {
            abort(404, 'Convite não encontrado');
        }

        $invitation = Invitation::query()
            ->where('uuid', $uuid)
            ->where($column, $id)
            ->first();

        if (! $invitation) {
            abort(404, 'Convite não encontrado');
        }

        return $invitation;
    }

    private function openQuery(): Builder
    {
        return Invitation::query()->where('status', '!=', Invitation::ACCEPTED);
    }
}
