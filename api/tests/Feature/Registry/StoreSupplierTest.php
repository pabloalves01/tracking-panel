<?php

namespace Tests\Feature\Registry;

use App\Models\Company;
use App\Models\Registry\CommonEntity;
use App\Models\Registry\Supplier;
use App\Models\User;
use App\Support\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreSupplierTest extends TestCase
{
    use RefreshDatabase;

    private const CPF = '11111111111';

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'email' => 'fornecedor-'.Str::uuid().'@example.com',
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

    public function test_cria_fornecedor_quando_o_documento_e_novo(): void
    {
        $this->postJson('/api/registry/suppliers', $this->payload())
            ->assertCreated();

        $this->assertSame(1, CommonEntity::query()->count());
        $this->assertSame(1, Supplier::query()->count());
    }

    public function test_reaproveita_pessoa_existente_sem_alterar_os_dados(): void
    {
        $entity = CommonEntity::query()->create([
            'name' => 'Maria Original',
            'person_type' => 'pf',
            'cpf_cnpj' => self::CPF,
            'email' => 'maria@example.com',
        ]);

        $this->postJson('/api/registry/suppliers', $this->payload([
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
        $this->assertSame(1, Supplier::query()->where('entity_id', $entity->id)->count());
    }

    public function test_fornecedor_duplicado_retorna_erro(): void
    {
        $this->postJson('/api/registry/suppliers', $this->payload())->assertCreated();

        $this->postJson('/api/registry/suppliers', $this->payload())
            ->assertConflict()
            ->assertJsonPath('message', 'Já existe um fornecedor com este documento');

        $this->assertSame(1, Supplier::query()->count());
        $this->assertSame('Maria Silva', CommonEntity::query()->first()->name);
    }

    public function test_editar_fornecedor_atualiza_apenas_a_chave_pix(): void
    {
        $this->postJson('/api/registry/suppliers', $this->payload())->assertCreated();
        $supplier = Supplier::query()->first();

        $this->putJson("/api/registry/suppliers/{$supplier->uuid}", [
            'pix_key_type' => 'email',
            'pix_key' => 'maria@example.com',
        ])->assertOk();

        $supplier->refresh();
        $this->assertSame('Maria Silva', $supplier->entity->name);
        $this->assertSame('email', $supplier->pix_key_type);
        $this->assertSame('maria@example.com', $supplier->pix_key);
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

    public function test_senha_e_gravada_com_hash_e_nao_volta_na_resposta(): void
    {
        $this->postJson('/api/registry/suppliers', $this->payload([
            'login' => 'fornecedor',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ]))->assertCreated();

        $supplier = Supplier::query()->first();
        $this->assertTrue(Hash::check('senha1234', $supplier->password));

        $this->getJson("/api/registry/suppliers/{$supplier->uuid}")
            ->assertOk()
            ->assertJsonPath('data.login', 'fornecedor')
            ->assertJsonMissingPath('data.password');

        $this->putJson("/api/registry/suppliers/{$supplier->uuid}", [
            'pix_key_type' => 'email',
            'pix_key' => 'maria@example.com',
            'login' => 'fornecedor',
        ])->assertOk();

        $this->assertTrue(Hash::check('senha1234', $supplier->fresh()->password));
        $this->assertSame('Maria Silva', $supplier->entity->name);
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
