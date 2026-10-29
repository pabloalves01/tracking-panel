<?php

namespace App\Http\Controllers\Registry;

use App\Http\Requests\Registry\SupplierRequest;
use App\Services\Registry\SupplierService;
use Illuminate\Http\JsonResponse;

/**
 * Fornecedores.
 */
class SupplierController extends EntityRoleController
{
    public function __construct(private readonly SupplierService $suppliers) {}

    protected function service(): SupplierService
    {
        return $this->suppliers;
    }

    public function store(SupplierRequest $request): JsonResponse
    {
        return $this->created($this->suppliers->store($request->entityData(), $request->roleData()));
    }

    public function update(SupplierRequest $request, string $uuid): JsonResponse
    {
        return $this->updated($this->suppliers->update($uuid, $request->entityData(), $request->roleData()));
    }
}
