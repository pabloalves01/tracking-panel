<?php

namespace App\Http\Controllers\Registry;

use App\Http\Requests\Registry\CarrierRequest;
use App\Http\Resources\Registry\CarrierResource;
use App\Services\Registry\CarrierService;
use Illuminate\Http\JsonResponse;

/**
 * Transportadoras.
 */
class CarrierController extends EntityRoleController
{
    public function __construct(private readonly CarrierService $carriers) {}

    protected function service(): CarrierService
    {
        return $this->carriers;
    }

    protected function resource(): string
    {
        return CarrierResource::class;
    }

    public function store(CarrierRequest $request): JsonResponse
    {
        return $this->created($this->carriers->store($request->entityData(), $request->roleData()));
    }

    public function update(CarrierRequest $request, string $uuid): JsonResponse
    {
        return $this->updated($this->carriers->update($uuid, $request->entityData(), $request->roleData()));
    }
}
