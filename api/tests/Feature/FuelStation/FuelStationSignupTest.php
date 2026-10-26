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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FuelStationSignupTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '19131243000197';

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
            ->assertJsonPath('message', 'Informe um CNPJ válido');

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
            ->assertJsonPath('message', 'Este posto já está cadastrado. Fale com o responsável');

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
            ->assertJsonPath('message', 'A consulta de CNPJ está indisponível. Tente novamente em instantes');

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
            ->assertJsonPath('message', 'A consulta de CNPJ está indisponível. Tente novamente em instantes');
    }

    public function test_consulta_de_cnpj_respeita_o_limite(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)->assertOk();
        }

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)->assertStatus(429);
    }

    public function test_consulta_de_email_nao_existe_mais(): void
    {
        $this->postJson('/api/auth/station-signup/check-email', [
            'email' => 'existe@posto.com',
        ])->assertNotFound();
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
        $this->assertSame('owner', $membership->role);
        $this->assertSame($user->id, $membership->user_id);
    }

    public function test_posto_aceita_varios_usuarios_e_nao_repete_o_par(): void
    {
        $this->registerStation();

        $station = FuelStation::query()->first();
        $other = User::factory()->create([
            'email' => 'outro@posto.com',
        ]);

        FuelStationUser::query()->create([
            'fuel_station_id' => $station->id,
            'user_id' => $other->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $this->assertSame(2, FuelStationUser::query()->count());

        try {
            FuelStationUser::query()->create([
                'fuel_station_id' => $station->id,
                'user_id' => $other->id,
                'role' => 'member',
                'status' => 'active',
            ]);
            $this->fail('O posto repetiu o mesmo usuário.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_station', $exception->getMessage());
        }
    }

    public function test_email_existente_pede_para_entrar(): void
    {
        User::factory()->create([
            'name' => 'Conta Antiga',
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ]);

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => 'Outro Nome',
            'email' => ' DONO@POSTO.COM ',
            'password' => 'senha-atual',
            'password_confirmation' => 'senha-atual',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Este e-mail já tem conta. Entre para cadastrar o posto');

        $this->assertSame(0, FuelStation::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_conta_logada_cadastra_o_posto_sem_nova_senha(): void
    {
        $user = User::factory()->create([
            'name' => 'Conta Antiga',
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ]);
        $token = $user->createToken('web');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/auth/stations', [
                'cnpj' => '19.131.243/0001-97',
            ])->assertCreated()
            ->assertJsonPath('message', 'Posto vinculado à sua conta');

        $this->assertSame(1, User::query()->count());
        $this->assertSame($user->id, FuelStationUser::query()->first()->user_id);
        $this->assertSame('owner', FuelStationUser::query()->first()->role);
        $this->assertSame(FuelStation::query()->first()->id, $token->accessToken->fresh()->fuel_station_id);
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
            ->assertJsonPath('message', 'Este posto já está cadastrado. Fale com o responsável');

        $this->assertSame(1, FuelStation::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, FuelStationUser::query()->count());
        $this->assertSame('primeira@posto.com', User::query()->first()->email);
    }

    public function test_empresa_nova_convida_o_proprietario_sem_criar_conta(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@logiv.com.br',
            'is_system_admin' => true,
        ]);

        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/system/companies', [
            'legal_name' => 'Empresa Teste LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => '11.222.333/0001-81',
            'admin_email' => ' PabloAlvesZimba@gmail.com ',
        ])->assertCreated()
            ->assertJsonPath('invitation.email', 'pabloalveszimba@gmail.com')
            ->assertJsonPath('invitation.role', 'owner');

        $this->assertNull(User::query()->where('email', 'pabloalveszimba@gmail.com')->first());
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('companies', [
            'cnpj' => '11222333000181',
        ]);
    }

    public function test_email_duplicado_na_criacao_avisa_que_a_conta_ja_existe(): void
    {
        FuelStation::created(function () {
            if (User::query()->where('email', 'nova@posto.com')->exists()) {
                return;
            }

            User::query()->insert([
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
            ->assertJsonPath('message', 'Este e-mail já tem conta. Entre para cadastrar o posto');

        $this->assertSame(0, FuelStation::query()->count());
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
            ->assertJsonPath('message', 'Posto cadastrado com sucesso');
    }
}
