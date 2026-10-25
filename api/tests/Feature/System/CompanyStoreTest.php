<?php

namespace Tests\Feature\System;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\FuelStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'admin@logiv.com.br',
            'is_system_admin' => true,
        ]);
    }

    public function test_cria_empresa_vinculando_uma_conta_existente_como_admin(): void
    {
        $owner = $this->owner();

        $this->asAdmin()->postJson('/api/system/companies', $this->payload())
            ->assertCreated()
            ->assertJsonPath('message', 'Empresa criada com sucesso');

        $company = Company::query()->sole();
        $this->assertSame(2, User::query()->count());
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $owner->id,
            'role' => 'admin',
            'deleted_at' => null,
        ]);

        // O admin do sistema continua acompanhando toda empresa criada.
        $this->assertDatabaseHas('company_user', [
            'company_id' => $company->id,
            'user_id' => $this->admin->id,
            'role' => 'admin',
        ]);
        $this->assertSame(2, CompanyUser::query()->count());

        $this->getJson('/api/system/companies')
            ->assertOk()
            ->assertJsonPath('data.0.users_count', 1);
    }

    public function test_conta_vinculada_entra_na_empresa_no_login(): void
    {
        $owner = $this->owner();

        $this->asAdmin()->postJson('/api/system/companies', $this->payload())->assertCreated();

        // Volta para o guard padrão: o login usa a sessão, não o Sanctum.
        $this->app['auth']->forgetGuards();
        $this->app['auth']->setDefaultDriver('web');

        $this->postJson('/api/auth/login', [
            'email' => $owner->email,
            'password' => 'senha1234',
        ])->assertOk()
            ->assertJsonCount(1, 'companies')
            ->assertJsonPath('user.has_company', true)
            ->assertJsonPath('user.current_company.cnpj', '11222333000181');
    }

    public function test_conta_com_posto_passa_a_escolher_entre_posto_e_empresa(): void
    {
        $owner = $this->owner();
        $station = FuelStation::query()->create([
            'cnpj' => '19131243000197',
            'corporate_name' => 'Posto Teste LTDA',
            'status' => 'active',
        ]);
        $owner->fuelStations()->attach($station->id, ['role' => 'owner']);
        $token = $owner->createToken('web');
        $token->accessToken->forceFill(['fuel_station_id' => $station->id])->save();

        $this->asAdmin()->postJson('/api/system/companies', $this->payload())->assertCreated();
        $this->app['auth']->forgetGuards();

        $company = Company::query()->sole();

        $this->withToken($token->plainTextToken)->getJson('/api/auth/contexts')
            ->assertOk()
            ->assertJsonPath('companies.0.uuid', $company->uuid)
            ->assertJsonPath('fuel_stations.0.uuid', $station->uuid);

        $this->withToken($token->plainTextToken)->postJson('/api/auth/company', ['uuid' => $company->uuid])
            ->assertOk();

        $this->withToken($token->plainTextToken)->getJson('/api/registry/entities')->assertOk();
    }

    public function test_email_em_maiusculas_encontra_a_conta(): void
    {
        $owner = $this->owner();

        $this->asAdmin()->postJson('/api/system/companies', $this->payload([
            'user_email' => ' Pablo@LOGIV.com.br ',
        ]))->assertCreated();

        $this->assertSame($owner->id, CompanyUser::query()->where('user_id', '!=', $this->admin->id)->sole()->user_id);
    }

    public function test_email_sem_conta_nao_cria_empresa_nem_usuario(): void
    {
        $this->asAdmin()->postJson('/api/system/companies', $this->payload([
            'user_email' => 'ninguem@logiv.com.br',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors([
                'user_email' => 'Nenhuma conta encontrada com este e-mail. Peça para a pessoa criar uma conta na Logiv',
            ]);

        $this->assertSame(0, Company::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_exige_o_administrador_da_empresa(): void
    {
        $this->asAdmin()->postJson('/api/system/companies', $this->payload(['user_email' => '']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_email' => 'Selecione o administrador da empresa']);

        $this->assertSame(0, Company::query()->count());
    }

    public function test_dados_de_usuario_no_formato_antigo_nao_criam_conta(): void
    {
        $this->asAdmin()->postJson('/api/system/companies', [
            'legal_name' => 'Empresa Teste LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => '11222333000181',
            'user' => [
                'name' => 'Pablo Alves',
                'email' => 'pablo@logiv.com.br',
                'password' => 'senha1234',
                'password_confirmation' => 'senha1234',
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('user_email');

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, Company::query()->count());
    }

    public function test_admin_do_sistema_pode_escolher_a_propria_conta(): void
    {
        $this->asAdmin()->postJson('/api/system/companies', $this->payload([
            'user_email' => 'admin@logiv.com.br',
        ]))->assertCreated();

        $this->assertSame($this->admin->id, CompanyUser::query()->sole()->user_id);
    }

    public function test_editar_empresa_nao_mexe_nos_vinculos(): void
    {
        $owner = $this->owner();
        $this->asAdmin()->postJson('/api/system/companies', $this->payload())->assertCreated();
        $company = Company::query()->sole();

        $this->putJson('/api/system/companies/'.$company->uuid, [
            'legal_name' => 'Empresa Nova LTDA',
            'trade_name' => 'Empresa Nova',
            'cnpj' => '11222333000181',
        ])->assertOk();

        $this->assertSame('Empresa Nova', $company->fresh()->trade_name);
        $this->assertSame(2, CompanyUser::query()->count());
        $this->assertSame(1, $owner->companies()->count());
    }

    public function test_quem_nao_e_admin_do_sistema_recebe_403(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner, 'sanctum');

        $this->postJson('/api/system/companies', $this->payload())->assertForbidden();
        $this->getJson('/api/system/users/lookup?email=pablo@logiv.com.br')->assertForbidden();

        $this->assertSame(0, Company::query()->count());
    }

    public function test_sem_login_recebe_401(): void
    {
        $this->postJson('/api/system/companies', $this->payload())->assertUnauthorized();
        $this->getJson('/api/system/users/lookup?email=pablo@logiv.com.br')->assertUnauthorized();
    }

    public function test_busca_conta_pelo_email_completo(): void
    {
        $this->owner();

        $this->asAdmin()->getJson('/api/system/users/lookup?email='.urlencode(' PABLO@Logiv.com.br '))
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'name' => 'Pablo Alves',
                    'email' => 'pablo@logiv.com.br',
                ],
            ]);
    }

    public function test_busca_nao_encontra_email_parcial_nem_inexistente(): void
    {
        $this->owner();

        $this->asAdmin()->getJson('/api/system/users/lookup?email=pablo@logiv.com')
            ->assertNotFound()
            ->assertJsonPath('message', 'Nenhuma conta encontrada com este e-mail. Peça para a pessoa criar uma conta na Logiv');

        $this->getJson('/api/system/users/lookup?email=ninguem@logiv.com.br')->assertNotFound();
    }

    public function test_busca_valida_o_email(): void
    {
        $this->asAdmin()->getJson('/api/system/users/lookup')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Informe o e-mail']);

        $this->getJson('/api/system/users/lookup?email=pablo')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Informe um e-mail válido']);
    }

    public function test_busca_respeita_o_limite(): void
    {
        $this->asAdmin();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->getJson('/api/system/users/lookup?email=ninguem@logiv.com.br')->assertNotFound();
        }

        $this->getJson('/api/system/users/lookup?email=ninguem@logiv.com.br')->assertStatus(429);
    }

    private function asAdmin(): static
    {
        return $this->actingAs($this->admin, 'sanctum');
    }

    private function owner(): User
    {
        return User::factory()->create([
            'name' => 'Pablo Alves',
            'email' => 'pablo@logiv.com.br',
            'password' => 'senha1234',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'legal_name' => 'Empresa Teste LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => '11222333000181',
            'user_email' => 'pablo@logiv.com.br',
        ], $overrides);
    }
}
