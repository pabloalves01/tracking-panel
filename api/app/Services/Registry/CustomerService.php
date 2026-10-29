<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Customer;

/**
 * Clientes: a pessoa em `entities`, o cliente em `customers`.
 */
class CustomerService extends EntityRoleService
{
    public function role(): EntityRole
    {
        return EntityRole::Customer;
    }

    /**
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function store(array $entityData, array $data): Customer
    {
        $customer = $this->transaction(function () use ($entityData, $data) {
            $customer = $this->enroll($entityData);
            $customer->fill($data)->save();

            return $customer;
        }, self::DOCUMENT_JUST_TAKEN);

        return $this->get($customer->uuid);
    }

    /**
     * Os dados da pessoa valem para todos os papéis dela; os demais, só
     * para este.
     *
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $entityData, array $data): Customer
    {
        $this->transaction(function () use ($uuid, $entityData, $data) {
            $customer = $this->get($uuid);

            $this->entities->update($customer->entity, $entityData);
            $customer->update($data);
        }, self::DOCUMENT_TAKEN);

        return $this->get($uuid);
    }
}
