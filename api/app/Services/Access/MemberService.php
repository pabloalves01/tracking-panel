<?php

namespace App\Services\Access;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Os membros de uma organização.
 *
 * Sem troca de papel, sem "sempre resta um owner" e sem o `MembershipScope`
 * que parametrizava empresa e posto: com uma tabela só e nenhum papel, resta
 * suspender, reativar e remover.
 *
 * A remoção é soft delete nos dois casos. Antes era soft na empresa e
 * física no posto, o que fazia a mesma ação significar coisas diferentes.
 */
class MemberService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return Collection<int, Membership> */
    public function list(Organization $organization): Collection
    {
        return Membership::query()
            ->where('organization_id', $organization->getKey())
            ->with('user')
            ->get()
            ->sortBy(fn (Membership $membership) => $membership->user->name)
            ->values();
    }

    public function suspend(Organization $organization, User $actor, string $userUuid): void
    {
        DB::transaction(function () use ($organization, $actor, $userUuid) {
            $membership = $this->find($organization, $userUuid);

            $this->assertNotSelf($membership, $actor);
            $this->assertNotLastMember($organization, $membership);

            $membership->update(['status' => Membership::SUSPENDED]);

            $this->audit->log('membership.suspended', $membership, [], $organization->getKey());
        });
    }

    public function reactivate(Organization $organization, string $userUuid): void
    {
        DB::transaction(function () use ($organization, $userUuid) {
            $membership = $this->find($organization, $userUuid);
            $membership->update(['status' => Membership::ACTIVE]);

            $this->audit->log('membership.reactivated', $membership, [], $organization->getKey());
        });
    }

    public function remove(Organization $organization, User $actor, string $userUuid): void
    {
        DB::transaction(function () use ($organization, $actor, $userUuid) {
            $membership = $this->find($organization, $userUuid);

            $this->assertNotSelf($membership, $actor);
            $this->assertNotLastMember($organization, $membership);

            $this->audit->log('membership.removed', $membership, [
                'user_id' => $membership->user_id,
            ], $organization->getKey());

            $membership->delete();
        });
    }

    // ================================ INTERNALS ================================

    private function find(Organization $organization, string $userUuid): Membership
    {
        $membership = Membership::query()
            ->where('organization_id', $organization->getKey())
            ->whereHas('user', fn ($query) => $query->where('uuid', $userUuid))
            ->with('user')
            ->lockForUpdate()
            ->first();

        if ($membership === null) {
            throw new DomainException('Membro não encontrado nesta organização');
        }

        return $membership;
    }

    private function assertNotSelf(Membership $membership, User $actor): void
    {
        if ($membership->user_id === $actor->getKey()) {
            throw new DomainException('Você não pode alterar o próprio acesso');
        }
    }

    /**
     * Uma organização sem nenhum membro ativo fica inacessível e só o admin
     * do sistema conseguiria resgatá-la.
     */
    private function assertNotLastMember(Organization $organization, Membership $membership): void
    {
        if (! $membership->isActive()) {
            return;
        }

        $actives = Membership::query()
            ->where('organization_id', $organization->getKey())
            ->where('status', Membership::ACTIVE)
            ->lockForUpdate()
            ->count();

        if ($actives <= 1) {
            throw new DomainException(
                'Esta é a última pessoa com acesso. Convide outra antes de remover esta.'
            );
        }
    }
}
