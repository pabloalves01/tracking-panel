<?php

namespace App\Http\Resources\Registry\Supplier;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    /**
     * Fornecedor como sai na API.
     * O `id` interno não é exposto; o cliente identifica pelo `uuid`.
     * Nome, documento e contato vêm da entidade comum.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->entity?->name,
            'trade_name' => $this->entity?->trade_name,
            'person_type' => $this->entity?->person_type,
            'cpf_cnpj' => $this->entity?->cpf_cnpj,
            'email' => $this->entity?->email,
            'phone' => $this->entity?->phone,
            'postal_code' => $this->entity?->postal_code,
            'street' => $this->entity?->street,
            'street_number' => $this->entity?->street_number,
            'complement' => $this->entity?->complement,
            'neighborhood' => $this->entity?->neighborhood,
            'state_id' => $this->entity?->state_id,
            'city_id' => $this->entity?->city_id,
            'pix_key_type' => $this->pix_key_type,
            'pix_key' => $this->pix_key,
            'status' => $this->status,
        ];
    }
}
