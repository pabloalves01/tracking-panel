<?php

namespace App\Services\Registry;

use App\Models\Registry\Supplier;
use Illuminate\Support\Facades\DB;
use DomainException;

class SupplierService
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    /**
     * List all suppliers
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list()
    {
        return Supplier::with('entity')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new supplier
     *
     * @param array $data
     * @return \App\Models\Registry\Supplier
     */
    public function store(array $data): Supplier
    {
        return DB::transaction(function () use ($data) {
            $entity = $this->commonEntityService->findOrCreate($data['entity']);

            $existSupplier = Supplier::where('entity_id', $entity->id)->first();

            if ($existSupplier) {
                throw new DomainException('Já existe um fornecedor com este documento');
            }

            $supplier = Supplier::create([
                'entity_id' => $entity->id,
                'pix_key_type' => $data['pix_key_type'] ?? null,
                'pix_key' => $data['pix_key'] ?? null,
                'login' => $data['login'] ?? null,
                ...$this->passwordAttribute($data),
            ]);

            return $supplier->load('entity');
        });
    }

    /**
     * Get a supplier by UUID
     *
     * @param string $uuid
     * @return \App\Models\Registry\Supplier
     */
    public function get(string $uuid): Supplier
    {
        return Supplier::where('uuid', $uuid)->with('entity')->firstOrFail();
    }

    /**
     * Update a supplier
     *
     * @param string $uuid
     * @param array $data
     * @return \App\Models\Registry\Supplier
     */
    public function update(string $uuid, array $data): Supplier
    {
        return DB::transaction(function () use ($uuid, $data) {
            $supplier = $this->get($uuid);

            $supplier->update([
                'pix_key_type' => $data['pix_key_type'] ?? null,
                'pix_key' => $data['pix_key'] ?? null,
                'login' => $data['login'] ?? null,
                ...$this->passwordAttribute($data),
            ]);

            return $supplier;
        });
    }

    /**
     * Deactivate a supplier
     *
     * @param string $uuid
     * @return void
     */
    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $supplier = Supplier::where('uuid', $uuid)->firstOrFail();
            $supplier->update(['status' => 'inactive']);
        });
    }

    /**
     * Activate a supplier
     *
     * @param string $uuid
     * @return void
     */
    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $supplier = Supplier::where('uuid', $uuid)->firstOrFail();
            $supplier->update(['status' => 'active']);
        });
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
