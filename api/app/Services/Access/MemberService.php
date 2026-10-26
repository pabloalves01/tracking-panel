<?php

namespace App\Services\Access;

use App\Models\User;
use App\Support\MembershipContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class MemberService
{
    /**
     * Membros do contexto, do mais antigo ao mais novo.
     */
    public function list(MembershipContext $context): Collection
    {
        return $context->members()
            ->with('user')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Troca o papel. Não deixa o contexto sem admin.
     */
    public function updateRole(MembershipContext $context, string $userUuid, string $role): void
    {
        DB::transaction(function () use ($context, $userUuid, $role) {
            [$member, $admins] = $this->lockMember($context, $userUuid);

            if ($member->role === 'admin' && $role !== 'admin' && $admins === 1) {
                abort(422, $this->lastAdminMessage($context));
            }

            $member->update(['role' => $role]);
        });
    }

    /**
     * Remove o membro, ou a própria pessoa sai. Só o admin remove outra pessoa.
     */
    public function remove(MembershipContext $context, string $userUuid, User $actor): void
    {
        DB::transaction(function () use ($context, $userUuid, $actor) {
            [$member, $admins] = $this->lockMember($context, $userUuid);

            $isSelf = $member->user_id === $actor->id;
            $actorIsAdmin = $context->members()
                ->where('user_id', $actor->id)
                ->where('role', 'admin')
                ->exists();

            if (! $isSelf && ! $actorIsAdmin) {
                abort(403, 'Apenas administradores podem gerenciar usuários.');
            }

            if ($member->role === 'admin' && $admins === 1) {
                abort(422, $this->lastAdminMessage($context));
            }

            $member->delete();
        });
    }

    /**
     * Trava os vínculos do contexto pra conferir o último admin sem corrida.
     *
     * @return array{0: Model, 1: int}
     */
    private function lockMember(MembershipContext $context, string $userUuid): array
    {
        $userId = User::query()->where('uuid', $userUuid)->value('id');
        $members = $context->members()->orderBy('id')->lockForUpdate()->get();
        $member = $userId ? $members->firstWhere('user_id', $userId) : null;

        if (! $member) {
            abort(404, 'Usuário não encontrado');
        }

        return [$member, $members->where('role', 'admin')->count()];
    }

    private function lastAdminMessage(MembershipContext $context): string
    {
        return $context->theLabel().' precisa ter pelo menos um administrador.';
    }
}
