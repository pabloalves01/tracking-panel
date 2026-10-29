<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Requests\Registry\VehicleResponsibleRequest;
use Illuminate\Http\JsonResponse;

/**
 * Responsáveis por veículo.
 */
class VehicleResponsibleController extends EntityRoleController
{
    protected function role(): EntityRole
    {
        return EntityRole::VehicleResponsible;
    }

    public function store(VehicleResponsibleRequest $request): JsonResponse
    {
        return $this->storeWith($request);
    }

    public function update(VehicleResponsibleRequest $request, string $uuid): JsonResponse
    {
        return $this->updateWith($request, $uuid);
    }
}
