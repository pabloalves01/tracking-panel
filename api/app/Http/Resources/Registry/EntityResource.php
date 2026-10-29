<?php

namespace App\Http\Resources\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Entity;
use Illuminate\Database\Eloquent\Model;
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

            // Os papéis que a pessoa exerce, cada um com o próprio uuid — é
            // por ele que a tela abre o cadastro daquele papel.
            'roles' => $this->when(
                $this->relationLoaded(EntityRole::Driver->relation()),
                fn () => $this->roleRecords()->map(fn (Model $record) => [
                    'role' => $record->role()->value,
                    'label' => $record->role()->label(),
                    'slug' => $record->role()->slug(),
                    'uuid' => $record->uuid,
                    'status' => $record->status,
                ])
            ),
        ];
    }
}
