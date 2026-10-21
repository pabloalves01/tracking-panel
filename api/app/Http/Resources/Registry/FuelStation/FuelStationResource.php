<?php

namespace App\Http\Resources\Registry\FuelStation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelStationResource extends JsonResource
{
    /**
     * Posto como sai na API.
     * O `id` interno não é exposto; o cliente identifica pelo `uuid`.
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
            'postal_code' => $this->entity?->postal_code,
            'street' => $this->entity?->street,
            'street_number' => $this->entity?->street_number,
            'complement' => $this->entity?->complement,
            'neighborhood' => $this->entity?->neighborhood,
            'state_id' => $this->entity?->state_id,
            'city_id' => $this->entity?->city_id,
            'pix_key_type' => $this->pix_key_type,
            'pix_key' => $this->pix_key,
            'login' => $this->login,
            'contact_name' => $this->contact_name,
            'credit_limit' => $this->credit_limit,
            'default_payment_method' => $this->default_payment_method,
            'diesel_s10_price' => $this->diesel_s10_price,
            'diesel_s500_price' => $this->diesel_s500_price,
            'gasoline_price' => $this->gasoline_price,
            'additive_gasoline_price' => $this->additive_gasoline_price,
            'billing_recurrence' => $this->billing_recurrence,
            'billing_cutoff_day' => $this->billing_cutoff_day,
            'billing_last_day_of_month' => $this->billing_last_day_of_month,
            'billing_interval_days' => $this->billing_interval_days,
            'billing_cycle_starts_on' => $this->billing_cycle_starts_on?->format('Y-m-d'),
            'billing_due_days' => $this->billing_due_days,
            'status' => $this->status,
        ];
    }
}
