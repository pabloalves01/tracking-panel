<?php

namespace App\Services\Registry;

use App\Models\Registry\VehicleResponsible;
use Illuminate\Support\Facades\DB;
use DomainException;

class VehicleResponsibleService
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    /**
     * List all vehicleResponsibles
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list()
    {
        return VehicleResponsible::with('entity')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new vehicleResponsible
     *
     * @param array $data
     * @return \App\Models\Registry\VehicleResponsible
     */
    public function store(array $data): VehicleResponsible
    {
        return DB::transaction(function () use ($data) {
            $entity = $this->commonEntityService->findOrCreate($data['entity']);

            $existVehicleResponsible = VehicleResponsible::where('entity_id', $entity->id)->first();

            if ($existVehicleResponsible) {
                throw new DomainException('Já existe um responsável com este documento');
            }

            $vehicleResponsible = VehicleResponsible::create([
                'entity_id' => $entity->id,
                'pix_key_type' => $data['pix_key_type'],
                'pix_key' => $data['pix_key'],
            ]);

            return $vehicleResponsible->load('entity');
        });
    }

    /**
     * Get a vehicleResponsible by UUID
     *
     * @param string $uuid
     * @return \App\Models\Registry\VehicleResponsible
     */
    public function get(string $uuid): VehicleResponsible
    {
        return VehicleResponsible::where('uuid', $uuid)->with('entity')->firstOrFail();
    }

    /**
     * Update a vehicleResponsible
     *
     * @param string $uuid
     * @param array $data
     * @return \App\Models\Registry\VehicleResponsible
     */
    public function update(string $uuid, array $data): VehicleResponsible
    {
        return DB::transaction(function () use ($uuid, $data) {
            $vehicleResponsible = $this->get($uuid);

            $vehicleResponsible->update([
                'pix_key_type' => $data['pix_key_type'],
                'pix_key' => $data['pix_key'],
            ]);

            return $vehicleResponsible;
        });
    }

    /**
     * Deactivate a vehicleResponsible
     *
     * @param string $uuid
     * @return void
     */
    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $vehicleResponsible = VehicleResponsible::where('uuid', $uuid)->firstOrFail();
            $vehicleResponsible->update(['status' => 'inactive']);
        });
    }

    /**
     * Activate a vehicleResponsible
     *
     * @param string $uuid
     * @return void
     */
    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $vehicleResponsible = VehicleResponsible::where('uuid', $uuid)->firstOrFail();
            $vehicleResponsible->update(['status' => 'active']);
        });
    }

}
