<?php

namespace Tests\Feature\FuelStation;

use App\Models\Company;
use App\Models\FuelStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_com_apenas_empresa_ja_seleciona_a_empresa(): void
    {
        $user = $this->user();
        $company = $this->companyFor($user);

        $token = $this->login($user);

        $this->assertSame(1, count($token['companies']));
        $this->assertSame([], $token['fuel_stations']);
        $this->assertSame($company->uuid, $token['user']['current_company']['uuid']);
        $this->assertTrue($token['user']['has_company']);
        $this->assertFalse($token['user']['has_fuel_station']);
    }

    public function test_login_com_apenas_posto_ja_seleciona_o_posto(): void
    {
        $user = $this->user();
        $station = $this->stationFor($user);

        $token = $this->login($user);

        $this->assertSame([], $token['companies']);
        $this->assertSame($station->uuid, $token['fuel_stations'][0]['uuid']);
        $this->assertSame($station->uuid, $token['user']['current_fuel_station']['uuid']);
        $this->assertFalse($token['user']['has_company']);
        $this->assertTrue($token['user']['has_fuel_station']);
    }

    public function test_login_com_empresa_e_posto_devolve_os_dois_sem_selecionar(): void
    {
        $user = $this->user();
        $company = $this->companyFor($user);
        $station = $this->stationFor($user);

        $token = $this->login($user);

        $this->assertSame($company->uuid, $token['companies'][0]['uuid']);
        $this->assertSame($station->uuid, $token['fuel_stations'][0]['uuid']);
        $this->assertFalse($token['user']['has_company']);
        $this->assertFalse($token['user']['has_fuel_station']);

        $this->withToken($token['token'])->getJson('/api/auth/contexts')
            ->assertOk()
            ->assertJsonPath('companies.0.uuid', $company->uuid)
            ->assertJsonPath('fuel_stations.0.uuid', $station->uuid);
    }

    public function test_selecionar_posto_grava_o_posto_e_limpa_a_empresa(): void
    {
        $user = $this->user();
        $company = $this->companyFor($user);
        $station = $this->stationFor($user, '19131243000197', 'Posto Centro');
        $token = $this->login($user)['token'];

        $this->withToken($token)->postJson('/api/auth/station', ['uuid' => $station->uuid])
            ->assertOk()
            ->assertJsonPath('data.uuid', $station->uuid);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'company_id' => null,
            'fuel_station_id' => $station->id,
        ]);

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.has_fuel_station', true)
            ->assertJsonPath('data.current_fuel_station.uuid', $station->uuid)
            ->assertJsonPath('data.has_company', false);

        $this->withToken($token)->getJson('/api/registry/entities')->assertStatus(409);
        $this->withToken($token)->getJson('/api/station/me')
            ->assertOk()
            ->assertJsonPath('data.cnpj', '19131243000197');

        $this->withToken($token)->postJson('/api/auth/company', ['uuid' => $company->uuid])
            ->assertOk()
            ->assertJsonPath('data.uuid', $company->uuid);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'company_id' => $company->id,
            'fuel_station_id' => null,
        ]);

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.has_company', true)
            ->assertJsonPath('data.has_fuel_station', false);

        $this->withToken($token)->getJson('/api/station/me')->assertStatus(409);
        $this->withToken($token)->getJson('/api/registry/entities')->assertOk();
    }

    public function test_token_de_posto_na_rota_da_empresa_retorna_409(): void
    {
        $user = $this->user();
        $this->stationFor($user);
        $token = $this->login($user)['token'];

        $this->withToken($token)->getJson('/api/registry/entities')->assertStatus(409);
        $this->withToken($token)->getJson('/api/station/me')->assertOk();
    }

    public function test_token_de_empresa_na_rota_do_posto_retorna_409(): void
    {
        $user = $this->user();
        $this->companyFor($user);
        $token = $this->login($user)['token'];

        $this->withToken($token)->getJson('/api/station/me')->assertStatus(409);
        $this->withToken($token)->getJson('/api/registry/entities')->assertOk();
    }

    public function test_posto_inativo_fica_fora_do_login_e_nao_entra(): void
    {
        $user = $this->user();
        $station = $this->stationFor($user);
        $station->update(['status' => 'inactive']);

        $token = $this->login($user);

        $this->assertSame([], $token['fuel_stations']);
        $this->assertFalse($token['user']['has_fuel_station']);

        $this->withToken($token['token'])->getJson('/api/auth/contexts')
            ->assertOk()
            ->assertJsonCount(0, 'fuel_stations');

        $this->withToken($token['token'])
            ->postJson('/api/auth/station', ['uuid' => $station->uuid])
            ->assertForbidden();
    }

    public function test_token_com_empresa_e_posto_retorna_409_nas_duas_rotas(): void
    {
        $user = $this->user();
        $company = $this->companyFor($user);
        $station = $this->stationFor($user);
        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'company_id' => $company->id,
            'fuel_station_id' => $station->id,
        ])->save();

        $this->withToken($token->plainTextToken)->getJson('/api/registry/entities')->assertStatus(409);
        $this->withToken($token->plainTextToken)->getJson('/api/station/me')->assertStatus(409);
    }

    public function test_login_com_email_em_maiusculas(): void
    {
        $user = $this->user('dono@posto.com');
        $this->stationFor($user);

        $this->postJson('/api/auth/login', [
            'email' => ' DONO@POSTO.COM ',
            'password' => 'senha-atual',
        ])->assertOk()
            ->assertJsonPath('user.email', 'dono@posto.com')
            ->assertJsonPath('user.has_fuel_station', true);
    }

    public function test_admin_do_sistema_com_posto_entra_no_posto(): void
    {
        $admin = $this->user('admin@posto.com');
        $admin->forceFill(['is_system_admin' => true])->save();
        $station = $this->stationFor($admin);

        $token = $this->login($admin);

        $this->assertTrue($token['user']['is_system_admin']);
        $this->assertFalse($token['user']['has_company']);
        $this->assertTrue($token['user']['has_fuel_station']);
        $this->assertSame($station->uuid, $token['user']['current_fuel_station']['uuid']);
        $this->assertSame($station->id, $admin->tokens()->first()->fuel_station_id);
        $this->assertNull($admin->tokens()->first()->company_id);
    }

    public function test_usuario_de_fora_nao_seleciona_o_posto(): void
    {
        $owner = $this->user('dono@posto.com');
        $station = $this->stationFor($owner);
        $outsider = $this->user('fora@posto.com');

        $this->withToken($this->login($outsider)['token'])
            ->postJson('/api/auth/station', ['uuid' => $station->uuid])
            ->assertForbidden();
    }

    public function test_usuario_de_fora_do_posto_recebe_403(): void
    {
        $owner = $this->user('dono@posto.com');
        $station = $this->stationFor($owner);
        $outsider = $this->user('fora@posto.com');

        $token = $outsider->createToken('web');
        $token->accessToken->forceFill(['fuel_station_id' => $station->id])->save();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/station/me')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não tem acesso a este posto');
    }

    private function user(string $email = 'dono@posto.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => 'senha-atual',
        ]);
    }

    private function login(User $user): array
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'senha-atual',
        ])->assertOk();

        // O login autentica a sessão de teste. Sem sair, o Sanctum ignora o bearer.
        auth()->logout();
        $this->app['session']->save();
        $this->app['auth']->forgetGuards();

        return $response->json();
    }

    private function companyFor(User $user, string $cnpj = '00000000000191'): Company
    {
        $company = Company::query()->create([
            'legal_name' => 'Empresa Teste LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => $cnpj,
        ]);

        $user->companies()->attach($company->id, ['role' => 'admin']);

        return $company;
    }

    private function stationFor(User $user, string $cnpj = '19131243000197', string $tradeName = 'Posto Teste'): FuelStation
    {
        $station = FuelStation::query()->create([
            'cnpj' => $cnpj,
            'corporate_name' => $tradeName.' LTDA',
            'trade_name' => $tradeName,
            'receita_status' => 'ATIVA',
            'zip_code' => '01001000',
            'street' => 'Praça da Sé',
            'number' => '100',
            'district' => 'Sé',
            'status' => 'active',
        ]);

        $user->fuelStations()->attach($station->id, ['role' => 'owner']);

        return $station;
    }
}
