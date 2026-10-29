<?php

namespace App\Services\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Supplier;

/**
 * Fornecedores: a pessoa em `entities`, o fornecedor em `suppliers`.
 */
class SupplierService extends EntityRoleService
{
    public function role(): EntityRole
    {
        return EntityRole::Supplier;
    }

    /**
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function store(array $entityData, array $data): Supplier
    {
        $supplier = $this->transaction(function () use ($entityData, $data) {
            $supplier = $this->enroll($entityData);
            $supplier->fill($data)->save();

            return $supplier;
        }, self::DOCUMENT_JUST_TAKEN);

        return $this->get($supplier->uuid);
    }

    /**
     * Os dados da pessoa valem para todos os papéis dela; os demais, só
     * para este.
     *
     * @param  array<string, mixed>  $entityData
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $entityData, array $data): Supplier
    {
        $this->transaction(function () use ($uuid, $entityData, $data) {
            $supplier = $this->get($uuid);

            $this->entities->update($supplier->entity, $entityData);
            $supplier->update($data);
        }, self::DOCUMENT_TAKEN);

        return $this->get($uuid);
    }
}
