<?php

namespace Tests\Feature\FuelStation;

use App\Mail\FuelStationSignupCodeMail;
use App\Models\FuelStation;
use App\Models\FuelStationSignup;
use App\Models\FuelStationUser;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FuelStationSignupTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '19131243000197';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
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
        $this->assertSame(0, FuelStationSignup::query()->count());
    }

    public function test_codigo_e_salvo_em_hash_e_nada_e_criado_antes_de_verificar(): void
    {
        [$state, $city] = $this->seedSaoPaulo();
        $code = $this->startSignup();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, FuelStation::query()->count());
        $this->assertSame(0, FuelStationUser::query()->count());
        $this->assertSame(1, FuelStationSignup::query()->count());

        $signup = FuelStationSignup::query()->first();
        $this->assertNotSame($code, $signup->code_hash);
        $this->assertTrue(Hash::check($code, $signup->code_hash));
        $this->assertTrue(Hash::check('senha1234', $signup->password));
        $this->assertStringContainsString('123456', (new FuelStationSignupCodeMail('123456'))->render());

        $this->postJson("/api/auth/station-signup/{$signup->uuid}/verify", [
            'code' => $code,
        ])->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado com sucesso');

        $this->assertSame(0, FuelStationSignup::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, FuelStation::query()->count());

        $user = User::query()->first();
        $station = FuelStation::query()->first();
        $membership = FuelStationUser::query()->first();

        $this->assertSame('Dona do Posto', $user->name);
        $this->assertSame('nova@posto.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
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

    public function test_codigo_errado_incrementa_as_tentativas(): void
    {
        $code = $this->startSignup();
        $signup = FuelStationSignup::query()->first();
        $wrong = $code === '000000' ? '000001' : '000000';

        $this->postJson("/api/auth/station-signup/{$signup->uuid}/verify", [
            'code' => $wrong,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        $this->assertSame(1, $signup->fresh()->attempts);
        $this->assertSame(0, FuelStation::query()->count());
    }

    public function test_codigo_expirado_retorna_422(): void
    {
        $code = $this->startSignup();
        $signup = FuelStationSignup::query()->first();

        $this->travel(11)->minutes();

        $this->postJson("/api/auth/station-signup/{$signup->uuid}/verify", [
            'code' => $code,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'O código expirou. Peça um novo código');

        $this->assertNotNull($signup->fresh());
        $this->assertSame(0, FuelStation::query()->count());
    }

    public function test_cinco_codigos_errados_invalidam_o_cadastro(): void
    {
        $code = $this->startSignup();
        $signup = FuelStationSignup::query()->first();
        $wrong = $code === '000000' ? '000001' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson("/api/auth/station-signup/{$signup->uuid}/verify", [
                'code' => $wrong,
            ])->assertUnprocessable();
        }

        $this->assertSame(0, FuelStationSignup::query()->count());
        $this->assertSame(0, User::query()->count());

        $this->postJson("/api/auth/station-signup/{$signup->uuid}/verify", [
            'code' => $code,
        ])->assertNotFound();
    }

    public function test_reenvio_antes_de_60_segundos_retorna_429_e_depois_troca_o_codigo(): void
    {
        $code = $this->startSignup();
        $signup = FuelStationSignup::query()->first();
        $signup->forceFill(['attempts' => 4])->save();

        $this->postJson("/api/auth/station-signup/{$signup->uuid}/resend")
            ->assertStatus(429);

        $this->travel(61)->seconds();

        $this->postJson("/api/auth/station-signup/{$signup->uuid}/resend")
            ->assertOk()
            ->assertJsonStructure(['expires_at']);

        $signup->refresh();
        $this->assertSame(0, $signup->attempts);
        $this->assertFalse(Hash::check($code, $signup->code_hash));

        $resent = null;
        Mail::assertSent(FuelStationSignupCodeMail::class, function (FuelStationSignupCodeMail $mail) use (&$resent) {
            $resent = $mail->code;

            return true;
        });

        $this->postJson("/api/auth/station-signup/{$signup->uuid}/verify", [
            'code' => $code,
        ])->assertUnprocessable();

        $this->postJson("/api/auth/station-signup/{$signup->uuid}/verify", [
            'code' => $resent,
        ])->assertCreated();
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
        $this->assertSame(0, FuelStationSignup::query()->count());
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
        Mail::assertNothingSent();
    }

    public function test_email_existente_com_senha_errada_bloqueia_na_sexta_tentativa(): void
    {
        User::factory()->create([
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/auth/station-signup', [
                'cnpj' => self::CNPJ,
                'email' => 'dono@posto.com',
                'password' => 'errada',
            ])->assertUnprocessable()
                ->assertJsonPath('message', 'Senha incorreta');
        }

        $this->travel(61)->seconds();

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'email' => 'dono@posto.com',
            'password' => 'errada',
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas de senha. Tente novamente em alguns minutos');

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ])->assertStatus(429);

        $this->assertSame(0, FuelStation::query()->count());
        $this->assertSame(0, FuelStationSignup::query()->count());
    }

    public function test_segundo_cadastro_do_mesmo_cnpj_nao_conclui(): void
    {
        $first = $this->startSignup('primeira@posto.com');
        $second = $this->startSignup('segunda@posto.com', 'Outra Pessoa');

        $firstSignup = FuelStationSignup::query()->where('email', 'primeira@posto.com')->first();
        $secondSignup = FuelStationSignup::query()->where('email', 'segunda@posto.com')->first();

        $this->postJson("/api/auth/station-signup/{$firstSignup->uuid}/verify", [
            'code' => $first,
        ])->assertCreated();

        $this->postJson("/api/auth/station-signup/{$secondSignup->uuid}/verify", [
            'code' => $second,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Este posto já está cadastrado. Fale com o responsável');

        $this->assertSame(1, FuelStation::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, FuelStationUser::query()->count());
        $this->assertNotNull($secondSignup->fresh());
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

    private function startSignup(string $email = 'nova@posto.com', string $name = 'Dona do Posto'): string
    {
        $response = $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => $name,
            'email' => $email,
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ])->assertOk();

        $code = null;
        Mail::assertSent(FuelStationSignupCodeMail::class, function (FuelStationSignupCodeMail $mail) use ($email, &$code) {
            if (! $mail->hasTo($email)) {
                return false;
            }

            $code = $mail->code;

            return true;
        });

        $this->assertNotEmpty($response->json('signup_uuid'));
        $this->assertNotEmpty($response->json('email_masked'));
        $this->assertNotSame($code, $response->json('email_masked'));

        return $code;
    }
}
