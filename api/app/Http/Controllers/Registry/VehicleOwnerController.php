<?php

namespace App\Http\Controllers\Registry;

use App\Http\Requests\Registry\VehicleOwnerRequest;
use App\Services\Registry\VehicleOwnerService;
use Illuminate\Http\JsonResponse;

/**
 * Proprietários de veículo.
 */
class VehicleOwnerController extends EntityRoleController
{
    public function __construct(private readonly VehicleOwnerService $owners) {}

    protected function service(): VehicleOwnerService
    {
        return $this->owners;
    }

    public function store(VehicleOwnerRequest $request): JsonResponse
    {
        return $this->created($this->owners->store($request->entityData(), $request->roleData()));
    }

    public function update(VehicleOwnerRequest $request, string $uuid): JsonResponse
    {
        return $this->updated($this->owners->update($uuid, $request->entityData(), $request->roleData()));
    }
}
