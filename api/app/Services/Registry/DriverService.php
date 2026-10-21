<?php

namespace App\Services\Registry;

use App\Models\Registry\Driver;
use Illuminate\Support\Facades\DB;
use DomainException;

class DriverService
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    /**
     * List all drivers
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list()
    {
        return Driver::with('entity')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new driver
     *
     * @param array $data
     * @return \App\Models\Registry\Driver
     */
    public function store(array $data): Driver
    {
        return DB::transaction(function () use ($data) {
            $entity = $this->commonEntityService->findOrCreate($data['entity']);

            $existDriver = Driver::where('entity_id', $entity->id)->first();

            if ($existDriver) {
                throw new DomainException('Já existe um motorista com este documento');
            }

            $driver = Driver::create([
                'entity_id' => $entity->id,
                ...$this->roleAttributes($data),
            ]);

            return $driver->load('entity');
        });
    }

    /**
     * Get a driver by UUID
     *
     * @param string $uuid
     * @return \App\Models\Registry\Driver
     */
    public function get(string $uuid): Driver
    {
        return Driver::where('uuid', $uuid)->with('entity')->firstOrFail();
    }

    /**
     * Update a driver. Só grava os campos da CNH.
     *
     * @param string $uuid
     * @param array $data
     * @return \App\Models\Registry\Driver
     */
    public function update(string $uuid, array $data): Driver
    {
        return DB::transaction(function () use ($uuid, $data) {
            $driver = $this->get($uuid);

            $driver->update($this->roleAttributes($data));

            return $driver;
        });
    }

    /**
     * Deactivate a driver
     *
     * @param string $uuid
     * @return void
     */
    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $driver = Driver::where('uuid', $uuid)->firstOrFail();
            $driver->update(['status' => 'inactive']);
        });
    }

    /**
     * Activate a driver
     *
     * @param string $uuid
     * @return void
     */
    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $driver = Driver::where('uuid', $uuid)->firstOrFail();
            $driver->update(['status' => 'active']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function roleAttributes(array $data): array
    {
        return [
            'license_number' => $data['license_number'],
            'license_category' => $data['license_category'],
            'license_expires_on' => $data['license_expires_on'] ?? null,
            'license_issued_state' => $data['license_issued_state'] ?? null,
            'residence_proof_path' => $data['residence_proof_path'] ?? null,
        ];
    }
}
