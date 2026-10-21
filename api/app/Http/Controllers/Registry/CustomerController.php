<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\Customer\StoreCustomerRequest;
use App\Http\Requests\Registry\Customer\UpdateCustomerRequest;
use App\Http\Resources\Registry\Customer\CustomerResource;
use App\Services\Registry\CustomerService;
use DomainException;
use Exception;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function __construct(private CustomerService $customerService) {}

    public function index()
    {
        try {
            $customers = $this->customerService->list();

            return CustomerResource::collection($customers);
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

    public function store(StoreCustomerRequest $request)
    {
        try {
            $this->customerService->store($request->validated());

            return response()->json([
                'message' => 'Cliente criado com sucesso',
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
            $customer = $this->customerService->get($uuid);

            return new CustomerResource($customer);
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

    public function update(UpdateCustomerRequest $request, string $uuid)
    {
        try {
            $this->customerService->update($uuid, $request->validated());

            return response()->json([
                'message' => 'Cliente atualizado com sucesso',
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
            $this->customerService->deactivate($uuid);

            return response()->json([
                'message' => 'Cliente desativado com sucesso',
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
            $this->customerService->activate($uuid);

            return response()->json([
                'message' => 'Cliente ativado com sucesso',
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
