<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Driver;

/**
 * Motoristas: a pessoa em `entities`, a CNH em `drivers`.
 */
class DriverService extends EntityRoleService
{
    public function role(): EntityRole
    {
        return EntityRole::Driver;
    }

    /**
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function store(array $entityData, array $data): Driver
    {
        $driver = $this->transaction(function () use ($entityData, $data) {
            $driver = $this->enroll($entityData);
            $driver->fill($data)->save();

            return $driver;
        }, self::DOCUMENT_JUST_TAKEN);

        return $this->get($driver->uuid);
    }

    /**
     * Os dados da pessoa valem para todos os papéis dela; os demais, só
     * para este.
     *
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $entityData, array $data): Driver
    {
        $this->transaction(function () use ($uuid, $entityData, $data) {
            $driver = $this->get($uuid);

            $this->entities->update($driver->entity, $entityData);
            $driver->update($data);
        }, self::DOCUMENT_TAKEN);

        return $this->get($uuid);
    }
}
