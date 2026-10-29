<?php

namespace App\Http\Controllers\Registry;

use App\Http\Requests\Registry\CustomerRequest;
use App\Services\Registry\CustomerService;
use Illuminate\Http\JsonResponse;

/**
 * Clientes.
 */
class CustomerController extends EntityRoleController
{
    public function __construct(private readonly CustomerService $customers) {}

    protected function service(): CustomerService
    {
        return $this->customers;
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        return $this->created($this->customers->store($request->entityData(), $request->roleData()));
    }

    public function update(CustomerRequest $request, string $uuid): JsonResponse
    {
        return $this->updated($this->customers->update($uuid, $request->entityData(), $request->roleData()));
    }
}
