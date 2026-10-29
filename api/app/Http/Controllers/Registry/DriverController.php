<?php

namespace App\Http\Controllers\Registry;

use App\Http\Requests\Registry\DriverRequest;
use App\Http\Resources\Registry\DriverResource;
use App\Services\Registry\DriverService;
use Illuminate\Http\JsonResponse;

/**
 * Motoristas.
 */
class DriverController extends EntityRoleController
{
    public function __construct(private readonly DriverService $drivers) {}

    protected function service(): DriverService
    {
        return $this->drivers;
    }

    protected function resource(): string
    {
        return DriverResource::class;
    }

    public function store(DriverRequest $request): JsonResponse
    {
        return $this->created($this->drivers->store($request->entityData(), $request->roleData()));
    }

    public function update(DriverRequest $request, string $uuid): JsonResponse
    {
        return $this->updated($this->drivers->update($uuid, $request->entityData(), $request->roleData()));
    }
}
