<?php

namespace App\Services\Registry;

use App\Models\Registry\Supplier;
use Illuminate\Support\Facades\DB;

class SupplierService
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    public function list()
    {
        return Supplier::with('entity')->orderBy('created_at', 'desc')->get();
    }

    public function store(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $entity = $this->commonEntityService->findOrCreate($data['entity']);

            $supplier = Supplier::firstOrCreate(
                ['entity_id' => $entity->id],
                [
                    'pix_key_type' => $data['pix_key_type'] ?? null,
                    'pix_key' => $data['pix_key'] ?? null,
                ]
            );

            return $supplier->load('entity');
        });
    }

    public function get(string $uuid): Supplier
    {
        return Supplier::where('uuid', $uuid)->with('entity')->firstOrFail();
    }

    public function update(string $uuid, array $data): Supplier
    {
        return DB::transaction(function () use ($uuid, $data) {
            $supplier = $this->get($uuid);

            $supplier->entity->update($data['entity']);
            $supplier->update([
                'pix_key_type' => $data['pix_key_type'] ?? null,
                'pix_key' => $data['pix_key'] ?? null,
            ]);

            return $supplier;
        });
    }

    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $supplier = Supplier::where('uuid', $uuid)->firstOrFail();
            $supplier->update(['status' => 'inactive']);
        });
    }

    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $supplier = Supplier::where('uuid', $uuid)->firstOrFail();
            $supplier->update(['status' => 'active']);
        });
    }
}
