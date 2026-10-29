<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Requests\Registry\SupplierRequest;
use Illuminate\Http\JsonResponse;

/**
 * Fornecedores.
 */
class SupplierController extends EntityRoleController
{
    protected function role(): EntityRole
    {
        return EntityRole::Supplier;
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        return $this->storeWith($request);
    }

    public function update(SupplierRequest $request, string $uuid): JsonResponse
    {
        return $this->updateWith($request, $uuid);
    }
}
