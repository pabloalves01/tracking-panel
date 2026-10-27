<?php

namespace App\Services\Access;

use App\Models\CompanyUser;
use App\Models\FuelStationUser;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Membership;
use App\Support\MembershipScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MemberService
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * @return Collection<int, CompanyUser|FuelStationUser>
     */
    public function list(MembershipScope $scope): Collection
    {
        return $scope->membershipClass::query()
            ->where($scope->foreignKey, $scope->ownerId)
            ->where('status', Membership::ACTIVE)
            ->with('user')
            ->orderBy('id')
            ->get();
    }

    public function updateRole(MembershipScope $scope, User $actor, string $memberUuid, string $role): void
    {
        $membership = $this->findActive($scope, $memberUuid);

        if ($membership->user_id === $actor->id) {
            abort(422, 'Você não pode alterar o próprio papel');
        }

        if (! in_array($role, Membership::roles(), true)) {
            abort(422, 'Papel inválido');
        }

        if (! Membership::manages($scope->actorRole)) {
            abort(403, 'Você não tem permissão para esta ação');
        }

        if ($scope->actorRole === Membership::ADMIN && ($membership->role === Membership::OWNER || $role === Membership::OWNER)) {
            abort(403, 'Só o proprietário altera outro proprietário');
        }

        if ($membership->role === Membership::OWNER && $role !== Membership::OWNER && $this->ownerCount($scope) <= 1) {
            abort(422, 'É preciso manter um proprietário');
        }

        DB::transaction(function () use ($membership, $role, $scope) {
            $from = $membership->role;
            $updated = $membership->newQuery()
                ->whereKey($membership->id)
                ->where('status', Membership::ACTIVE)
                ->where('role', $from)
                ->update([
                    'role' => $role,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                abort(422, 'Não foi possível alterar o papel');
            }

            $this->audit->log('membership.role_changed', $membership, [
                'from' => $from,
                'to' => $role,
                'user_uuid' => $membership->user?->uuid,
            ], $scope->companyId, $scope->fuelStationId);
        });
    }

    public function suspend(MembershipScope $scope, User $actor, string $memberUuid): void
    {
        $membership = $this->findActive($scope, $memberUuid);

        if (! Membership::manages($scope->actorRole)) {
            abort(403, 'Você não tem permissão para esta ação');
        }

        if ($scope->actorRole === Membership::ADMIN && $membership->role === Membership::OWNER) {
            abort(403, 'Só o proprietário remove outro proprietário');
        }

        if ($membership->role === Membership::OWNER && $this->ownerCount($scope) <= 1) {
            abort(422, 'É preciso manter um proprietário');
        }

        if ($membership->user_id === $actor->id) {
            abort(422, 'Você não pode remover a si mesmo');
        }

        DB::transaction(function () use ($membership, $scope, $actor) {
            $updated = $membership->newQuery()
                ->whereKey($membership->id)
                ->where('status', Membership::ACTIVE)
                ->update([
                    'status' => Membership::SUSPENDED,
                    'updated_at' => now(),
                ]);

            if ($updated === 0) {
                abort(422, 'Não foi possível remover esta pessoa');
            }

            $this->audit->log('membership.suspended', $membership, [
                'user_uuid' => $membership->user?->uuid,
                'role' => $membership->role,
                'actor_uuid' => $actor->uuid,
            ], $scope->companyId, $scope->fuelStationId);
        });
    }

    private function findActive(MembershipScope $scope, string $memberUuid): Model
    {
        if (! Str::isUuid($memberUuid)) {
            abort(404, 'Pessoa não encontrada');
        }

        $membership = $scope->membershipClass::query()
            ->where($scope->foreignKey, $scope->ownerId)
            ->where('status', Membership::ACTIVE)
            ->whereHas('user', fn ($query) => $query->where('uuid', $memberUuid))
            ->with('user')
            ->first();

        if (! $membership) {
            abort(404, 'Pessoa não encontrada');
        }

        return $membership;
    }

    private function ownerCount(MembershipScope $scope): int
    {
        return $scope->membershipClass::query()
            ->where($scope->foreignKey, $scope->ownerId)
            ->where('status', Membership::ACTIVE)
            ->where('role', Membership::OWNER)
            ->count();
    }
}
