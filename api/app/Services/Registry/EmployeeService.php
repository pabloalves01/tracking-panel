<?php

namespace App\Services\Registry;

use App\Models\Registry\Employee;
use Illuminate\Support\Facades\DB;
use DomainException;

class EmployeeService
{
    public function __construct(private CommonEntityService $commonEntityService) {}

    /**
     * List all employees
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function list()
    {
        return Employee::with('entity')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Create a new employee
     *
     * @param array $data
     * @return \App\Models\Registry\Employee
     */
    public function store(array $data): Employee
    {
        return DB::transaction(function () use ($data) {
            $entity = $this->commonEntityService->findOrCreate($data['entity']);

            $existEmployee = Employee::where('entity_id', $entity->id)->first();

            if ($existEmployee) {
                throw new DomainException('Já existe um funcionário com este documento');
            }

            $employee = Employee::create([
                'entity_id' => $entity->id,
            ]);

            return $employee->load('entity');
        });
    }

    /**
     * Get a employee by UUID
     *
     * @param string $uuid
     * @return \App\Models\Registry\Employee
     */
    public function get(string $uuid): Employee
    {
        return Employee::where('uuid', $uuid)->with('entity')->firstOrFail();
    }

    /**
     * O funcionário não tem coluna própria. A edição não grava nada
     * e não altera a entidade.
     *
     * @param string $uuid
     * @param array $data
     * @return \App\Models\Registry\Employee
     */
    public function update(string $uuid, array $data): Employee
    {
        return $this->get($uuid);
    }

    /**
     * Deactivate a employee
     *
     * @param string $uuid
     * @return void
     */
    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $employee = Employee::where('uuid', $uuid)->firstOrFail();
            $employee->update(['status' => 'inactive']);
        });
    }

    /**
     * Activate a employee
     *
     * @param string $uuid
     * @return void
     */
    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $employee = Employee::where('uuid', $uuid)->firstOrFail();
            $employee->update(['status' => 'active']);
        });
    }
}
