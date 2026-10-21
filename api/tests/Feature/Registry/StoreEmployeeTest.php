<?php

namespace Tests\Feature\Registry;

use App\Models\Company;
use App\Models\Registry\CommonEntity;
use App\Models\Registry\Employee;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreEmployeeTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '11111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'funcionário-'.Str::uuid().'@example.com',
        ]);

        $company = Company::query()->create([
            'legal_name' => 'Empresa Teste LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => str_pad((string) random_int(1, 99999999999999), 14, '0', STR_PAD_LEFT),
        ]);

        $user->companies()->attach($company->id, ['role' => 'admin']);

        $token = $user->createToken('web');
        $token->accessToken->forceFill(['company_id' => $company->id])->save();
        $this->withToken($token->plainTextToken);

        app()->instance(CurrentCompany::class, new CurrentCompany($company->id));
    }

    public function test_cria_funcionário_quando_o_documento_e_novo(): void
    {
        $this->postJson('/api/registry/employees', $this->payload([
            'entity' => [
                'rg_ie' => '1234567',
                'emergency_phone' => '(11) 99999-8888',
                'emergency_phone_type' => 'mobile',
            ],
        ]))->assertCreated();

        $entity = CommonEntity::query()->first();
        $this->assertSame(1, Employee::query()->count());
        $this->assertSame('1234567', $entity->rg_ie);
        $this->assertSame('11999998888', $entity->emergency_phone);
        $this->assertSame('mobile', $entity->emergency_phone_type);

        $employee = Employee::query()->first();
        $this->getJson("/api/registry/employees/{$employee->uuid}")
            ->assertOk()
            ->assertJsonPath('data.rg_ie', '1234567');
    }

    public function test_reaproveita_pessoa_existente_sem_alterar_os_dados(): void
    {
        $entity = CommonEntity::query()->create([
            'name' => 'Maria Original',
            'person_type' => 'pf',
            'cpf_cnpj' => self::CPF,
            'email' => 'maria@example.com',
            'rg_ie' => '111',
        ]);

        $this->postJson('/api/registry/employees', $this->payload([
            'entity' => [
                'name' => 'Nome Sobrescrito',
                'person_type' => 'pf',
                'cpf_cnpj' => self::CPF,
                'email' => 'outro@example.com',
                'rg_ie' => '999',
            ],
        ]))->assertCreated();

        $entity->refresh();
        $this->assertSame('Maria Original', $entity->name);
        $this->assertSame('maria@example.com', $entity->email);
        $this->assertSame('111', $entity->rg_ie);
        $this->assertSame(1, CommonEntity::query()->count());
        $this->assertSame(1, Employee::query()->where('entity_id', $entity->id)->count());
    }

    public function test_funcionário_duplicado_retorna_erro(): void
    {
        $this->postJson('/api/registry/employees', $this->payload())->assertCreated();

        $this->postJson('/api/registry/employees', $this->payload())
            ->assertConflict()
            ->assertJsonPath('message', 'Já existe um funcionário com este documento');

        $this->assertSame(1, Employee::query()->count());
        $this->assertSame('Maria Silva', CommonEntity::query()->first()->name);
    }

    public function test_editar_funcionário_nao_altera_a_entidade(): void
    {
        $this->postJson('/api/registry/employees', $this->payload())->assertCreated();
        $employee = Employee::query()->first();

        $this->putJson("/api/registry/employees/{$employee->uuid}", [
            'entity' => [
                'name' => 'Nome Alterado',
                'email' => 'outro@example.com',
            ],
        ])->assertOk();

        $employee->refresh();
        $this->assertSame('Maria Silva', $employee->entity->name);
        $this->assertNull($employee->entity->email);
    }

    public function test_editar_entidade_atualiza_os_dados_compartilhados(): void
    {
        $entity = CommonEntity::query()->create([
            'name' => 'Maria Original',
            'person_type' => 'pf',
            'cpf_cnpj' => self::CPF,
            'email' => 'maria@example.com',
        ]);

        $this->putJson("/api/registry/entities/{$entity->uuid}", [
            'name' => 'Maria Corrigida',
            'person_type' => 'pf',
            'cpf_cnpj' => self::CPF,
            'email' => 'corrigida@example.com',
            'rg_ie' => '445566',
            'emergency_phone' => '11988887777',
            'emergency_phone_type' => 'home',
        ])->assertOk();

        $entity->refresh();
        $this->assertSame('Maria Corrigida', $entity->name);
        $this->assertSame('corrigida@example.com', $entity->email);
        $this->assertSame('445566', $entity->rg_ie);
        $this->assertSame('11988887777', $entity->emergency_phone);
        $this->assertSame('home', $entity->emergency_phone_type);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace_recursive([
            'entity' => [
                'name' => 'Maria Silva',
                'person_type' => 'pf',
                'cpf_cnpj' => self::CPF,
            ],
        ], $overrides);
    }
}
