<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\CommonEntity\UpdateCommonEntityRequest;
use App\Http\Resources\Registry\CommonEntity\CommonEntityResource;
use App\Models\Registry\CommonEntity;
use App\Services\Registry\CommonEntityService;
use DomainException;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CommonEntityController extends Controller
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    public function index()
    {
        try {
            $entities = $this->commonEntityService->list();

            return CommonEntityResource::collection($entities);
        } catch (DomainException $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $uuid)
    {
        try {
            $entity = $this->commonEntityService->get($uuid);

            return new CommonEntityResource($entity);
        } catch (DomainException $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateCommonEntityRequest $request, string $uuid)
    {
        try {
            $this->commonEntityService->update($uuid, $request->validated());

            return response()->json([
                'message' => 'Entidade atualizada com sucesso',
            ]);
        } catch (DomainException $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a common entity by document.
     */
    public function byDocument(string $document): JsonResponse
    {
        $entity = CommonEntity::query()
            ->withExists('supplier')
            ->where('cpf_cnpj', $document)
            ->first();

        return response()->json(['data' => $entity]);
    }
}
