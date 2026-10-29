<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * As operações que todo papel tem: listar, cadastrar, editar, inativar e
 * excluir. O que muda de um papel para outro — colunas, validação, saída —
 * está no model, na request e no resource dele; aqui não há `if motorista`.
 *
 * Um papel com regra própria (o funcionário que ganha acesso ao sistema, o
 * cliente com limite de crédito) ganha um service seu, chamado pelo
 * controller dele, em vez de um caso a mais aqui.
 */
class EntityRoleService
{
    public function __construct(private readonly EntityService $entities) {}

    /**
     * @param  array{search?: string|null, status?: string|null, per_page?: int|null}  $filters
     */
    public function list(EntityRole $role, array $filters = []): LengthAwarePaginator
    {
        return $this->query($role)
            ->status($filters['status'] ?? null)
            ->search($filters['search'] ?? null)
            ->orderByName()
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function get(EntityRole $role, string $uuid): Model
    {
        return $this->query($role)->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Cadastra a pessoa no papel.
     *
     * Três casos, pelo documento: ninguém (cria a pessoa e o papel), alguém
     * que exerce outros papéis (reaproveita a pessoa com os dados enviados) e
     * alguém que já exerceu este papel e foi excluído (restaura a mesma
     * linha). Quem exerce este papel agora é recusado.
     *
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $roleData
     */
    public function store(EntityRole $role, array $entityData, array $roleData): Model
    {
        $model = $role->model();

        try {
            $record = DB::transaction(function () use ($role, $model, $entityData, $roleData) {
                $entity = $this->entities->findForRegistration($entityData['document']);
                $record = $entity ? $model::withTrashed()->where('entity_id', $entity->getKey())->first() : null;

                // Antes de gravar a pessoa: recusado, nada do formulário
                // pode ter tocado no cadastro de quem já está lá.
                if ($record !== null && ! $record->trashed()) {
                    throw new DomainException(sprintf(
                        '%s já está cadastrado como %s',
                        $entity->name,
                        mb_strtolower($role->label())
                    ));
                }

                $entity = $this->entities->register($entity, $entityData);

                if ($record?->trashed()) {
                    $record->restore();
                }

                $record ??= new $model(['entity_id' => $entity->getKey()]);
                $record->fill([...$roleData, 'status' => $model::ACTIVE])->save();

                return $record;
            });
        } catch (UniqueConstraintViolationException) {
            // Duas telas cadastrando o mesmo documento ao mesmo tempo: a
            // segunda bate no índice único em vez de duplicar.
            throw new DomainException('Este documento acabou de ser cadastrado em outra tela. Atualize e tente de novo.');
        }

        return $this->get($role, $record->uuid);
    }

    /**
     * Grava a pessoa e o papel na mesma operação.
     *
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $roleData
     */
    public function update(EntityRole $role, string $uuid, array $entityData, array $roleData): Model
    {
        try {
            DB::transaction(function () use ($role, $uuid, $entityData, $roleData) {
                $record = $this->get($role, $uuid);

                $this->entities->update($record->entity, $entityData);
                $record->update($roleData);
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Já existe outro cadastro com este documento');
        }

        return $this->get($role, $uuid);
    }

    public function setStatus(EntityRole $role, string $uuid, string $status): void
    {
        $this->get($role, $uuid)->update(['status' => $status]);
    }

    /**
     * Exclui o papel. A pessoa continua enquanto exercer outro; sem nenhum,
     * sai do cadastro junto.
     *
     * A exclusão é lógica: o que a operação registrar apontando para este
     * papel — viagens do motorista, contas do fornecedor — continua
     * apontando para uma linha que existe.
     */
    public function remove(EntityRole $role, string $uuid): void
    {
        DB::transaction(function () use ($role, $uuid) {
            $record = $this->get($role, $uuid);

            $record->delete();
            $this->entities->deleteIfOrphan($record->entity);
        });
    }

    private function query(EntityRole $role)
    {
        return $role->model()::query()->with(['entity' => fn ($query) => $query->withAllRoles()]);
    }
}
