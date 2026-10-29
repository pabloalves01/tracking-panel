<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Employee;

/**
 * Funcionários: a pessoa em `entities`, o funcionário em `employees`.
 */
class EmployeeService extends EntityRoleService
{
    public function role(): EntityRole
    {
        return EntityRole::Employee;
    }

    /**
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function store(array $entityData, array $data): Employee
    {
        $employee = $this->transaction(function () use ($entityData, $data) {
            $employee = $this->enroll($entityData);
            $employee->fill($data)->save();

            return $employee;
        }, self::DOCUMENT_JUST_TAKEN);

        return $this->get($employee->uuid);
    }

    /**
     * Os dados da pessoa valem para todos os papéis dela; os demais, só
     * para este.
     *
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $entityData, array $data): Employee
    {
        $this->transaction(function () use ($uuid, $entityData, $data) {
            $employee = $this->get($uuid);

            $this->entities->update($employee->entity, $entityData);
            $employee->update($data);
        }, self::DOCUMENT_TAKEN);

        return $this->get($uuid);
    }
}
