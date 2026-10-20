<?php

namespace App\Services\Registry;

use App\Models\Registry\CommonEntity;

class CommonEntityService
{
    public function findOrCreate(array $data): CommonEntity
    {
        $entity = CommonEntity::where('cpf_cnpj', $data['cpf_cnpj'])->first();

        if ($entity) {
            return $entity;
        }

        return CommonEntity::create($data);
    }
}
