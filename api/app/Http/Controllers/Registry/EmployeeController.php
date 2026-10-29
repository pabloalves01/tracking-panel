<?php

namespace App\Http\Controllers\Registry;

use App\Http\Requests\Registry\EmployeeRequest;
use App\Services\Registry\EmployeeService;
use Illuminate\Http\JsonResponse;

/**
 * Funcionários.
 */
class EmployeeController extends EntityRoleController
{
    public function __construct(private readonly EmployeeService $employees) {}

    protected function service(): EmployeeService
    {
        return $this->employees;
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        return $this->created($this->employees->store($request->entityData(), $request->roleData()));
    }

    public function update(EmployeeRequest $request, string $uuid): JsonResponse
    {
        return $this->updated($this->employees->update($uuid, $request->entityData(), $request->roleData()));
    }
}
