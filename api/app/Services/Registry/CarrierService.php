<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Carrier;

/**
 * Transportadoras: a pessoa em `entities`, a transportadora em `carriers`.
 */
class CarrierService extends EntityRoleService
{
    public function role(): EntityRole
    {
        return EntityRole::Carrier;
    }

    /**
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function store(array $entityData, array $data): Carrier
    {
        $carrier = $this->transaction(function () use ($entityData, $data) {
            $carrier = $this->enroll($entityData);
            $carrier->fill($data)->save();

            return $carrier;
        }, self::DOCUMENT_JUST_TAKEN);

        return $this->get($carrier->uuid);
    }

    /**
     * Os dados da pessoa valem para todos os papéis dela; os demais, só
     * para este.
     *
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $entityData, array $data): Carrier
    {
        $this->transaction(function () use ($uuid, $entityData, $data) {
            $carrier = $this->get($uuid);

            $this->entities->update($carrier->entity, $entityData);
            $carrier->update($data);
        }, self::DOCUMENT_TAKEN);

        return $this->get($uuid);
    }
}
