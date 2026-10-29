<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\VehicleOwner;

/**
 * Proprietários de veículo: a pessoa em `entities`, o proprietário em `vehicle_owners`.
 */
class VehicleOwnerService extends EntityRoleService
{
    public function role(): EntityRole
    {
        return EntityRole::VehicleOwner;
    }

    /**
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function store(array $entityData, array $data): VehicleOwner
    {
        $vehicleOwner = $this->transaction(function () use ($entityData, $data) {
            $vehicleOwner = $this->enroll($entityData);
            $vehicleOwner->fill($data)->save();

            return $vehicleOwner;
        }, self::DOCUMENT_JUST_TAKEN);

        return $this->get($vehicleOwner->uuid);
    }

    /**
     * Os dados da pessoa valem para todos os papéis dela; os demais, só
     * para este.
     *
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $entityData, array $data): VehicleOwner
    {
        $this->transaction(function () use ($uuid, $entityData, $data) {
            $vehicleOwner = $this->get($uuid);

            $this->entities->update($vehicleOwner->entity, $entityData);
            $vehicleOwner->update($data);
        }, self::DOCUMENT_TAKEN);

        return $this->get($uuid);
    }
}
