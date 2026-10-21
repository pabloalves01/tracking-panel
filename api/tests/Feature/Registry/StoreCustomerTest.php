<?php

namespace Tests\Feature\Registry;

use App\Models\Company;
use App\Models\Registry\CommonEntity;
use App\Models\Registry\Customer;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreCustomerTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '11111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'cliente-'.Str::uuid().'@example.com',
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

    public function test_cria_cliente_quando_o_documento_e_novo(): void
    {
        $this->postJson('/api/registry/customers', $this->payload([
            'entity' => [
                'rg_ie' => '1234567',
                'emergency_phone' => '(11) 99999-8888',
                'emergency_phone_type' => 'mobile',
            ],
        ]))->assertCreated();

        $entity = CommonEntity::query()->first();
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('1234567', $entity->rg_ie);
        $this->assertSame('11999998888', $entity->emergency_phone);
        $this->assertSame('mobile', $entity->emergency_phone_type);

        $customer = Customer::query()->first();
        $this->getJson("/api/registry/customers/{$customer->uuid}")
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

        $this->postJson('/api/registry/customers', $this->payload([
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
        $this->assertSame(1, Customer::query()->where('entity_id', $entity->id)->count());
    }

    public function test_cliente_duplicado_retorna_erro(): void
    {
        $this->postJson('/api/registry/customers', $this->payload())->assertCreated();

        $this->postJson('/api/registry/customers', $this->payload())
            ->assertConflict()
            ->assertJsonPath('message', 'Já existe um cliente com este documento');

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('Maria Silva', CommonEntity::query()->first()->name);
    }

    public function test_editar_cliente_nao_altera_a_entidade(): void
    {
        $this->postJson('/api/registry/customers', $this->payload())->assertCreated();
        $customer = Customer::query()->first();

        $this->putJson("/api/registry/customers/{$customer->uuid}", [
            'entity' => [
                'name' => 'Nome Alterado',
                'email' => 'outro@example.com',
            ],
        ])->assertOk();

        $customer->refresh();
        $this->assertSame('Maria Silva', $customer->entity->name);
        $this->assertNull($customer->entity->email);
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
