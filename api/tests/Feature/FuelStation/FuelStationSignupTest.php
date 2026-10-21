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
        [$state, $city] = $this->seedSaoPaulo();

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertOk()
            ->assertJsonPath('cnpj', self::CNPJ)
            ->assertJsonPath('corporate_name', 'POSTO EXEMPLO LTDA')
            ->assertJsonPath('trade_name', 'POSTO EXEMPLO')
            ->assertJsonPath('receita_status', 'ATIVA')
            ->assertJsonPath('zip_code', '01310930')
            ->assertJsonPath('street', 'AVENIDA PAULISTA')
            ->assertJsonPath('number', '1000')
            ->assertJsonPath('district', 'BELA VISTA')
            ->assertJsonPath('state_id', $state->id)
            ->assertJsonPath('city_id', $city->id);

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
            'receita_status' => 'ATIVA',
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

    public function test_consulta_de_cnpj_respeita_o_limite(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)->assertOk();
        }

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)->assertStatus(429);
    }

    public function test_check_email_diz_se_a_conta_existe_sem_criar_nada(): void
    {
        User::factory()->create([
            'email' => 'existe@posto.com',
        ]);

        $this->postJson('/api/auth/station-signup/check-email', [
            'cnpj' => self::CNPJ,
            'email' => 'existe@posto.com',
        ])->assertOk()->assertExactJson(['exists' => true]);

        $this->postJson('/api/auth/station-signup/check-email', [
            'cnpj' => self::CNPJ,
            'email' => 'novo@posto.com',
        ])->assertOk()->assertExactJson(['exists' => false]);

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, FuelStation::query()->count());
    }

    public function test_cadastro_cria_posto_e_usuario(): void
    {
        [$state, $city] = $this->seedSaoPaulo();

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
        $this->assertSame('POSTO EXEMPLO LTDA', $station->corporate_name);
        $this->assertSame('POSTO EXEMPLO', $station->trade_name);
        $this->assertSame('ATIVA', $station->receita_status);
        $this->assertNotNull($station->receita_checked_at);
        $this->assertSame('01310930', $station->zip_code);
        $this->assertSame('AVENIDA PAULISTA', $station->street);
        $this->assertSame('1000', $station->number);
        $this->assertSame('LOJA 1', $station->complement);
        $this->assertSame('BELA VISTA', $station->district);
        $this->assertSame($state->id, $station->state_id);
        $this->assertSame($city->id, $station->city_id);
        $this->assertNull($station->pix_key_type);
        $this->assertNull($station->pix_key);
        $this->assertSame('active', $station->status);
        $this->assertSame($station->id, $membership->fuel_station_id);
        $this->assertSame($user->id, $membership->user_id);
        $this->assertSame('owner', $membership->role);
        $this->assertSame('active', $membership->status);
        $this->assertNull($membership->invited_by);
    }

    public function test_posto_aceita_somente_um_usuario(): void
    {
        $this->registerStation();

        $station = FuelStation::query()->first();
        $other = User::factory()->create([
            'email' => 'outro@posto.com',
        ]);

        try {
            FuelStationUser::query()->create([
                'fuel_station_id' => $station->id,
                'user_id' => $other->id,
                'role' => 'owner',
                'status' => 'active',
            ]);
            $this->fail('O posto aceitou um segundo usuário.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('fuel_station_id', $exception->getMessage());
        }

        $this->assertSame(1, FuelStationUser::query()->count());
    }

    public function test_email_existente_com_senha_certa_liga_o_posto_ao_usuario(): void
    {
        $verifiedAt = now()->subDay();
        $user = User::factory()->create([
            'name' => 'Conta Antiga',
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
            'email_verified_at' => $verifiedAt,
        ]);

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ])->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado com sucesso');

        $this->assertSame(1, User::query()->count());
        $this->assertSame('Conta Antiga', $user->fresh()->name);
        $this->assertSame(
            $verifiedAt->toDateTimeString(),
            $user->fresh()->email_verified_at->toDateTimeString(),
        );

        $membership = FuelStationUser::query()->first();
        $this->assertSame($user->id, $membership->user_id);
        $this->assertSame('owner', $membership->role);
        $this->assertSame('active', $membership->status);
        $this->assertSame('active', FuelStation::query()->first()->status);
    }

    public function test_email_existente_com_senha_errada_nao_cadastra(): void
    {
        User::factory()->create([
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ]);

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/api/auth/station-signup', [
                'cnpj' => self::CNPJ,
                'email' => 'dono@posto.com',
                'password' => 'errada',
            ])->assertUnprocessable()
                ->assertJsonPath('message', 'Senha incorreta');
        }

        $this->assertSame(0, FuelStation::query()->count());
        $this->assertSame(1, User::query()->count());

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ])->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado com sucesso');

        $this->assertSame(1, FuelStation::query()->count());
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
