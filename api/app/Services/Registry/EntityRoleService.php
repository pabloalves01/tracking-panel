<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use Closure;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * A base do service de cada papel (`DriverService`, `CustomerService`…).
 *
 * Aqui fica só o que não depende do papel: listar, abrir, ativar, inativar,
 * excluir e achar a pessoa pelo documento. Cadastrar e editar são do
 * service do papel — é lá que os dados próprios dele são gravados, junto
 * com a pessoa.
 */
abstract class EntityRoleService
{
    protected const DOCUMENT_JUST_TAKEN = 'Este documento acabou de ser cadastrado em outra tela. Atualize e tente de novo.';

    protected const DOCUMENT_TAKEN = 'Já existe outro cadastro com este documento';

    public function __construct(protected readonly EntityService $entities) {}

    abstract public function role(): EntityRole;

    /**
     * @param  array{search?: string|null, status?: string|null, per_page?: int|null}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->query()
            ->status($filters['status'] ?? null)
            ->search($filters['search'] ?? null)
            ->orderByName()
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function get(string $uuid): Model
    {
        return $this->query()->where('uuid', $uuid)->firstOrFail();
    }

    public function setStatus(string $uuid, string $status): void
    {
        $this->get($uuid)->update(['status' => $status]);
    }

    /**
     * Exclui o papel. A pessoa continua enquanto exercer outro; sem nenhum,
     * sai do cadastro junto.
     *
     * A exclusão é lógica: o que a operação registrar apontando para este
     * papel — viagens do motorista, contas do fornecedor — continua
     * apontando para uma linha que existe.
     */
    public function remove(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $record = $this->get($uuid);

            $record->delete();
            $this->entities->deleteIfOrphan($record->entity);
        });
    }

    /**
     * Grava a pessoa e devolve a linha do papel para ela, ativa e ainda sem
     * os dados próprios — quem chama preenche e salva.
     *
     * Três casos, pelo documento: ninguém (pessoa e linha novas), alguém que
     * exerce outros papéis (reaproveita a pessoa com os dados enviados) e
     * alguém que já exerceu este papel e foi excluído (restaura a mesma
     * linha). Quem exerce este papel agora é recusado antes de qualquer
     * gravação. Chamar dentro de `transaction()`.
     *
     * @param  array<string, mixed>  $entityData
     */
    protected function enroll(array $entityData): Model
    {
        $model = $this->role()->model();

        $entity = $this->entities->findForRegistration($entityData['document']);
        $record = $entity ? $model::withTrashed()->where('entity_id', $entity->getKey())->first() : null;

        if ($record !== null && ! $record->trashed()) {
            throw new DomainException(sprintf(
                '%s já está cadastrado como %s',
                $entity->name,
                mb_strtolower($this->role()->label())
            ));
        }

        $entity = $this->entities->register($entity, $entityData);

        if ($record?->trashed()) {
            $record->restore();
        }

        $record ??= new $model(['entity_id' => $entity->getKey()]);
        $record->status = $model::ACTIVE;

        return $record;
    }

    /**
     * Roda a gravação numa transação. O índice único violado — duas telas
     * gravando o mesmo documento ao mesmo tempo — vira `$conflict` em vez
     * de erro 500.
     */
    protected function transaction(Closure $callback, string $conflict): mixed
    {
        try {
            return DB::transaction($callback);
        } catch (UniqueConstraintViolationException) {
            throw new DomainException($conflict);
        }
    }

    private function query()
    {
        return $this->role()->model()::query()->with(['entity' => fn ($query) => $query->withAllRoles()]);
    }
}
