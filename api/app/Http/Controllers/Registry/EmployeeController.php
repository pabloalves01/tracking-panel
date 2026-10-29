<?php

namespace App\Http\Controllers\Registry;

use App\Enums\EntityRole;
use App\Http\Requests\Registry\EmployeeRequest;
use Illuminate\Http\JsonResponse;

/**
 * Funcionários.
 */
class EmployeeController extends EntityRoleController
{
    protected function role(): EntityRole
    {
        return EntityRole::Employee;
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        return $this->storeWith($request);
    }

    public function update(EmployeeRequest $request, string $uuid): JsonResponse
    {
        return $this->updateWith($request, $uuid);
    }
}
