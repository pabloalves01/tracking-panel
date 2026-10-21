<?php

namespace App\Services\Registry;

use App\Models\Registry\CommonEntity;

class CommonEntityService
{
    public function findOrCreate(array $data): CommonEntity
    {
        $entity = CommonEntity::where('cpf_cnpj', $data['cpf_cnpj'])->first();

        if ($entity) {

            $entity->update([
                'name' => $data['name'],
                'trade_name' => $data['trade_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'postal_code' => $data['postal_code'] ?? null,
                'street' => $data['street'] ?? null,
                'street_number' => $data['street_number'] ?? null,
                'complement' => $data['complement'] ?? null,
                'neighborhood' => $data['neighborhood'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
            ]);
            return $entity;
        }

        return CommonEntity::create($data);
    }
}
