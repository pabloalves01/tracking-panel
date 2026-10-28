<?php

namespace App\Http\Resources\Registry;

use App\Models\Registry\Entity;
use App\Models\Registry\EntityRoleAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A entidade como sai da API. O `id` interno não é exposto; o client
 * identifica pelo `uuid`.
 *
 * @mixin Entity
 */
class EntityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'kind' => $this->kind,
            'document' => $this->document,
            'name' => $this->name,
            'trade_name' => $this->trade_name,

            'email' => $this->email,
            'phone' => $this->phone,
            'emergency_phone' => $this->emergency_phone,
            'emergency_phone_type' => $this->emergency_phone_type,

            'national_id' => $this->national_id,
            'state_registration' => $this->state_registration,

            'pix_key_type' => $this->pix_key_type,
            'pix_key' => $this->pix_key,

            'postal_code' => $this->postal_code,
            'street' => $this->street,
            'street_number' => $this->street_number,
            'complement' => $this->complement,
            'neighborhood' => $this->neighborhood,
            'state_id' => $this->state_id,
            'city_id' => $this->city_id,

            // Os papéis que a pessoa exerce. É o que permite a tela avisar
            // "este motorista também é proprietário" — informação que já
            // existia no banco e não aparecia em lugar nenhum.
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn (EntityRoleAssignment $assignment) => [
                'role' => $assignment->role->value,
                'label' => $assignment->role->label(),
                'slug' => $assignment->role->slug(),
                'status' => $assignment->status,
            ])->values()),

            'driver' => $this->whenLoaded('driverDetails', fn () => $this->driverDetails ? [
                'license_number' => $this->driverDetails->license_number,
                'license_category' => $this->driverDetails->license_category,
                'license_expires_on' => $this->driverDetails->license_expires_on?->toDateString(),
                'license_issued_state' => $this->driverDetails->license_issued_state,
                'residence_proof_path' => $this->driverDetails->residence_proof_path,
            ] : null),
        ];
    }
}
