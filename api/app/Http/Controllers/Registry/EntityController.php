<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\StoreEntityRequest;
use App\Http\Requests\Registry\UpdateEntityRequest;
use App\Http\Resources\Registry\EntityResource;
use App\Models\Registry\EntityRoleAssignment;
use App\Services\Registry\EntityService;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Um controller para os sete cadastros. O papel vem da rota.
 *
 * O `try/catch` saiu daqui: `DomainException` e os demais erros são
 * traduzidos uma vez em `bootstrap/app.php`.
 */
class EntityController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request, EntityRole $role): AnonymousResourceCollection
    {
        return EntityResource::collection(
            $this->entities->list($role, [
                'search' => $request->query('search'),
                'status' => $request->query('status'),
                'per_page' => $request->query('per_page'),
            ])
        );
    }

    /**
     * Procura pelo documento enquanto o usuário digita, para o formulário
     * avisar que a pessoa já existe antes de ele preencher tudo de novo.
     */
    public function byDocument(string $document): JsonResponse
    {
        $entity = $this->entities->findByDocument(Text::digits($document));

        return response()->json([
            'data' => $entity ? new EntityResource($entity) : null,
        ]);
    }

    public function store(StoreEntityRequest $request, EntityRole $role): JsonResponse
    {
        $entity = $this->entities->store($role, $request->validated());

        return response()->json([
            'message' => sprintf('%s %s com sucesso', $role->label(), $role->isFeminine() ? 'cadastrada' : 'cadastrado'),
            'data' => new EntityResource($entity),
        ], 201);
    }

    public function show(EntityRole $role, string $uuid): EntityResource
    {
        return new EntityResource($this->entities->get($uuid));
    }

    public function update(UpdateEntityRequest $request, EntityRole $role, string $uuid): JsonResponse
    {
        $entity = $this->entities->update($uuid, $role, $request->validated());

        return response()->json([
            'message' => sprintf('%s %s com sucesso', $role->label(), $role->isFeminine() ? 'atualizada' : 'atualizado'),
            'data' => new EntityResource($entity),
        ]);
    }

    public function activate(EntityRole $role, string $uuid): JsonResponse
    {
        $this->entities->setRoleStatus($uuid, $role, EntityRoleAssignment::ACTIVE);

        return response()->json([
            'message' => sprintf('%s %s com sucesso', $role->label(), $role->isFeminine() ? 'ativada' : 'ativado'),
        ]);
    }

    public function deactivate(EntityRole $role, string $uuid): JsonResponse
    {
        $this->entities->setRoleStatus($uuid, $role, EntityRoleAssignment::INACTIVE);

        return response()->json([
            'message' => sprintf('%s %s com sucesso', $role->label(), $role->isFeminine() ? 'desativada' : 'desativado'),
        ]);
    }

    /**
     * Tira o papel. A pessoa continua cadastrada enquanto exercer outro.
     */
    public function destroy(EntityRole $role, string $uuid): JsonResponse
    {
        $this->entities->removeRole($uuid, $role);

        return response()->json([
            'message' => sprintf('%s %s do cadastro', $role->label(), $role->isFeminine() ? 'removida' : 'removido'),
        ]);
    }

}
