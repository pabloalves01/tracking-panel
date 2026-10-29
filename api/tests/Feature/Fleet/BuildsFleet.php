<?php

namespace Tests\Feature\Fleet;

use App\Models\Fleet\BodyType;
use App\Models\Fleet\DeviceModel;
use App\Models\Fleet\VehicleType;

/**
 * Montagem de cadastro para os testes de frota: proprietário, veículo e
 * rastreador pela API, como a tela faz.
 */
trait BuildsFleet
{
    private int $documents = 0;

    protected function owner(string $name = 'Transportes Silva'): string
    {
        return $this->postJson('/api/registry/vehicle-owners', [
            'kind' => 'individual',
            'document' => $this->cpf(),
            'name' => $name,
        ])->assertCreated()->json('data.uuid');
    }

    protected function carrier(string $name = 'Transportes Lima', array $antt = []): string
    {
        return $this->postJson('/api/registry/carriers', [
            'kind' => 'individual',
            'document' => $this->cpf(),
            'name' => $name,
            ...$antt,
        ])->assertCreated()->json('data.uuid');
    }

    protected function responsible(string $name = 'Responsável Souza'): string
    {
        return $this->postJson('/api/registry/vehicle-responsibles', [
            'kind' => 'individual',
            'document' => $this->cpf(),
            'name' => $name,
        ])->assertCreated()->json('data.uuid');
    }

    /** @return array<string, mixed> */
    protected function traction(array $overrides = []): array
    {
        return [
            'vehicle_type_id' => $this->typeId('truck_tractor'),
            'body_type_id' => null,
            'fleet_type' => 'own',
            'plate' => 'ABC1D23',
            'plate_state' => 'sp',
            'renavam' => $this->renavam(1),
            'chassis' => '9BSR6X40003901234',
            'registration_expires_on' => '2027-03-31',
            'brand' => 'Scania',
            'model' => 'R 450',
            'color' => 'Branco',
            'manufacture_year' => 2022,
            'model_year' => 2023,
            'axle_count' => 3,
            'tare_weight_kg' => 9200,
            'max_traction_weight_kg' => 74000,
            'owner' => $overrides['owner'] ?? $this->owner(),
            'responsible' => $overrides['responsible'] ?? $this->responsible(),
            ...$overrides,
        ];
    }

    /** @return array<string, mixed> */
    protected function trailer(string $plate, array $overrides = []): array
    {
        return $this->traction([
            'vehicle_type_id' => $this->typeId('semi_trailer'),
            'body_type_id' => $this->bodyId('bulk'),
            'plate' => $plate,
            'renavam' => $this->renavam(crc32($plate) % 1000000),
            'chassis' => '9EP07123450001234',
            'brand' => 'Randon',
            'model' => 'Graneleiro',
            'tare_weight_kg' => 7400,
            'load_capacity_kg' => 32000,
            ...$overrides,
        ]);
    }

    protected function typeId(string $slug): int
    {
        return VehicleType::query()->where('slug', $slug)->value('id');
    }

    protected function bodyId(string $slug): int
    {
        return BodyType::query()->where('slug', $slug)->value('id');
    }

    /** @return array{device: string, role: string} */
    protected function primary(string $device): array
    {
        return ['device' => $device, 'role' => 'primary'];
    }

    /** @return array{device: string, role: string} */
    protected function backup(string $device): array
    {
        return ['device' => $device, 'role' => 'backup'];
    }

    protected function createVehicle(array $payload): string
    {
        return $this->postJson('/api/fleet/vehicles', $payload)->assertCreated()->json('data.uuid');
    }

    protected function createDevice(string $imei = '358899051234567', array $overrides = []): string
    {
        return $this->postJson('/api/fleet/devices', [
            'device_model_id' => DeviceModel::query()->where('name', 'N4')->value('id'),
            'imei' => $imei,
            'sim_number' => '(11) 98888-7777',
            'sim_iccid' => '8955 0312 3456 7890 123',
            ...$overrides,
        ])->assertCreated()->json('data.uuid');
    }

    /** Um RENAVAM válido a partir de uma semente. */
    protected function renavam(int $seed): string
    {
        $base = str_pad((string) $seed, 10, '0', STR_PAD_LEFT);
        $sum = 0;

        foreach ([3, 2, 9, 8, 7, 6, 5, 4, 3, 2] as $index => $weight) {
            $sum += (int) $base[$index] * $weight;
        }

        $check = 11 - ($sum % 11);

        return $base.($check > 9 ? 0 : $check);
    }

    private function cpf(): string
    {
        $digits = str_split(str_pad((string) (100000000 + ++$this->documents * 7919), 9, '0', STR_PAD_LEFT));

        foreach ([9, 10] as $length) {
            $sum = 0;
            foreach (array_slice($digits, 0, $length) as $index => $digit) {
                $sum += (int) $digit * ($length + 1 - $index);
            }
            $rest = $sum % 11;
            $digits[] = (string) ($rest < 2 ? 0 : 11 - $rest);
        }

        return implode('', $digits);
    }
}
