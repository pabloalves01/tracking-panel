<?php

namespace App\Http\Resources\Registry\Employee;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Funcionário como sai na API.
     * O `id` interno não é exposto; a tela identifica pelo `uuid`.
     * Nome, documento e contato vêm da entidade comum.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'entity_uuid' => $this->entity?->uuid,
            'name' => $this->entity?->name,
            'trade_name' => $this->entity?->trade_name,
            'person_type' => $this->entity?->person_type,
            'cpf_cnpj' => $this->entity?->cpf_cnpj,
            'email' => $this->entity?->email,
            'phone' => $this->entity?->phone,
            'rg' => $this->entity?->rg,
            'state_registration' => $this->entity?->state_registration,
            'emergency_phone' => $this->entity?->emergency_phone,
            'emergency_phone_type' => $this->entity?->emergency_phone_type,
            'postal_code' => $this->entity?->postal_code,
            'street' => $this->entity?->street,
            'street_number' => $this->entity?->street_number,
            'complement' => $this->entity?->complement,
            'neighborhood' => $this->entity?->neighborhood,
            'state_id' => $this->entity?->state_id,
            'city_id' => $this->entity?->city_id,
            'status' => $this->status,
        ];
    }
}
