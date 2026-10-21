<?php

namespace App\Services\Registry;

use App\Models\Registry\CommonEntity;

class CommonEntityService
{
    public function list()
    {
        return CommonEntity::query()->orderBy('name')->get();
    }

    public function get(string $uuid): CommonEntity
    {
        return CommonEntity::query()->where('uuid', $uuid)->firstOrFail();
    }

    public function update(string $uuid, array $data): CommonEntity
    {
        $entity = $this->get($uuid);
        $entity->update($data);

        return $entity;
    }

    public function findOrCreate(array $data): CommonEntity
    {
        $entity = CommonEntity::query()->where('cpf_cnpj', $data['cpf_cnpj'])->first();

        if ($entity) {
            return $entity;
        }

        return CommonEntity::create($data);
    }
}
