<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Requests\Registry\VehicleOwnerRequest;
use Illuminate\Http\JsonResponse;

/**
 * Proprietários de veículo.
 */
class VehicleOwnerController extends EntityRoleController
{
    protected function role(): EntityRole
    {
        return EntityRole::VehicleOwner;
    }

    public function store(VehicleOwnerRequest $request): JsonResponse
    {
        return $this->storeWith($request);
    }

    public function update(VehicleOwnerRequest $request, string $uuid): JsonResponse
    {
        return $this->updateWith($request, $uuid);
    }
}
