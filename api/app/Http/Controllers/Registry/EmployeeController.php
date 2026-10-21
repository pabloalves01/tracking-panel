<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\Employee\StoreEmployeeRequest;
use App\Http\Requests\Registry\Employee\UpdateEmployeeRequest;
use App\Http\Resources\Registry\Employee\EmployeeResource;
use App\Services\Registry\EmployeeService;
use DomainException;
use Exception;
use Illuminate\Support\Facades\Log;

class EmployeeController extends Controller
{
    public function __construct(private EmployeeService $employeeService) {}

    public function index()
    {
        try {
            $employees = $this->employeeService->list();

            return EmployeeResource::collection($employees);
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

    public function store(StoreEmployeeRequest $request)
    {
        try {
            $this->employeeService->store($request->validated());

            return response()->json([
                'message' => 'Funcionário criado com sucesso',
            ], 201);
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
            $employee = $this->employeeService->get($uuid);

            return new EmployeeResource($employee);
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

    public function update(UpdateEmployeeRequest $request, string $uuid)
    {
        try {
            $this->employeeService->update($uuid, $request->validated());

            return response()->json([
                'message' => 'Funcionário atualizado com sucesso',
            ], 200);
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

    public function deactivate(string $uuid)
    {
        try {
            $this->employeeService->deactivate($uuid);

            return response()->json([
                'message' => 'Funcionário desativado com sucesso',
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

    public function activate(string $uuid)
    {
        try {
            $this->employeeService->activate($uuid);

            return response()->json([
                'message' => 'Funcionário ativado com sucesso',
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
}
