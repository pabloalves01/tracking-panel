<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Requests\Registry\CarrierRequest;
use Illuminate\Http\JsonResponse;

/**
 * Transportadoras.
 */
class CarrierController extends EntityRoleController
{
    protected function role(): EntityRole
    {
        return EntityRole::Carrier;
    }

    public function store(CarrierRequest $request): JsonResponse
    {
        return $this->storeWith($request);
    }

    public function update(CarrierRequest $request, string $uuid): JsonResponse
    {
        return $this->updateWith($request, $uuid);
    }
}
