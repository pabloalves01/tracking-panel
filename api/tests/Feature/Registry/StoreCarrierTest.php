<?php

namespace Tests\Feature\Registry;

use App\Models\Company;
use App\Models\Registry\Carrier;
use App\Models\Registry\CommonEntity;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreCarrierTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '52998224725';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'transportadora-'.Str::uuid().'@example.com',
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

    public function test_cria_transportadora_quando_o_documento_e_novo(): void
    {
        $this->postJson('/api/registry/carriers', $this->payload([
            'entity' => [
                'rg' => '1234567',
                'emergency_phone' => '(11) 99999-8888',
                'emergency_phone_type' => 'mobile',
            ],
        ]))->assertCreated();

        $entity = CommonEntity::query()->first();
        $this->assertSame(1, Carrier::query()->count());
        $this->assertSame('1234567', $entity->rg);
        $this->assertSame('11999998888', $entity->emergency_phone);
        $this->assertSame('mobile', $entity->emergency_phone_type);

        $carrier = Carrier::query()->first();
        $this->getJson("/api/registry/carriers/{$carrier->uuid}")
            ->assertOk()
            ->assertJsonPath('data.rg', '1234567');
    }

    public function test_reaproveita_pessoa_existente_sem_alterar_os_dados(): void
    {
        $entity = CommonEntity::query()->create([
            'name' => 'Maria Original',
            'person_type' => 'pf',
            'cpf_cnpj' => self::CPF,
            'email' => 'maria@example.com',
            'rg' => '111',
        ]);

        $this->postJson('/api/registry/carriers', $this->payload([
            'entity' => [
                'name' => 'Nome Sobrescrito',
                'person_type' => 'pf',
                'cpf_cnpj' => self::CPF,
                'email' => 'outro@example.com',
                'rg' => '999',
            ],
        ]))->assertCreated();

        $entity->refresh();
        $this->assertSame('Maria Original', $entity->name);
        $this->assertSame('maria@example.com', $entity->email);
        $this->assertSame('111', $entity->rg);
        $this->assertSame(1, CommonEntity::query()->count());
        $this->assertSame(1, Carrier::query()->where('entity_id', $entity->id)->count());
    }

    public function test_transportadora_duplicada_retorna_erro(): void
    {
        $this->postJson('/api/registry/carriers', $this->payload())->assertCreated();

        $this->postJson('/api/registry/carriers', $this->payload())
            ->assertConflict()
            ->assertJsonPath('message', 'Já existe uma transportadora com este documento');

        $this->assertSame(1, Carrier::query()->count());
        $this->assertSame('Maria Silva', CommonEntity::query()->first()->name);
    }

    public function test_editar_transportadora_nao_altera_a_entidade(): void
    {
        $this->postJson('/api/registry/carriers', $this->payload())->assertCreated();
        $carrier = Carrier::query()->first();

        $this->putJson("/api/registry/carriers/{$carrier->uuid}", [
            'entity' => [
                'name' => 'Nome Alterado',
                'email' => 'outro@example.com',
            ],
        ])->assertOk();

        $carrier->refresh();
        $this->assertSame('Maria Silva', $carrier->entity->name);
        $this->assertNull($carrier->entity->email);
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
            'rg' => '445566',
            'emergency_phone' => '11988887777',
            'emergency_phone_type' => 'home',
        ])->assertOk();

        $entity->refresh();
        $this->assertSame('Maria Corrigida', $entity->name);
        $this->assertSame('corrigida@example.com', $entity->email);
        $this->assertSame('445566', $entity->rg);
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
