<?php

namespace App\Services\Registry;

use App\Models\Registry\Customer;
use Illuminate\Support\Facades\DB;
use DomainException;

class CustomerService
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    /**
     * List all customers
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list()
    {
        return Customer::with('entity')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new customer
     *
     * @param array $data
     * @return \App\Models\Registry\Customer
     */
    public function store(array $data): Customer
    {
        return DB::transaction(function () use ($data) {
            $entity = $this->commonEntityService->findOrCreate($data['entity']);

            $existCustomer = Customer::where('entity_id', $entity->id)->first();

            if ($existCustomer) {
                throw new DomainException('Já existe um cliente com este documento');
            }

            $customer = Customer::create([
                'entity_id' => $entity->id,
            ]);

            return $customer->load('entity');
        });
    }

    /**
     * Get a customer by UUID
     *
     * @param string $uuid
     * @return \App\Models\Registry\Customer
     */
    public function get(string $uuid): Customer
    {
        return Customer::where('uuid', $uuid)->with('entity')->firstOrFail();
    }

    /**
     * O cliente não tem coluna própria. A edição não grava nada
     * e não altera a entidade.
     *
     * @param string $uuid
     * @param array $data
     * @return \App\Models\Registry\Customer
     */
    public function update(string $uuid, array $data): Customer
    {
        return $this->get($uuid);
    }

    /**
     * Deactivate a customer
     *
     * @param string $uuid
     * @return void
     */
    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $customer = Customer::where('uuid', $uuid)->firstOrFail();
            $customer->update(['status' => 'inactive']);
        });
    }

    /**
     * Activate a customer
     *
     * @param string $uuid
     * @return void
     */
    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $customer = Customer::where('uuid', $uuid)->firstOrFail();
            $customer->update(['status' => 'active']);
        });
    }
}
