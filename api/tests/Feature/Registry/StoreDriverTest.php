<?php

namespace Tests\Feature\Registry;

use App\Models\Company;
use App\Models\Registry\CommonEntity;
use App\Models\Registry\Driver;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreDriverTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '11111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'motorista-'.Str::uuid().'@example.com',
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

    public function test_cria_motorista_quando_o_documento_e_novo(): void
    {
        $this->postJson('/api/registry/drivers', $this->payload([
            'entity' => [
                'document_file_path' => 'documentos/cnh.pdf',
            ],
            'residence_proof_path' => 'documentos/residencia.pdf',
            'license_issued_state' => 'sp',
        ]))->assertCreated();

        $driver = Driver::query()->first();
        $this->assertSame(1, CommonEntity::query()->count());
        $this->assertSame('documentos/cnh.pdf', $driver->entity->document_file_path);
        $this->assertSame('12345678900', $driver->license_number);
        $this->assertSame('AB', $driver->license_category);
        $this->assertSame('2030-01-15', $driver->license_expires_on->format('Y-m-d'));
        $this->assertSame('SP', $driver->license_issued_state);
        $this->assertSame('documentos/residencia.pdf', $driver->residence_proof_path);

        $this->getJson("/api/registry/drivers/{$driver->uuid}")
            ->assertOk()
            ->assertJsonPath('data.license_number', '12345678900');
    }

    public function test_reaproveita_pessoa_existente_sem_alterar_os_dados(): void
    {
        $entity = CommonEntity::query()->create([
            'name' => 'Maria Original',
            'person_type' => 'pf',
            'cpf_cnpj' => self::CPF,
            'email' => 'maria@example.com',
        ]);

        $this->postJson('/api/registry/drivers', $this->payload([
            'entity' => [
                'name' => 'Nome Sobrescrito',
                'person_type' => 'pf',
                'cpf_cnpj' => self::CPF,
                'email' => 'outro@example.com',
            ],
        ]))->assertCreated();

        $entity->refresh();
        $this->assertSame('Maria Original', $entity->name);
        $this->assertSame('maria@example.com', $entity->email);
        $this->assertSame(1, Driver::query()->where('entity_id', $entity->id)->count());
    }

    public function test_motorista_duplicado_retorna_erro(): void
    {
        $this->postJson('/api/registry/drivers', $this->payload())->assertCreated();

        $this->postJson('/api/registry/drivers', $this->payload())
            ->assertConflict()
            ->assertJsonPath('message', 'Já existe um motorista com este documento');

        $this->assertSame(1, Driver::query()->count());
        $this->assertSame('Maria Silva', CommonEntity::query()->first()->name);
    }

    public function test_editar_motorista_atualiza_apenas_a_cnh(): void
    {
        $this->postJson('/api/registry/drivers', $this->payload())->assertCreated();
        $driver = Driver::query()->first();

        $this->putJson("/api/registry/drivers/{$driver->uuid}", [
            'license_number' => '999',
            'license_category' => 'E',
            'license_expires_on' => '2031-02-02',
            'license_issued_state' => 'RJ',
            'entity' => [
                'name' => 'Nome Alterado',
            ],
        ])->assertOk();

        $driver->refresh();
        $this->assertSame('Maria Silva', $driver->entity->name);
        $this->assertSame('999', $driver->license_number);
        $this->assertSame('E', $driver->license_category);
        $this->assertSame('2031-02-02', $driver->license_expires_on->format('Y-m-d'));
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
            'document_file_path' => 'documentos/rg.pdf',
        ])->assertOk();

        $entity->refresh();
        $this->assertSame('Maria Corrigida', $entity->name);
        $this->assertSame('documentos/rg.pdf', $entity->document_file_path);
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
            'license_number' => '12345678900',
            'license_category' => 'AB',
            'license_expires_on' => '2030-01-15',
        ], $overrides);
    }
}
