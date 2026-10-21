<?php

namespace App\Services\Registry;

use App\Models\Registry\Carrier;
use Illuminate\Support\Facades\DB;
use DomainException;

class CarrierService
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    /**
     * List all carriers
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list()
    {
        return Carrier::with('entity')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new carrier
     *
     * @param array $data
     * @return \App\Models\Registry\Carrier
     */
    public function store(array $data): Carrier
    {
        return DB::transaction(function () use ($data) {
            $entity = $this->commonEntityService->findOrCreate($data['entity']);

            $existCarrier = Carrier::where('entity_id', $entity->id)->first();

            if ($existCarrier) {
                throw new DomainException('Já existe uma transportadora com este documento');
            }

            $carrier = Carrier::create([
                'entity_id' => $entity->id,
            ]);

            return $carrier->load('entity');
        });
    }

    /**
     * Get a carrier by UUID
     *
     * @param string $uuid
     * @return \App\Models\Registry\Carrier
     */
    public function get(string $uuid): Carrier
    {
        return Carrier::where('uuid', $uuid)->with('entity')->firstOrFail();
    }

    /**
     * A transportadora não tem coluna própria. A edição não grava nada
     * e não altera a entidade.
     *
     * @param string $uuid
     * @param array $data
     * @return \App\Models\Registry\Carrier
     */
    public function update(string $uuid, array $data): Carrier
    {
        return $this->get($uuid);
    }

    /**
     * Deactivate a carrier
     *
     * @param string $uuid
     * @return void
     */
    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $carrier = Carrier::where('uuid', $uuid)->firstOrFail();
            $carrier->update(['status' => 'inactive']);
        });
    }

    /**
     * Activate a carrier
     *
     * @param string $uuid
     * @return void
     */
    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $carrier = Carrier::where('uuid', $uuid)->firstOrFail();
            $carrier->update(['status' => 'active']);
        });
    }
}
