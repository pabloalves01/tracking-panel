<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Entity;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * A pessoa por trás dos papéis: procurar pelo documento, criar ou restaurar,
 * atualizar e excluir. Não sabe nada de papel em particular — isso é do
 * service de cada papel (`DriverService`, `CustomerService`…).
 */
class EntityService
{
    /**
     * Todas as entidades da empresa, de qualquer papel. É a tela onde se
     * procura alguém sem saber em qual cadastro ele está.
     *
     * @param  array{search?: string|null, role?: string|null, per_page?: int|null}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $role = isset($filters['role']) ? EntityRole::from($filters['role']) : null;

        return Entity::query()
            ->when($role, fn ($query) => $query->withRole($role))
            ->search($filters['search'] ?? null)
            ->withAllRoles()
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function get(string $uuid): Entity
    {
        return Entity::query()->where('uuid', $uuid)->withAllRoles()->firstOrFail();
    }

    /**
     * Busca pelo documento, para o formulário avisar que a pessoa já existe
     * antes de o usuário digitar tudo de novo. Devolve null quando não há.
     */
    public function findByDocument(string $document): ?Entity
    {
        return Entity::query()->where('document', $document)->withAllRoles()->first();
    }

    /**
     * Quem tem este documento, no cadastro ou excluído — o do cadastro
     * primeiro. É por aqui que um papel novo acha a pessoa.
     */
    public function findForRegistration(string $document): ?Entity
    {
        return Entity::withTrashed()
            ->where('document', $document)
            ->orderByRaw('deleted_at is null desc')
            ->latest('deleted_at')
            ->first();
    }

    /**
     * Grava os dados do formulário na pessoa achada por
     * `findForRegistration()`, ou cria uma.
     *
     * Quem foi excluído volta — o mesmo registro, com o mesmo uuid —, em vez
     * de nascer uma segunda entidade com o mesmo documento. Chamar dentro de
     * uma transação.
     *
     * @param  array<string, mixed>  $data
     */
    public function register(?Entity $entity, array $data): Entity
    {
        if ($entity === null) {
            return Entity::query()->create($data);
        }

        if ($entity->trashed()) {
            $entity->restore();
        }

        $entity->update($data);

        return $entity;
    }

    /**
     * Grava os dados da pessoa. Vale para todos os papéis dela: o nome
     * corrigido no cadastro de cliente aparece no de fornecedor.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Entity $entity, array $data): void
    {
        if (isset($data['document'])) {
            $this->assertDocumentIsFree($entity, $data['document']);
        }

        $entity->update($data);
    }

    /**
     * Exclui a pessoa de todos os cadastros de uma vez.
     */
    public function delete(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $entity = $this->get($uuid);

            $entity->roleRecords()->each->delete();
            $entity->delete();
        });
    }

    /**
     * Sem papel nenhum, a pessoa sai do cadastro. Volta sozinha se for
     * cadastrada de novo — ver `register()`.
     */
    public function deleteIfOrphan(Entity $entity): void
    {
        $hasRole = collect(EntityRole::cases())
            ->contains(fn (EntityRole $role) => $entity->{$role->relation()}()->exists());

        if (! $hasRole) {
            $entity->delete();
        }
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
