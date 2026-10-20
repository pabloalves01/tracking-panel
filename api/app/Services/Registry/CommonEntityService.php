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
            // Documento já existe: atualiza contato e localização junto com o cadastro.
            $entity->update(Arr::only($data, [
                'name',
                'trade_name',
                'email',
                'phone',
                'postal_code',
                'street',
                'street_number',
                'complement',
                'neighborhood',
                'state_id',
                'city_id',
                'postal_code',
                'street',
                'street_number',
                'complement',
                'neighborhood',
            ]));

            return $entity;
        }

        return CommonEntity::create($data);
    }
}
