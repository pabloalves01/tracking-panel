<?php

namespace App\Http\Resources\Registry\CommonEntity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommonEntityResource extends JsonResource
{
    /**
     * Entidade comum como sai na API.
     * O `id` interno não é exposto; o cliente identifica pelo `uuid`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'trade_name' => $this->trade_name,
            'person_type' => $this->person_type,
            'cpf_cnpj' => $this->cpf_cnpj,
            'email' => $this->email,
            'phone' => $this->phone,
            'rg_ie' => $this->rg_ie,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'emergency_phone' => $this->emergency_phone,
            'emergency_phone_type' => $this->emergency_phone_type,
            'document_file_path' => $this->document_file_path,
            'postal_code' => $this->postal_code,
            'street' => $this->street,
            'street_number' => $this->street_number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,
        ];
    }
}
