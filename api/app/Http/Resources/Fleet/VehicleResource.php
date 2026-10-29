<?php

namespace App\Http\Resources\Fleet;

use App\Models\Fleet\DeviceInstallation;
use App\Models\Fleet\Vehicle;
use App\Models\Fleet\VehicleCoupling;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'kind' => $this->kind()->value,
            'type' => VehicleTypeResource::make($this->vehicleType),
            'body_type' => BodyTypeResource::make($this->whenLoaded('bodyType')),
            'fleet_type' => $this->fleet_type->value,
            'plate' => $this->plate,
            'plate_state' => $this->plate_state,
            'renavam' => $this->renavam,
            'chassis' => $this->chassis,
            'registration_expires_on' => $this->registration_expires_on?->toDateString(),
            'brand' => $this->whenLoaded('vehicleBrand', fn () => ['id' => $this->vehicleBrand->id, 'name' => $this->vehicleBrand->name]),
            'model' => $this->whenLoaded('vehicleModel', fn () => ['id' => $this->vehicleModel->id, 'name' => $this->vehicleModel->name]),
            'color' => $this->color,
            'manufacture_year' => $this->manufacture_year,
            'model_year' => $this->model_year,
            'axle_count' => $this->axle_count,
            'tare_weight_kg' => $this->tare_weight_kg,
            'load_capacity_kg' => $this->load_capacity_kg,
            'gross_weight_kg' => $this->gross_weight_kg,
            'max_traction_weight_kg' => $this->max_traction_weight_kg,
            'volume_capacity_m3' => $this->volume_capacity_m3 === null ? null : (float) $this->volume_capacity_m3,
            'status' => $this->status,
            'owner' => $this->whenLoaded('owner', fn () => $this->person($this->owner)),
            'carrier' => $this->whenLoaded('carrier', fn () => $this->person($this->carrier)),
            'responsible' => $this->whenLoaded('responsible', fn () => $this->person($this->responsible)),
            'trailers' => $this->whenLoaded('trailerCouplings', fn () => $this->trailerCouplings->map(
                fn (VehicleCoupling $coupling) => [
                    'position' => $coupling->position,
                    'coupled_at' => $coupling->coupled_at->toIso8601String(),
                    ...$this->summary($coupling->trailer),
                ]
            )),
            'traction' => $this->whenLoaded('tractionCoupling', fn () => $this->tractionCoupling ? [
                'position' => $this->tractionCoupling->position,
                ...$this->summary($this->tractionCoupling->traction),
            ] : null),
            'devices' => $this->whenLoaded('installations', fn () => $this->installations->map(
                fn (DeviceInstallation $installation) => [
                    'uuid' => $installation->device->uuid,
                    'imei' => $installation->device->imei,
                    'model' => $installation->device->deviceModel?->label(),
                    'role' => $installation->role->value,
                    'installed_at' => $installation->installed_at->toIso8601String(),
                ]
            )),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * Proprietário, transportador ou responsável: o `uuid` do papel e a
     * pessoa. A chave PIX vem do cadastro da pessoa — é para a do
     * responsável que vão os pagamentos do veículo —, não é repetida aqui.
     *
     * @return array<string, mixed>|null
     */
    private function person(?Model $role): ?array
    {
        if ($role === null) {
            return null;
        }

        return [
            'uuid' => $role->uuid,
            'name' => $role->entity?->name,
            'document' => $role->entity?->document,
            'pix_key_type' => $role->entity?->pix_key_type,
            'pix_key' => $role->entity?->pix_key,
        ];
    }

    /** @return array<string, mixed> */
    private function summary(Vehicle $vehicle): array
    {
        return [
            'uuid' => $vehicle->uuid,
            'plate' => $vehicle->plate,
            'type' => $vehicle->vehicleType->name,
        ];
    }
}
