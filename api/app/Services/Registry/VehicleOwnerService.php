<?php

namespace App\Services\Registry;

use App\Models\Registry\VehicleOwner;
use Illuminate\Support\Facades\DB;
use DomainException;

class VehicleOwnerService
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    /**
     * List all vehicleOwners
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list()
    {
        return VehicleOwner::with('entity')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new vehicleOwner
     *
     * @param array $data
     * @return \App\Models\Registry\VehicleOwner
     */
    public function store(array $data): VehicleOwner
    {
        return DB::transaction(function () use ($data) {
            $entity = $this->commonEntityService->findOrCreate($data['entity']);

            $existVehicleOwner = VehicleOwner::where('entity_id', $entity->id)->first();

            if ($existVehicleOwner) {
                throw new DomainException('Já existe um proprietário com este documento');
            }

            $vehicleOwner = VehicleOwner::create([
                'entity_id' => $entity->id,
                'pix_key_type' => $data['pix_key_type'],
                'pix_key' => $data['pix_key'],
            ]);

            return $vehicleOwner->load('entity');
        });
    }

    /**
     * Get a vehicleOwner by UUID
     *
     * @param string $uuid
     * @return \App\Models\Registry\VehicleOwner
     */
    public function get(string $uuid): VehicleOwner
    {
        return VehicleOwner::where('uuid', $uuid)->with('entity')->firstOrFail();
    }

    /**
     * Update a vehicleOwner
     *
     * @param string $uuid
     * @param array $data
     * @return \App\Models\Registry\VehicleOwner
     */
    public function update(string $uuid, array $data): VehicleOwner
    {
        return DB::transaction(function () use ($uuid, $data) {
            $vehicleOwner = $this->get($uuid);

            $vehicleOwner->update([
                'pix_key_type' => $data['pix_key_type'],
                'pix_key' => $data['pix_key'],
            ]);

            return $vehicleOwner;
        });
    }

    /**
     * Deactivate a vehicleOwner
     *
     * @param string $uuid
     * @return void
     */
    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $vehicleOwner = VehicleOwner::where('uuid', $uuid)->firstOrFail();
            $vehicleOwner->update(['status' => 'inactive']);
        });
    }

    /**
     * Activate a vehicleOwner
     *
     * @param string $uuid
     * @return void
     */
    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $vehicleOwner = VehicleOwner::where('uuid', $uuid)->firstOrFail();
            $vehicleOwner->update(['status' => 'active']);
        });
    }

}
