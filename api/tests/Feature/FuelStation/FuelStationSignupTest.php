<?php

namespace Tests\Feature\FuelStation;

use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FuelStationSignupTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '19131243000197';

    private const ALREADY = 'Este posto já está cadastrado na Logiv. Peça um convite ao administrador do posto.';

    private const RECEITA = 'Não foi possível consultar a Receita agora. Tente de novo em instantes.';

    private const EMAIL_TAKEN = 'Este e-mail já tem conta. Entre com ela para cadastrar o posto.';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->fakeReceita();
    }

    public function test_consulta_cnpj_ativo_e_reaproveita_o_cache(): void
    {
        $this->seedSaoPaulo();

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertOk()
            ->assertJsonPath('corporate_name', 'POSTO EXEMPLO LTDA')
            ->assertJsonPath('city_name', 'São Paulo')
            ->assertJsonPath('uf', 'SP')
            ->assertJsonMissingPath('state_id');

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)->assertOk();

        Http::assertSentCount(1);
    }

    public function test_cnpj_invalido_nao_consulta_a_receita(): void
    {
        $this->getJson('/api/auth/station-signup/cnpj/123')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Informe um CNPJ válido.');

        Http::assertNothingSent();
    }

    public function test_cnpj_ja_cadastrado_retorna_422(): void
    {
        FuelStation::query()->create([
            'cnpj' => self::CNPJ,
            'corporate_name' => 'Posto Existente',
            'status' => 'active',
        ]);

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este posto já está cadastrado na Logiv. Peça um convite ao administrador do posto.');

        Http::assertNothingSent();
    }

    public function test_cnpj_que_nao_esta_ativo_retorna_422(): void
    {
        $this->fakeReceita([
            'descricao_situacao_cadastral' => 'BAIXADA',
        ]);

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O CNPJ não está com situação cadastral ATIVA');
    }

    public function test_brasilapi_fora_retorna_503_e_nao_grava_cache(): void
    {
        $this->resetHttp();
        Http::fake(function () {
            throw new ConnectionException('offline');
        });

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertStatus(503)
            ->assertJsonPath('message', 'Não foi possível consultar a Receita agora. Tente de novo em instantes.');

        $this->fakeReceita();

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertOk()
            ->assertJsonPath('corporate_name', 'POSTO EXEMPLO LTDA');
    }

    public function test_brasilapi_nao_encontra_o_cnpj(): void
    {
        $this->fakeReceita([], 404);

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'CNPJ não encontrado na Receita Federal');
    }

    public function test_brasilapi_com_erro_retorna_503(): void
    {
        $this->fakeReceita([], 500);

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertStatus(503)
            ->assertJsonPath('message', 'Não foi possível consultar a Receita agora. Tente de novo em instantes.');
    }

    public function test_consulta_de_cnpj_respeita_o_limite(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)->assertOk();
        }

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)->assertStatus(429);
    }

    public function test_cadastro_respeita_o_limite(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/auth/station-signup', [])->assertUnprocessable();
        }

        $this->postJson('/api/auth/station-signup', [])->assertStatus(429);
    }

    public function test_cadastro_cria_posto_e_usuario(): void
    {
        [, $city] = $this->seedSaoPaulo();

        $this->registerStation();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, FuelStation::query()->count());

        $user = User::query()->first();
        $station = FuelStation::query()->first();
        $membership = FuelStationUser::query()->first();

        $this->assertSame('Dona do Posto', $user->name);
        $this->assertSame('nova@posto.com', $user->email);
        $this->assertTrue(Hash::check('senha1234', $user->password));
        $this->assertSame(self::CNPJ, $station->cnpj);
        $this->assertSame($city->id, $station->city_id);
        $this->assertSame('admin', $membership->role);
        $this->assertSame($user->id, $membership->user_id);
    }

    public function test_posto_aceita_varios_usuarios_mas_nao_repete_o_mesmo(): void
    {
        $this->registerStation();

        $station = FuelStation::query()->first();
        $admin = User::query()->first();
        $other = User::factory()->create([
            'email' => 'outro@posto.com',
        ]);

        FuelStationUser::query()->create([
            'fuel_station_id' => $station->id,
            'user_id' => $other->id,
            'role' => 'member',
        ]);

        try {
            DB::transaction(function () use ($station, $admin) {
                FuelStationUser::query()->create([
                    'fuel_station_id' => $station->id,
                    'user_id' => $admin->id,
                    'role' => 'member',
                ]);
            });
            $this->fail('O posto aceitou o mesmo usuário duas vezes.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_station', $exception->getMessage());
        }

        $this->assertSame(2, FuelStationUser::query()->count());
    }

    public function test_segundo_cadastro_do_mesmo_cnpj_nao_conclui(): void
    {
        $this->registerStation('primeira@posto.com', 'Primeira Pessoa');

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => 'Outra Pessoa',
            'email' => 'segunda@posto.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Este posto já está cadastrado na Logiv. Peça um convite ao administrador do posto.');

        $this->assertSame(1, FuelStation::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, FuelStationUser::query()->count());
        $this->assertSame('primeira@posto.com', User::query()->first()->email);
    }

    public function test_email_duplicado_na_criacao_avisa_que_a_conta_ja_existe(): void
    {
        FuelStation::created(function () {
            if (User::query()->where('email', 'nova@posto.com')->exists()) {
                return;
            }

            User::query()->insert([
                'uuid' => (string) Str::uuid(),
                'name' => 'Outra conta',
                'email' => 'nova@posto.com',
                'password' => 'secreta',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => 'Dona do Posto',
            'email' => 'nova@posto.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => self::EMAIL_TAKEN]);

        $this->assertSame(0, FuelStation::query()->count());
    }

    public function test_cadastro_entra_direto_no_posto(): void
    {
        $token = $this->postJson('/api/auth/station-signup', $this->signupPayload())
            ->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado')
            ->json('token');

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.has_fuel_station', true)
            ->assertJsonPath('data.current_fuel_station.cnpj', self::CNPJ)
            ->assertJsonPath('data.current_role', 'admin');
    }

    public function test_email_que_ja_tem_conta_pede_login_sem_consultar_nem_criar(): void
    {
        $user = User::factory()->create([
            'name' => 'Conta Antiga',
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ]);

        $this->postJson('/api/auth/station-signup', $this->signupPayload(['email' => ' DONO@Posto.com ']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => self::EMAIL_TAKEN]);

        $this->assertSame(0, FuelStation::query()->count());
        $this->assertSame('Conta Antiga', $user->fresh()->name);
        $this->assertTrue(Hash::check('senha-atual', $user->fresh()->password));
        Http::assertNothingSent();
    }

    public function test_cadastro_valida_campos_sem_quebrar_com_array(): void
    {
        $this->postJson('/api/auth/station-signup', [
            'cnpj' => ['1'],
            'name' => ['a'],
            'email' => ['a@b.com'],
            'password' => ['x'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj', 'name', 'email', 'password']);

        $this->postJson('/api/auth/station-signup', $this->signupPayload([
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'A senha deve ter no mínimo 8 caracteres']);

        $this->postJson('/api/auth/station-signup', $this->signupPayload([
            'password_confirmation' => 'outra-senha',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'A confirmação da senha não confere']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_receita_sem_razao_social_nao_cadastra(): void
    {
        $this->fakeReceita(['razao_social' => '']);

        $this->postJson('/api/auth/station-signup', $this->signupPayload())
            ->assertStatus(503)
            ->assertJsonPath('message', self::RECEITA);

        $this->assertSame(0, User::query()->count());
    }

    // ===================== CONTA LOGADA =====================

    public function test_conta_logada_cadastra_o_posto_e_o_token_vai_pro_posto(): void
    {
        $user = User::factory()->create(['email' => 'dono@posto.com']);
        $token = $user->createToken('web')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/auth/stations', ['cnpj' => '19.131.243/0001-97'])
            ->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado');

        $this->assertSame(1, User::query()->count());
        $membership = FuelStationUser::query()->sole();
        $this->assertSame($user->id, $membership->user_id);
        $this->assertSame('admin', $membership->role);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)
            ->getJson('/api/auth/me')
            ->assertJsonPath('data.current_fuel_station.cnpj', self::CNPJ)
            ->assertJsonPath('data.current_role', 'admin');
    }

    public function test_conta_logada_tambem_respeita_cnpj_ja_cadastrado_e_invalido(): void
    {
        $this->registerStation();
        $user = User::factory()->create(['email' => 'outra@posto.com']);
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('web')->plainTextToken);

        $this->postJson('/api/auth/stations', ['cnpj' => self::CNPJ])
            ->assertUnprocessable()
            ->assertJsonPath('message', self::ALREADY);
        $this->postJson('/api/auth/stations', ['cnpj' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj' => 'Informe um CNPJ válido.']);
        $this->postJson('/api/auth/stations', ['cnpj' => ['123']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cnpj');

        $this->assertSame(1, FuelStationUser::query()->count());
    }

    public function test_cadastro_na_conta_logada_exige_login(): void
    {
        $this->postJson('/api/auth/stations', ['cnpj' => self::CNPJ])->assertUnauthorized();
    }

    public function test_rota_check_email_saiu(): void
    {
        $this->postJson('/api/auth/station-signup/check-email', ['email' => 'a@b.com'])->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function signupPayload(array $overrides = []): array
    {
        return array_merge([
            'cnpj' => self::CNPJ,
            'name' => 'Dona do Posto',
            'email' => 'nova@posto.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeReceita(array $overrides = [], int $status = 200): void
    {
        $this->resetHttp();

        Http::fake([
            'brasilapi.com.br/api/cnpj/v1/*' => Http::response($this->receitaPayload($overrides), $status),
        ]);
    }

    private function resetHttp(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function receitaPayload(array $overrides = []): array
    {
        return array_replace([
            'cnpj' => self::CNPJ,
            'razao_social' => 'POSTO EXEMPLO LTDA',
            'nome_fantasia' => 'POSTO EXEMPLO',
            'descricao_situacao_cadastral' => 'ATIVA',
            'situacao_cadastral' => 2,
            'descricao_tipo_de_logradouro' => 'AVENIDA',
            'logradouro' => 'PAULISTA',
            'numero' => '1000',
            'complemento' => 'LOJA 1',
            'bairro' => 'BELA VISTA',
            'cep' => '01310-930',
            'uf' => 'SP',
            'municipio' => 'SAO PAULO',
            'codigo_municipio_ibge' => 3550308,
        ], $overrides);
    }

    /**
     * @return array{0: State, 1: City}
     */
    private function seedSaoPaulo(): array
    {
        $state = State::query()->create([
            'ibge_code' => 35,
            'name' => 'São Paulo',
            'uf' => 'SP',
            'region' => 'Sudeste',
        ]);

        $city = City::query()->create([
            'ibge_code' => 3550308,
            'state_id' => $state->id,
            'name' => 'São Paulo',
        ]);

        return [$state, $city];
    }

    private function registerStation(string $email = 'nova@posto.com', string $name = 'Dona do Posto'): void
    {
        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => $name,
            'email' => $email,
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ])->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado');
    }
}
