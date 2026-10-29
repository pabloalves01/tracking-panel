<?php

namespace App\Http\Controllers\Registry;

use App\Http\Requests\Registry\VehicleResponsibleRequest;
use App\Services\Registry\VehicleResponsibleService;
use Illuminate\Http\JsonResponse;

/**
 * Responsáveis por veículo.
 */
class VehicleResponsibleController extends EntityRoleController
{
    public function __construct(private readonly VehicleResponsibleService $responsibles) {}

    protected function service(): VehicleResponsibleService
    {
        return $this->responsibles;
    }

    public function store(VehicleResponsibleRequest $request): JsonResponse
    {
        return $this->created($this->responsibles->store($request->entityData(), $request->roleData()));
    }

    public function update(VehicleResponsibleRequest $request, string $uuid): JsonResponse
    {
        return $this->updated($this->responsibles->update($uuid, $request->entityData(), $request->roleData()));
    }
}
