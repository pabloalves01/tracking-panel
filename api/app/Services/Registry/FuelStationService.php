<?php

namespace App\Services\Registry;

use App\Models\Registry\FuelStation;
use Illuminate\Support\Facades\DB;
use DomainException;

class FuelStationService
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    /**
     * List all fuelStations
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list()
    {
        return FuelStation::with('entity')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new fuelStation
     *
     * @param array $data
     * @return \App\Models\Registry\FuelStation
     */
    public function store(array $data): FuelStation
    {
        return DB::transaction(function () use ($data) {
            $entity = $this->commonEntityService->findOrCreate($data['entity']);

            $existFuelStation = FuelStation::where('entity_id', $entity->id)->first();

            if ($existFuelStation) {
                throw new DomainException('Já existe um posto com este documento');
            }

            $fuelStation = FuelStation::create([
                'entity_id' => $entity->id,
                ...$this->stationAttributes($data),
                ...$this->passwordAttribute($data),
            ]);

            return $fuelStation->load('entity');
        });
    }

    /**
     * Get a fuelStation by UUID
     *
     * @param string $uuid
     * @return \App\Models\Registry\FuelStation
     */
    public function get(string $uuid): FuelStation
    {
        return FuelStation::where('uuid', $uuid)->with('entity')->firstOrFail();
    }

    /**
     * Update a fuelStation
     *
     * @param string $uuid
     * @param array $data
     * @return \App\Models\Registry\FuelStation
     */
    public function update(string $uuid, array $data): FuelStation
    {
        return DB::transaction(function () use ($uuid, $data) {
            $fuelStation = $this->get($uuid);

            $fuelStation->update([
                ...$this->stationAttributes($data),
                ...$this->passwordAttribute($data),
            ]);

            return $fuelStation;
        });
    }

    /**
     * Deactivate a fuelStation
     *
     * @param string $uuid
     * @return void
     */
    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $fuelStation = FuelStation::where('uuid', $uuid)->firstOrFail();
            $fuelStation->update(['status' => 'inactive']);
        });
    }

    /**
     * Activate a fuelStation
     *
     * @param string $uuid
     * @return void
     */
    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $fuelStation = FuelStation::where('uuid', $uuid)->firstOrFail();
            $fuelStation->update(['status' => 'active']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function stationAttributes(array $data): array
    {
        $attributes = [
            'login' => $data['login'],
            'pix_key_type' => $data['pix_key_type'] ?? null,
            'pix_key' => $data['pix_key'] ?? null,
            'diesel_s10_price' => $data['diesel_s10_price'] ?? null,
            'diesel_s500_price' => $data['diesel_s500_price'] ?? null,
            'gasoline_price' => $data['gasoline_price'] ?? null,
            'additive_gasoline_price' => $data['additive_gasoline_price'] ?? null,
        ];

        // Situação sai do formulário. A lista ainda ativa e desativa.
        // Sem a chave, o create usa o default da coluna e o update mantém a atual.
        if (filled($data['status'] ?? null)) {
            $attributes['status'] = $data['status'];
        }

        return $attributes;
    }

    /**
     * Senha vazia não troca o hash já gravado.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function passwordAttribute(array $data): array
    {
        if (! filled($data['password'] ?? null)) {
            return [];
        }

        return ['password' => $data['password']];
    }
}
