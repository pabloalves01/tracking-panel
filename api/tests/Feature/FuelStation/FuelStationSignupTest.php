<?php

namespace Tests\Feature\FuelStation;

use App\Models\Company;
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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FuelStationSignupTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '19131243000197';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // O cadastro de posto exige conta logada. Sem contexto selecionado.
        $this->user = User::factory()->create(['email' => 'dono@posto.com']);
        $this->withToken($this->user->createToken('web')->plainTextToken);

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

    public function test_cadastro_respeita_o_limite(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/auth/station-signup', [])->assertUnprocessable();
        }

        $this->postJson('/api/auth/station-signup', [])->assertStatus(429);
    }

    public function test_consulta_e_cadastro_sem_login_retornam_401(): void
    {
        $this->withoutToken();

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)->assertUnauthorized();
        $this->postJson('/api/auth/station-signup', ['cnpj' => self::CNPJ])->assertUnauthorized();

        $this->assertSame(0, FuelStation::query()->count());
        Http::assertNothingSent();
    }

    public function test_rota_de_check_email_nao_existe_mais(): void
    {
        $this->postJson('/api/auth/station-signup/check-email', [
            'email' => 'dono@posto.com',
        ])->assertNotFound();
    }

    public function test_cadastro_cria_posto_vincula_o_usuario_e_entra_no_posto(): void
    {
        [, $city] = $this->seedSaoPaulo();

        $this->postJson('/api/auth/station-signup', ['cnpj' => '19.131.243/0001-97'])
            ->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado com sucesso')
            ->assertJsonPath('fuel_station.cnpj', self::CNPJ)
            ->assertJsonPath('fuel_station.corporate_name', 'POSTO EXEMPLO LTDA')
            ->assertJsonPath('fuel_station.city_name', 'São Paulo')
            ->assertJsonPath('fuel_station.uf', 'SP');

        $station = FuelStation::query()->sole();
        $membership = FuelStationUser::query()->sole();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(self::CNPJ, $station->cnpj);
        $this->assertSame($city->id, $station->city_id);
        $this->assertSame('active', $station->status);
        $this->assertNotNull($station->receita_checked_at);
        $this->assertSame('owner', $membership->role);
        $this->assertSame($this->user->id, $membership->user_id);
        $this->assertSame($station->id, $membership->fuel_station_id);

        $token = $this->user->tokens()->sole();
        $this->assertSame($station->id, $token->fuel_station_id);
        $this->assertNull($token->company_id);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.has_fuel_station', true)
            ->assertJsonPath('data.current_fuel_station.uuid', $station->uuid);

        $this->getJson('/api/station/me')
            ->assertOk()
            ->assertJsonPath('data.cnpj', self::CNPJ);
    }

    public function test_cadastro_ignora_dados_de_conta_enviados_junto(): void
    {
        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => 'Outra Pessoa',
            'email' => 'outra@posto.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ])->assertCreated();

        $this->assertSame(1, User::query()->count());
        $this->assertSame($this->user->id, FuelStationUser::query()->sole()->user_id);
    }

    public function test_cadastro_com_empresa_selecionada_troca_para_o_posto(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Empresa Teste LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => '00000000000191',
        ]);
        $this->user->companies()->attach($company->id, ['role' => 'admin']);
        $this->user->tokens()->sole()->forceFill(['company_id' => $company->id])->save();

        $this->postJson('/api/auth/station-signup', ['cnpj' => self::CNPJ])->assertCreated();

        $token = $this->user->tokens()->sole();
        $this->assertNull($token->company_id);
        $this->assertSame(FuelStation::query()->sole()->id, $token->fuel_station_id);

        $this->getJson('/api/auth/contexts')
            ->assertOk()
            ->assertJsonCount(1, 'companies')
            ->assertJsonCount(1, 'fuel_stations');
    }

    public function test_usuario_pode_cadastrar_mais_de_um_posto(): void
    {
        $this->postJson('/api/auth/station-signup', ['cnpj' => self::CNPJ])->assertCreated();
        $this->fakeReceita(['cnpj' => '11222333000181']);
        $this->postJson('/api/auth/station-signup', ['cnpj' => '11222333000181'])->assertCreated();

        $this->assertSame(2, $this->user->fuelStations()->count());
        $this->assertSame(
            FuelStation::query()->where('cnpj', '11222333000181')->sole()->id,
            $this->user->tokens()->sole()->fuel_station_id,
        );
    }

    public function test_posto_aceita_somente_um_usuario(): void
    {
        $this->postJson('/api/auth/station-signup', ['cnpj' => self::CNPJ])->assertCreated();

        $station = FuelStation::query()->first();
        $other = User::factory()->create([
            'email' => 'outro@posto.com',
        ]);

        try {
            DB::transaction(function () use ($station, $other) {
                FuelStationUser::query()->create([
                    'fuel_station_id' => $station->id,
                    'user_id' => $other->id,
                    'role' => 'owner',
                ]);
            });
            $this->fail('O posto aceitou um segundo usuário.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_station_id', $exception->getMessage());
        }

        $this->assertSame(1, FuelStationUser::query()->count());
    }

    public function test_segundo_cadastro_do_mesmo_cnpj_nao_conclui(): void
    {
        $this->postJson('/api/auth/station-signup', ['cnpj' => self::CNPJ])->assertCreated();

        $other = User::factory()->create(['email' => 'segunda@posto.com']);
        $this->withToken($other->createToken('web')->plainTextToken);
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/station-signup', ['cnpj' => self::CNPJ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este posto já está cadastrado. Fale com o responsável');

        $this->assertSame(1, FuelStation::query()->count());
        $this->assertSame($this->user->id, FuelStationUser::query()->sole()->user_id);
        $this->assertNull($other->tokens()->sole()->fuel_station_id);
    }

    public function test_cnpj_de_posto_excluido_nao_cadastra_de_novo(): void
    {
        FuelStation::query()->create([
            'cnpj' => self::CNPJ,
            'corporate_name' => 'Posto Excluído',
            'status' => 'active',
        ])->delete();

        $this->postJson('/api/auth/station-signup', ['cnpj' => self::CNPJ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este posto já está cadastrado. Fale com o responsável');

        Http::assertNothingSent();
    }

    public function test_cadastro_valida_o_cnpj(): void
    {
        $this->postJson('/api/auth/station-signup', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cnpj' => 'O CNPJ é obrigatório']);

        $this->postJson('/api/auth/station-signup', ['cnpj' => '11111111111111'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('cnpj');

        $this->assertSame(0, FuelStation::query()->count());
        Http::assertNothingSent();
    }

    public function test_cnpj_duplicado_na_criacao_avisa_que_o_posto_ja_existe(): void
    {
        FuelStation::creating(function (FuelStation $station) {
            if ($station->corporate_name === 'Outro cadastro') {
                return;
            }

            FuelStation::query()->create([
                'cnpj' => self::CNPJ,
                'corporate_name' => 'Outro cadastro',
                'status' => 'active',
            ]);
        });

        $this->postJson('/api/auth/station-signup', ['cnpj' => self::CNPJ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este posto já está cadastrado. Fale com o responsável');

        $this->assertSame(0, FuelStationUser::query()->count());
        $this->assertNull($this->user->tokens()->sole()->fuel_station_id);
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
}
