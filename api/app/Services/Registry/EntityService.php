<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Entity;
use App\Models\Registry\EntityRoleAssignment;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * O cadastro de entidades inteiro. Antes eram oito services quase idênticos,
 * um por papel, e nenhum deles gravava os dados da pessoa na edição.
 */
class EntityService
{
    /**
     * As entidades que exercem um papel, paginadas.
     *
     * @param  array{search?: string|null, status?: string|null, per_page?: int|null}  $filters
     */
    public function list(EntityRole $role, array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = max(1, min($perPage, 100));

        return Entity::query()
            ->withRole($role, $filters['status'] ?? null)
            ->search($filters['search'] ?? null)
            ->with(['roles', 'driverDetails'])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Todas as entidades da empresa, de qualquer papel. É a tela onde se
     * procura alguém sem saber em qual cadastro ele está.
     *
     * @param  array{search?: string|null, role?: string|null, per_page?: int|null}  $filters
     */
    public function listAll(array $filters = []): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 25);
        $perPage = max(1, min($perPage, 100));

        $role = $filters['role'] ?? null;

        return Entity::query()
            ->when($role, fn ($query) => $query->withRole(EntityRole::from($role)))
            ->search($filters['search'] ?? null)
            ->with(['roles', 'driverDetails'])
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function get(string $uuid): Entity
    {
        return Entity::query()
            ->where('uuid', $uuid)
            ->with(['roles', 'driverDetails'])
            ->firstOrFail();
    }

    /**
     * Busca pelo documento, para o formulário avisar que a pessoa já existe
     * antes de o usuário digitar tudo de novo. Devolve null quando não há.
     */
    public function findByDocument(string $document): ?Entity
    {
        return Entity::query()
            ->where('document', $document)
            ->with(['roles', 'driverDetails'])
            ->first();
    }

    /**
     * Cadastra a entidade no papel pedido.
     *
     * Se a pessoa já existe — porque exerce outro papel — os dados enviados
     * atualizam o cadastro dela em vez de serem descartados, e o papel novo é
     * acrescentado. Era aqui que o endereço corrigido se perdia em silêncio.
     */
    public function store(EntityRole $role, array $data): Entity
    {
        return DB::transaction(function () use ($role, $data) {
            $entity = $this->findByDocument($data['document']);

            if ($entity === null) {
                $entity = Entity::create($data);
            } else {
                $this->assertRoleIsNew($entity, $role);
                $entity->update($data);
            }

            $entity->roles()->create([
                'role' => $role,
                'status' => EntityRoleAssignment::ACTIVE,
            ]);

            $this->syncRoleDetails($entity, $role, $data);

            return $entity->fresh(['roles', 'driverDetails']);
        });
    }

    /**
     * Atualiza os dados da pessoa e os dados do papel na mesma operação. Na
     * versão anterior a edição gravava só as colunas do papel, e em cliente,
     * transportadora e funcionário não gravava nada.
     */
    public function update(string $uuid, EntityRole $role, array $data): Entity
    {
        return DB::transaction(function () use ($uuid, $role, $data) {
            $entity = $this->get($uuid);

            $this->assertDocumentIsFree($entity, $data['document']);

            $entity->update($data);
            $this->syncRoleDetails($entity, $role, $data);

            return $entity->fresh(['roles', 'driverDetails']);
        });
    }

    public function setRoleStatus(string $uuid, EntityRole $role, string $status): void
    {
        DB::transaction(function () use ($uuid, $role, $status) {
            $this->roleOf($this->get($uuid), $role)->update(['status' => $status]);
        });
    }

    /**
     * Remove um papel. A entidade continua existindo enquanto exercer algum
     * outro; sem nenhum papel, ela sai do cadastro.
     */
    public function removeRole(string $uuid, EntityRole $role): void
    {
        DB::transaction(function () use ($uuid, $role) {
            $entity = $this->get($uuid);

            $this->roleOf($entity, $role)->delete();

            if ($role === EntityRole::Driver) {
                $entity->driverDetails()->delete();
            }

            if ($entity->roles()->count() === 0) {
                $entity->delete();
            }
        });
    }

    // ================================ INTERNALS ================================

    private function syncRoleDetails(Entity $entity, EntityRole $role, array $data): void
    {
        if ($role !== EntityRole::Driver) {
            return;
        }

        $details = array_intersect_key($data, array_flip([
            'license_number',
            'license_category',
            'license_expires_on',
            'license_issued_state',
            'residence_proof_path',
        ]));

        if ($details === []) {
            return;
        }

        $entity->driverDetails()->updateOrCreate([], $details);
    }

    private function roleOf(Entity $entity, EntityRole $role): EntityRoleAssignment
    {
        $assignment = $entity->roles->firstWhere('role', $role);

        if ($assignment === null) {
            throw new DomainException(
                sprintf('Esta entidade não está cadastrada como %s', mb_strtolower($role->label()))
            );
        }

        return $assignment;
    }

    private function assertRoleIsNew(Entity $entity, EntityRole $role): void
    {
        if (! $entity->hasRole($role)) {
            return;
        }

        throw new DomainException(sprintf(
            '%s já está cadastrado como %s',
            $entity->name,
            mb_strtolower($role->label())
        ));
    }

    private function assertDocumentIsFree(Entity $entity, string $document): void
    {
        $taken = Entity::query()
            ->where('document', $document)
            ->whereKeyNot($entity->getKey())
            ->exists();

        if ($taken) {
            throw new DomainException('Já existe outro cadastro com este documento');
        }
    }
}
