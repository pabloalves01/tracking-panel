<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Requests\Registry\CustomerRequest;
use Illuminate\Http\JsonResponse;

/**
 * Clientes.
 */
class CustomerController extends EntityRoleController
{
    protected function role(): EntityRole
    {
        return EntityRole::Customer;
    }

    public function store(CustomerRequest $request): JsonResponse
    {
        return $this->storeWith($request);
    }

    public function update(CustomerRequest $request, string $uuid): JsonResponse
    {
        return $this->updateWith($request, $uuid);
    }
}
