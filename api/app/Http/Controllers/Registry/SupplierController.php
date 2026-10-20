<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\Supplier\StoreSupplierRequest;
use App\Http\Requests\Registry\Supplier\UpdateSupplierRequest;
use App\Http\Resources\Registry\Supplier\SupplierResource;
use App\Services\Registry\SupplierService;
use DomainException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller
{
    public function __construct(private SupplierService $supplierService) {}

    public function index()
    {
        try {
            $suppliers = $this->supplierService->list();

            return SupplierResource::collection($suppliers);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreSupplierRequest $request)
    {
        try {
            $this->supplierService->store($request->validated());

            return response()->json([
                'message' => 'Fornecedor criado com sucesso',
            ], 201);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
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
            $supplier = $this->supplierService->get($uuid);

            return new SupplierResource($supplier);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateSupplierRequest $request, string $uuid)
    {
        try {
            $this->supplierService->update($uuid, $request->validated());

            return response()->json([
                'message' => 'Fornecedor atualizado com sucesso',
            ], 200);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, $uuid)
    {
        try {
            $this->supplierService->delete($uuid);

            return response()->json([
                'message' => 'Fornecedor excluído com sucesso',
            ]);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
