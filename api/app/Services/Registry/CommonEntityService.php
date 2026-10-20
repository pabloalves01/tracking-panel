<?php

namespace App\Services\Registry;

use App\Models\Registry\CommonEntity;
use Illuminate\Support\Arr;

class CommonEntityService
{
    public function findOrCreate(array $data): CommonEntity
    {
        $entity = CommonEntity::where('cpf_cnpj', $data['cpf_cnpj'])->first();

        if ($entity) {
            $entity->update(Arr::only($data, ['name', 'trade_name', 'email', 'phone']));

            return $entity;
        }

        return CommonEntity::create($data);
    }
}
