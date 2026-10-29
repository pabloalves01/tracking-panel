<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Requests\Registry\DriverRequest;
use App\Http\Resources\Registry\DriverResource;
use Illuminate\Http\JsonResponse;

/**
 * Motoristas.
 */
class DriverController extends EntityRoleController
{
    protected function role(): EntityRole
    {
        return EntityRole::Driver;
    }

    protected function resource(): string
    {
        return DriverResource::class;
    }

    public function store(DriverRequest $request): JsonResponse
    {
        return $this->storeWith($request);
    }

    public function update(DriverRequest $request, string $uuid): JsonResponse
    {
        return $this->updateWith($request, $uuid);
    }
}
