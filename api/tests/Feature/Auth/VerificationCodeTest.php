<?php

namespace Tests\Feature\Auth;

use App\Mail\StationSignupExistingAccountMail;
use App\Mail\VerificationCodeMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VerificationCodeTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '19131243000197';

    public function test_envio_publico_nao_revela_se_o_email_existe(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ja@posto.test', 'password' => 'senha1234']);

        $known = $this->postJson('/api/auth/verification-codes', [
            'email' => ' JA@posto.test ',
            'purpose' => 'station_signup',
        ])->assertOk();

        $unknown = $this->postJson('/api/auth/verification-codes', [
            'email' => 'nova@posto.test',
            'purpose' => 'station_signup',
        ])->assertOk();

        $this->assertSame($known->json('message'), $unknown->json('message'));
        $this->assertSame(
            'Enviamos um código para este e-mail.',
            $known->json('message'),
        );
        Mail::assertQueued(VerificationCodeMail::class, 1);
        Mail::assertQueued(VerificationCodeMail::class, fn (VerificationCodeMail $mail) => $mail->hasTo('nova@posto.test'));
        Mail::assertQueued(StationSignupExistingAccountMail::class, fn (StationSignupExistingAccountMail $mail) => $mail->hasTo('ja@posto.test'));
        $this->assertNull(VerificationCode::query()->where('email', 'ja@posto.test')->value('code_hash'));
        $this->assertNotNull(VerificationCode::query()->where('email', 'nova@posto.test')->value('code_hash'));
    }

    public function test_cinco_codigos_errados_invalidam_o_codigo_certo(): void
    {
        $this->plant('dona@melo.test', '123456', VerificationCode::PASSWORD_RESET);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/auth/password/reset', [
                'email' => 'dona@melo.test',
                'code' => '000000',
                'password' => 'senha-nova-1',
                'password_confirmation' => 'senha-nova-1',
            ])->assertUnprocessable()
                ->assertJsonPath('message', 'Código inválido');
        }

        $this->postJson('/api/auth/password/reset', [
            'email' => 'dona@melo.test',
            'code' => '000000',
            'password' => 'senha-nova-1',
            'password_confirmation' => 'senha-nova-1',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        $this->assertSame(5, VerificationCode::query()->where('email', 'dona@melo.test')->value('attempts'));
        $this->assertNotNull(VerificationCode::query()->where('email', 'dona@melo.test')->value('invalidated_at'));

        $this->postJson('/api/auth/password/reset', [
            'email' => 'dona@melo.test',
            'code' => '123456',
            'password' => 'senha-nova-1',
            'password_confirmation' => 'senha-nova-1',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        $this->assertNull(VerificationCode::query()->where('email', 'dona@melo.test')->value('consumed_at'));
        $this->assertNull(User::query()->where('email', 'dona@melo.test')->first());
    }

    public function test_codigo_errado_responde_igual_com_ou_sem_conta(): void
    {
        User::factory()->create(['email' => 'errado.existe@melo.test', 'password' => 'senha1234']);
        $this->plant('errado.existe@melo.test', '123456', VerificationCode::PASSWORD_RESET);

        $this->assertSamePublicFailure(
            $this->timedReset('errado.existe@melo.test', '000000'),
            $this->timedReset('errado.nao@melo.test', '000000'),
        );
    }

    public function test_codigo_esgotado_responde_igual_com_ou_sem_conta(): void
    {
        User::factory()->create(['email' => 'esgotado.existe@melo.test', 'password' => 'senha1234']);
        $this->plant('esgotado.existe@melo.test', '123456', VerificationCode::PASSWORD_RESET);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postReset('esgotado.existe@melo.test', '000000')->assertUnprocessable();
        }

        $this->assertSamePublicFailure(
            $this->timedReset('esgotado.existe@melo.test', '123456'),
            $this->timedReset('esgotado.nao@melo.test', '123456'),
        );
    }

    public function test_codigo_expirado_responde_igual_com_ou_sem_conta(): void
    {
        User::factory()->create(['email' => 'expirado.existe@melo.test', 'password' => 'senha1234']);
        $this->plant('expirado.existe@melo.test', '123456', VerificationCode::PASSWORD_RESET);
        VerificationCode::query()->where('email', 'expirado.existe@melo.test')->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->assertSamePublicFailure(
            $this->timedReset('expirado.existe@melo.test', '123456'),
            $this->timedReset('expirado.nao@melo.test', '123456'),
        );
    }

    public function test_codigo_novo_nao_libera_mais_palpites(): void
    {
        $this->freezeTime();
        Mail::fake();
        $email = 'limite.palpite@melo.test';
        User::factory()->create(['email' => $email, 'password' => 'senha-antiga']);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postReset($email, '000000')
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Código inválido');
        }

        $this->releasePasswordThrottle();
        $this->postJson('/api/auth/verification-codes', [
            'email' => $email,
            'purpose' => 'password_reset',
        ])->assertOk();

        $code = null;
        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return strlen($mail->code) === 6;
        });

        $this->postReset($email, (string) $code)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas. Tente novamente em 60 min.');

        $issued = VerificationCode::query()->where('email', $email)->whereNotNull('code_hash')->latest('id')->first();
        $this->assertNotNull($issued);
        $this->assertNull($issued->consumed_at);
        $this->assertTrue(Hash::check(
            'senha-antiga',
            User::query()->where('email', $email)->value('password'),
        ));
    }

    public function test_codigo_certo_passa_depois_de_nove_errados(): void
    {
        $email = 'nove.certo@melo.test';
        User::factory()->create(['email' => $email, 'password' => 'senha-antiga']);

        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->postReset($email, '000000')
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Código inválido');
        }

        $this->plant($email, '123456', VerificationCode::PASSWORD_RESET);

        $this->postReset($email, '123456')
            ->assertOk()
            ->assertJsonPath('message', 'Senha atualizada. Entre com a nova senha.');

        $this->assertTrue(Hash::check('senha-nova-1', User::query()->where('email', $email)->value('password')));
        $this->assertNotNull(
            VerificationCode::query()->where('email', $email)->whereNotNull('code_hash')->value('consumed_at'),
        );
    }

    public function test_decimo_primeiro_palpite_recebe_429_mesmo_com_o_codigo_certo(): void
    {
        $this->freezeTime();
        $email = 'onze.palpite@melo.test';
        User::factory()->create(['email' => $email, 'password' => 'senha-antiga']);
        $this->plant($email, '123456', VerificationCode::PASSWORD_RESET);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postReset($email, '000000')->assertUnprocessable();
        }

        $this->releasePasswordThrottle();

        $blocked = $this->postReset($email, '123456');
        $blocked->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Muitas tentativas. Tente novamente em 60 min.');
        $this->assertSame((int) $blocked->headers->get('Retry-After'), $blocked->json('retry_after'));

        $this->assertNull(VerificationCode::query()->where('email', $email)->value('consumed_at'));
        $this->assertTrue(Hash::check('senha-antiga', User::query()->where('email', $email)->value('password')));
    }

    public function test_trava_do_reset_nao_trava_o_convite(): void
    {
        $this->freezeTime();
        $email = 'reset.convite@melo.test';
        $plain = 'token-reset-convite';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postReset($email, '000000')->assertUnprocessable();
        }

        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        Invitation::query()->create([
            'company_id' => $company->id,
            'email' => $email,
            'role' => 'member',
            'status' => 'pending',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(7),
        ]);
        $this->plant($email, '654321', VerificationCode::INVITATION_REGISTER);
        $this->releasePasswordThrottle();

        $this->postJson('/api/auth/invitations/'.$plain.'/register', [
            'name' => 'Convidada',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => '654321',
        ])->assertCreated()
            ->assertJsonPath('user.email', $email);

        $this->postReset($email, '123456')
            ->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas. Tente novamente em 60 min.');
    }

    public function test_429_e_igual_com_ou_sem_conta(): void
    {
        $this->freezeTime();
        User::factory()->create(['email' => 'teto.existe@melo.test', 'password' => 'senha1234']);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postReset('teto.existe@melo.test', '000000')->assertUnprocessable();
        }

        $this->releasePasswordThrottle();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postReset('teto.nao@melo.test', '000000')->assertUnprocessable();
        }

        $this->releasePasswordThrottle();

        $known = $this->timedReset('teto.existe@melo.test', '123456');
        $unknown = $this->timedReset('teto.nao@melo.test', '123456');

        [$knownResponse, $knownMs] = $known;
        [$unknownResponse, $unknownMs] = $unknown;

        $knownResponse->assertStatus(429);
        $unknownResponse->assertStatus(429);
        $this->assertSame($knownResponse->status(), $unknownResponse->status());
        $this->assertSame($knownResponse->json(), $unknownResponse->json());
        $this->assertSame($knownResponse->headers->get('Retry-After'), $unknownResponse->headers->get('Retry-After'));
        $this->assertSame('Muitas tentativas. Tente novamente em 60 min.', $knownResponse->json('message'));
        $this->assertLessThan(1000, abs($knownMs - $unknownMs));
    }

    public function test_palpites_paralelos_nao_passam_de_dez_no_postgres(): void
    {
        if (config('database.default') !== 'pgsql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('Concorrência só roda no PostgreSQL.');
        }

        $email = 'paralelo.palpite@posto.test';
        $config = config('database.connections.pgsql');
        config(['database.connections.guess_outside' => $config]);
        DB::purge('guess_outside');
        $outside = DB::connection('guess_outside');

        $outside->table('verification_codes')->where('email', $email)->delete();
        $outside->table('verification_codes')->insert([
            'email' => $email,
            'purpose' => VerificationCode::STATION_SIGNUP,
            'code_hash' => hash('sha256', '999999'),
            'expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = json_encode([
            'cnpj' => self::CNPJ,
            'name' => 'Dona do Posto',
            'email' => $email,
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => '000000',
        ], JSON_THROW_ON_ERROR);

        $env = [];

        foreach (array_merge(getenv() ?: [], $_ENV) as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $env[$name] = (string) $value;
            }
        }

        $env = array_merge($env, [
            'APP_ENV' => 'testing',
            'CACHE_STORE' => 'file',
            'CACHE_PREFIX' => 'guess-'.bin2hex(random_bytes(8)),
            'DB_CONNECTION' => 'pgsql',
            'GUESS_PAYLOAD' => $payload,
        ]);

        try {
            $processes = [];

            for ($attempt = 0; $attempt < 12; $attempt++) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/parallel_guess.php')], base_path(), $env);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }

            $statuses = [];

            foreach ($processes as $process) {
                $process->wait();
                $statuses[] = (int) strtok($process->getOutput(), "\n");
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
            }

            $counts = array_count_values($statuses);
            $this->assertSame(10, $counts[422] ?? 0);
            $this->assertSame(2, $counts[429] ?? 0);
            $this->assertNull($outside->table('verification_codes')->where('email', $email)->value('consumed_at'));
        } finally {
            $outside->table('verification_codes')->where('email', $email)->delete();
        }
    }

    public function test_codigo_errado_expirado_esgotado_e_reutilizado(): void
    {
        $this->plant('nova@posto.test', '123456');

        $this->postJson('/api/auth/station-signup', $this->signup('000000'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        VerificationCode::query()->update(['expires_at' => now()->subMinute(), 'attempts' => 0]);

        $this->postJson('/api/auth/station-signup', $this->signup('123456'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        VerificationCode::query()->update([
            'expires_at' => now()->addMinutes(10),
            'attempts' => 4,
            'invalidated_at' => null,
        ]);

        $this->postJson('/api/auth/station-signup', $this->signup('000000'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        VerificationCode::query()->update([
            'attempts' => 0,
            'invalidated_at' => null,
            'consumed_at' => now(),
        ]);

        $this->postJson('/api/auth/station-signup', $this->signup('123456'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');
    }

    public function test_reenvio_espera_um_minuto_e_o_sexto_pedido_da_hora_para(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'nova@posto.test',
            'purpose' => 'station_signup',
        ])->assertOk()->assertJsonPath('retry_after', 60);

        $this->travel(10)->seconds();

        $again = $this->postJson('/api/auth/verification-codes', [
            'email' => 'nova@posto.test',
            'purpose' => 'station_signup',
        ])->assertOk();

        $this->assertSame(50, $again->json('retry_after'));
        $this->assertSame(1, VerificationCode::query()->count());

        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->travel(61)->seconds();
            $this->postJson('/api/auth/verification-codes', [
                'email' => 'nova@posto.test',
                'purpose' => 'station_signup',
            ])->assertOk();
        }

        Mail::assertQueued(VerificationCodeMail::class, 5);
        $this->travel(61)->seconds();

        $blocked = $this->postJson('/api/auth/verification-codes', [
            'email' => 'nova@posto.test',
            'purpose' => 'station_signup',
        ])->assertOk();

        $this->assertGreaterThan(60, $blocked->json('retry_after'));
        Mail::assertQueued(VerificationCodeMail::class, 5);
        $this->assertSame(5, VerificationCode::query()->count());
    }

    public function test_cadastro_de_posto_so_conclui_com_o_codigo_do_email(): void
    {
        Mail::fake();
        $this->fakeReceita();

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'nova@posto.test',
            'purpose' => 'station_signup',
        ])->assertOk();

        $code = null;
        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return strlen($mail->code) === 6;
        });

        $html = (new VerificationCodeMail($code, 10))->render();
        $this->assertStringContainsString(implode(' ', str_split($code)), $html);
        $this->assertStringContainsString('Se não foi você, ignore este e-mail.', $html);

        $this->postJson('/api/auth/station-signup', $this->signup($code))
            ->assertCreated()
            ->assertJsonPath('user.email', 'nova@posto.test');

        $this->assertNotNull(User::query()->where('email', 'nova@posto.test')->first()->email_verified_at);
        $this->assertNotNull(VerificationCode::query()->first()->consumed_at);
    }

    public function test_convite_novo_exige_codigo_e_o_envio_sem_token_nao_entrega(): void
    {
        Mail::fake();
        $plain = 'token-convite-teste';
        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        Invitation::query()->create([
            'company_id' => $company->id,
            'email' => 'dona@melo.test',
            'role' => 'owner',
            'status' => 'pending',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(7),
        ]);

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'dona@melo.test',
            'purpose' => 'invitation_register',
        ])->assertOk();

        Mail::assertNothingQueued();
        $this->travel(61)->seconds();

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'dona@melo.test',
            'purpose' => 'invitation_register',
            'token' => $plain,
        ])->assertOk();

        $code = null;
        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/api/auth/invitations/'.$plain.'/register', [
            'name' => 'Dona',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => $code,
            'is_system_admin' => true,
        ])->assertCreated()
            ->assertJsonPath('user.is_system_admin', false);

        $this->assertNotNull(User::query()->where('email', 'dona@melo.test')->first()->email_verified_at);
    }

    public function test_envio_publico_respeita_o_limite(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/auth/verification-codes', [
                'email' => 'alguem'.$attempt.'@posto.test',
                'purpose' => 'station_signup',
            ])->assertOk();
        }

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'mais-um@posto.test',
            'purpose' => 'station_signup',
        ])->assertStatus(429)
            ->assertJsonStructure(['retry_after']);
    }

    /**
     * @return array{0: \Illuminate\Testing\TestResponse, 1: float}
     */
    private function timedReset(string $email, string $code): array
    {
        $start = hrtime(true);
        $response = $this->postReset($email, $code);

        return [$response, (hrtime(true) - $start) / 1e6];
    }

    private function releasePasswordThrottle(): void
    {
        RateLimiter::clear('password-reset'.sha1('|127.0.0.1'));
    }

    private function postReset(string $email, string $code): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/auth/password/reset', [
            'email' => $email,
            'code' => $code,
            'password' => 'senha-nova-1',
            'password_confirmation' => 'senha-nova-1',
        ]);
    }

    /**
     * @param  array{0: \Illuminate\Testing\TestResponse, 1: float}  $known
     * @param  array{0: \Illuminate\Testing\TestResponse, 1: float}  $unknown
     */
    private function assertSamePublicFailure(array $known, array $unknown): void
    {
        [$knownResponse, $knownMs] = $known;
        [$unknownResponse, $unknownMs] = $unknown;

        $knownResponse->assertUnprocessable();
        $unknownResponse->assertUnprocessable();
        $this->assertSame($knownResponse->status(), $unknownResponse->status());
        $this->assertSame('Código inválido', $knownResponse->json('message'));
        $this->assertSame($knownResponse->json('message'), $unknownResponse->json('message'));
        $this->assertLessThan(1000, abs($knownMs - $unknownMs));
    }

    /**
     * @return array<string, string>
     */
    private function signup(string $code): array
    {
        return [
            'cnpj' => self::CNPJ,
            'name' => 'Dona do Posto',
            'email' => 'nova@posto.test',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => $code,
        ];
    }

    private function plant(string $email, string $code, string $purpose = VerificationCode::STATION_SIGNUP): void
    {
        VerificationCode::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function fakeReceita(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'brasilapi.com.br/api/cnpj/v1/*' => Http::response([
                'cnpj' => self::CNPJ,
                'razao_social' => 'POSTO EXEMPLO LTDA',
                'nome_fantasia' => 'POSTO EXEMPLO',
                'descricao_situacao_cadastral' => 'ATIVA',
                'cep' => '01310930',
                'uf' => 'SP',
                'municipio' => 'SAO PAULO',
            ], 200),
        ]);
    }
}
