<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Resources\Registry\EntityRoleResource;
use App\Services\Registry\EntityRoleService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * As ações que todo cadastro de papel tem: listar, abrir, ativar, inativar
 * e excluir. `store` e `update` são do controller do papel, que valida com
 * a request dele e grava pelo service dele.
 */
abstract class EntityRoleController extends Controller
{
    abstract protected function service(): EntityRoleService;

    /**
     * @return class-string<EntityRoleResource>
     */
    protected function resource(): string
    {
        return EntityRoleResource::class;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $model = $this->service()->role()->model();

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([$model::ACTIVE, $model::INACTIVE])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return $this->resource()::collection($this->service()->list($filters));
    }

    public function show(string $uuid): EntityRoleResource
    {
        return $this->resource()::make($this->service()->get($uuid));
    }

    public function activate(string $uuid): JsonResponse
    {
        $this->service()->setStatus($uuid, $this->service()->role()->model()::ACTIVE);

        return response()->json(['message' => $this->done('ativad')]);
    }

    public function deactivate(string $uuid): JsonResponse
    {
        $this->service()->setStatus($uuid, $this->service()->role()->model()::INACTIVE);

        return response()->json(['message' => $this->done('desativad')]);
    }

    /**
     * Exclui o papel. A pessoa continua cadastrada enquanto exercer outro.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $this->service()->remove($uuid);

        return response()->json(['message' => $this->done('excluíd')]);
    }

    protected function created(Model $record): JsonResponse
    {
        return response()->json([
            'message' => $this->done('cadastrad'),
            'data' => $this->resource()::make($record),
        ], 201);
    }

    protected function updated(Model $record): JsonResponse
    {
        return response()->json([
            'message' => $this->done('atualizad'),
            'data' => $this->resource()::make($record),
        ]);
    }

    /** "Cliente cadastrado com sucesso", "Transportadora cadastrada com sucesso". */
    private function done(string $stem): string
    {
        $role = $this->service()->role();

        return sprintf('%s %s%s com sucesso', $role->label(), $stem, $role->isFeminine() ? 'a' : 'o');
    }
}
