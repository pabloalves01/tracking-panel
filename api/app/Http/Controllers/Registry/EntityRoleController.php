<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\EntityRequest;
use App\Http\Resources\Registry\EntityRoleResource;
use App\Services\Registry\EntityRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * As ações que todo cadastro de papel tem. O controller do papel diz qual é
 * o papel, qual request valida e — se tiver colunas próprias — qual resource
 * as devolve.
 *
 * `store` e `update` ficam no controller do papel porque é lá que a request
 * tipada é injetada; daqui saem `storeWith` e `updateWith`.
 */
abstract class EntityRoleController extends Controller
{
    public function __construct(protected readonly EntityRoleService $roles) {}

    abstract protected function role(): EntityRole;

    /**
     * @return class-string<EntityRoleResource>
     */
    protected function resource(): string
    {
        return EntityRoleResource::class;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $model = $this->role()->model();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([$model::ACTIVE, $model::INACTIVE])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->resource()::collection($this->roles->list($this->role(), $filters));
    }

    public function show(string $uuid): EntityRoleResource
    {
        return $this->resource()::make($this->roles->get($this->role(), $uuid));
    }

    public function activate(string $uuid): JsonResponse
    {
        $this->roles->setStatus($this->role(), $uuid, $this->role()->model()::ACTIVE);

        return response()->json(['message' => $this->done('ativad')]);
    }

    public function deactivate(string $uuid): JsonResponse
    {
        $this->roles->setStatus($this->role(), $uuid, $this->role()->model()::INACTIVE);

        return response()->json(['message' => $this->done('desativad')]);
    }

    /**
     * Exclui o papel. A pessoa continua cadastrada enquanto exercer outro.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $this->roles->remove($this->role(), $uuid);

        return response()->json(['message' => $this->done('excluíd')]);
    }

    protected function storeWith(EntityRequest $request): JsonResponse
    {
        $record = $this->roles->store($this->role(), $request->entityData(), $request->roleData());

        return response()->json([
            'message' => $this->done('cadastrad'),
            'data' => $this->resource()::make($record),
        ], 201);
    }

    protected function updateWith(EntityRequest $request, string $uuid): JsonResponse
    {
        $record = $this->roles->update($this->role(), $uuid, $request->entityData(), $request->roleData());

        return response()->json([
            'message' => $this->done('atualizad'),
            'data' => $this->resource()::make($record),
        ]);
    }

    /** "Cliente cadastrado com sucesso", "Transportadora cadastrada com sucesso". */
    private function done(string $stem): string
    {
        $role = $this->role();

        return sprintf('%s %s%s com sucesso', $role->label(), $stem, $role->isFeminine() ? 'a' : 'o');
    }
}
