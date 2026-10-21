<?php

namespace Tests\Feature\Registry;

use App\Models\Company;
use App\Models\Registry\CommonEntity;
use App\Models\Registry\VehicleOwner;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreVehicleOwnerTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '11111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'proprietário-'.Str::uuid().'@example.com',
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

    public function test_cria_proprietário_quando_o_documento_e_novo(): void
    {
        $this->postJson('/api/registry/vehicle-owners', $this->payload())
            ->assertCreated();

        $this->assertSame(1, CommonEntity::query()->count());
        $this->assertSame(1, VehicleOwner::query()->count());
    }

    public function test_reaproveita_pessoa_existente_sem_alterar_os_dados(): void
    {
        $entity = CommonEntity::query()->create([
            'name' => 'Maria Original',
            'person_type' => 'pf',
            'cpf_cnpj' => self::CPF,
            'email' => 'maria@example.com',
        ]);

        $this->postJson('/api/registry/vehicle-owners', $this->payload([
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
        $this->assertSame(1, CommonEntity::query()->count());
        $this->assertSame(1, VehicleOwner::query()->where('entity_id', $entity->id)->count());
    }

    public function test_proprietário_duplicado_retorna_erro(): void
    {
        $this->postJson('/api/registry/vehicle-owners', $this->payload())->assertCreated();

        $this->postJson('/api/registry/vehicle-owners', $this->payload())
            ->assertConflict()
            ->assertJsonPath('message', 'Já existe um proprietário com este documento');

        $this->assertSame(1, VehicleOwner::query()->count());
        $this->assertSame('Maria Silva', CommonEntity::query()->first()->name);
    }

    public function test_editar_proprietário_atualiza_apenas_a_chave_pix(): void
    {
        $this->postJson('/api/registry/vehicle-owners', $this->payload())->assertCreated();
        $vehicleOwner = VehicleOwner::query()->first();

        $this->putJson("/api/registry/vehicle-owners/{$vehicleOwner->uuid}", [
            'pix_key_type' => 'email',
            'pix_key' => 'maria@example.com',
        ])->assertOk();

        $vehicleOwner->refresh();
        $this->assertSame('Maria Silva', $vehicleOwner->entity->name);
        $this->assertSame('email', $vehicleOwner->pix_key_type);
        $this->assertSame('maria@example.com', $vehicleOwner->pix_key);
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
        ])->assertOk();

        $entity->refresh();
        $this->assertSame('Maria Corrigida', $entity->name);
        $this->assertSame('corrigida@example.com', $entity->email);
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
            'pix_key_type' => 'cpf',
            'pix_key' => self::CPF,
        ], $overrides);
    }
}
