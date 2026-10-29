<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\VehicleResponsible;

/**
 * Responsáveis por veículo: a pessoa em `entities`, o responsável em `vehicle_responsibles`.
 */
class VehicleResponsibleService extends EntityRoleService
{
    public function role(): EntityRole
    {
        return EntityRole::VehicleResponsible;
    }

    /**
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function store(array $entityData, array $data): VehicleResponsible
    {
        $responsible = $this->transaction(function () use ($entityData, $data) {
            $responsible = $this->enroll($entityData);
            $responsible->fill($data)->save();

            return $responsible;
        }, self::DOCUMENT_JUST_TAKEN);

        return $this->get($responsible->uuid);
    }

    /**
     * Os dados da pessoa valem para todos os papéis dela; os demais, só
     * para este.
     *
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $entityData, array $data): VehicleResponsible
    {
        $this->transaction(function () use ($uuid, $entityData, $data) {
            $responsible = $this->get($uuid);

            $this->entities->update($responsible->entity, $entityData);
            $responsible->update($data);
        }, self::DOCUMENT_TAKEN);

        return $this->get($uuid);
    }
}
