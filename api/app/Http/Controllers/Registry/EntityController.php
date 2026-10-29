<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\Registry\EntityResource;
use App\Services\Registry\EntityService;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * A pessoa, acima dos papéis: a lista geral, a consulta por documento do
 * formulário e a exclusão de todos os cadastros de uma vez. Cadastrar e
 * editar acontecem sempre por um papel — ver `EntityRoleController`.
 */
class EntityController extends Controller
{
    public function __construct(private readonly EntityService $entities) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::enum(EntityRole::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return EntityResource::collection($this->entities->list($filters));
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

    /**
     * Exclui a pessoa de todos os cadastros.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $this->entities->delete($uuid);

        return response()->json(['message' => 'Cadastro excluído com sucesso']);
    }
}
