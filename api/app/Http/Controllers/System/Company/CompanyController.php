<?php

namespace App\Http\Controllers\System\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\Company\StoreCompanyRequest;
use App\Http\Requests\System\Company\UpdateCompanyRequest;
use App\Http\Resources\System\Company\CompanyResource;
use App\Services\System\CompanyService;
use DomainException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    public function __construct(private CompanyService $companyService) {}

    public function index()
    {
        try {
            $companies = $this->companyService->list();
            return CompanyResource::collection($companies);
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

    public function store(StoreCompanyRequest $request)
    {
        try {
            $this->companyService->store($request->validated());
            return response()->json([
                'message' => 'Empresa criada com sucesso',
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

    public function show(Request $request, $uuid)
    {
        try {
            $company = $this->companyService->get($uuid);
            return CompanyResource::make($company);
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

    public function update(UpdateCompanyRequest $request, $uuid)
    {
        try {
            $this->companyService->update($uuid, $request->validated());
            return response()->json([
                'message' => 'Empresa atualizada com sucesso',
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

    public function destroy(Request $request, $uuid)
    {
        try {
            $this->companyService->delete($uuid);
            return response()->json([
                'message' => 'Empresa excluída com sucesso',
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
