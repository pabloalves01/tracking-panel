<?php

namespace Tests\Feature\Auth;

use App\Mail\StationSignupExistingAccountMail;
use App\Mail\VerificationCodeMail;
use App\Models\Company;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Invitation;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
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

    public function test_codigo_novo_volta_a_valer_depois_de_palpites_errados(): void
    {
        // O orçamento de palpites por e-mail saiu: ele travava a troca de
        // senha por uma hora, e quem o gastava não precisava ser o dono da
        // conta — bastava saber o e-mail. Quem limita agora é o contador por
        // código, que morre com o código, mais o teto por IP.
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

        $this->postReset($email, (string) $code)->assertOk();

        $this->assertNotNull(
            VerificationCode::query()->where('email', $email)->latest('id')->value('consumed_at'),
        );
        $this->assertTrue(Hash::check(
            'senha-nova-1',
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

    public function test_cinco_erros_matam_o_codigo_e_o_certo_para_de_valer(): void
    {
        // O contador vive no código, não no e-mail: gasto o orçamento, nem o
        // código certo passa — mas pedir outro resolve, e é isso que separa
        // esta defesa de uma negação de serviço contra o dono da conta.
        $this->freezeTime();
        $email = 'onze.palpite@melo.test';
        User::factory()->create(['email' => $email, 'password' => 'senha-antiga']);
        $this->plant($email, '123456', VerificationCode::PASSWORD_RESET);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postReset($email, '000000')->assertUnprocessable();
        }

        $this->postReset($email, '123456')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        $this->assertNotNull(VerificationCode::query()->where('email', $email)->value('invalidated_at'));
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

    public function test_teto_por_ip_responde_429_igual_com_ou_sem_conta(): void
    {
        // O teto por IP continua, e é o que segura a força bruta de quem
        // varre códigos. A resposta não pode dizer se a conta existe.
        $this->freezeTime();
        User::factory()->create(['email' => 'teto.existe@melo.test', 'password' => 'senha1234']);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postReset('teto.existe@melo.test', '000000')->assertUnprocessable();
        }

        $known = $this->timedReset('teto.existe@melo.test', '123456');
        $this->releasePasswordThrottle();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postReset('teto.nao@melo.test', '000000')->assertUnprocessable();
        }

        $unknown = $this->timedReset('teto.nao@melo.test', '123456');

        [$knownResponse, $knownMs] = $known;
        [$unknownResponse, $unknownMs] = $unknown;

        $knownResponse->assertStatus(429);
        $unknownResponse->assertStatus(429);
        $this->assertSame($knownResponse->json(), $unknownResponse->json());
        $this->assertSame($knownResponse->headers->get('Retry-After'), $unknownResponse->headers->get('Retry-After'));
        $this->assertLessThan(1000, abs($knownMs - $unknownMs));
    }

    public function test_palpites_paralelos_nao_passam_de_dez_no_postgres(): void
    {
        if (config('database.default') !== 'pgsql' || ! function_exists('proc_open')) {
            $this->markTestSkipped('Concorrência só roda no PostgreSQL.');
        }

        $email = 'paralelo.'.bin2hex(random_bytes(6)).'@posto.test';
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
            'DB_CONNECTION' => 'pgsql',
            'GUESS_PAYLOAD' => $payload,
            'GUESS_IP' => '10.'.random_int(0, 255).'.'.random_int(0, 255).'.'.random_int(1, 254),
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
            $summary = json_encode($statuses);
            $this->assertSame(10, $counts[422] ?? 0, $summary);
            $this->assertSame(2, $counts[429] ?? 0, $summary);
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
        // O código vai inteiro no HTML: o espaçamento visual é do
        // `letter-spacing`, e dígitos separados por espaço quebravam
        // o copiar e colar.
        $this->assertStringContainsString($code, $html);
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

    public function test_cadastro_por_convite_envia_codigo_mesmo_com_codigo_de_outra_finalidade(): void
    {
        Mail::fake();
        $stationToken = $this->inviteLink('posto.novo@logiv.test', station: true);
        $companyToken = $this->inviteLink('empresa.nova@melo.test', station: false);

        foreach ([
            ['posto.novo@logiv.test', $stationToken, 'station'],
            ['empresa.nova@melo.test', $companyToken, 'company'],
        ] as [$email, $plain, $kind]) {
            VerificationCode::query()->create([
                'email' => $email,
                'purpose' => VerificationCode::STATION_SIGNUP,
                'code_hash' => hash('sha256', '111111'),
                'expires_at' => now()->addMinutes(10),
            ]);
            VerificationCode::query()->create([
                'email' => $email,
                'purpose' => VerificationCode::INVITATION_REGISTER,
                'code_hash' => null,
                'expires_at' => null,
            ]);

            $sent = $this->postJson('/api/auth/verification-codes', [
                'email' => $email,
                'purpose' => 'invitation_register',
                'token' => $plain,
            ])->assertOk()
                ->assertJsonPath('sent', true)
                ->assertJsonPath('account_exists', false)
                ->assertJsonPath('message', 'Enviamos um código para este e-mail.');

            $this->assertSame(60, $sent->json('retry_after'));

            $again = $this->postJson('/api/auth/verification-codes', [
                'email' => $email,
                'purpose' => 'invitation_register',
                'token' => $plain,
            ])->assertOk()
                ->assertJsonPath('sent', false)
                ->assertJsonPath('account_exists', false);

            $this->assertStringContainsString('Aguarde', (string) $again->json('message'));
            $this->assertGreaterThan(0, $again->json('retry_after'));
            $this->assertLessThanOrEqual(60, $again->json('retry_after'));

            $code = null;
            Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code, $email) {
                if (! $mail->hasTo($email)) {
                    return false;
                }

                $code = $mail->code;

                return true;
            });

            $this->postJson('/api/auth/invitations/'.$plain.'/register', [
                'name' => 'Marcos',
                'password' => 'senha1234',
                'password_confirmation' => 'senha1234',
                'code' => $code,
            ])->assertCreated()
                ->assertJsonPath('user.email', $email);

            $user = User::query()->where('email', $email)->first();
            $this->assertNotNull($user->email_verified_at);

            if ($kind === 'station') {
                $this->assertTrue(FuelStationUser::query()->where('user_id', $user->id)->where('role', 'member')->where('status', 'active')->exists());
            } else {
                $this->assertTrue($user->companies()->where('companies.cnpj', '00000000000191')->exists());
            }
        }

        Mail::assertQueued(VerificationCodeMail::class, 2);
    }

    public function test_convite_de_conta_existente_nao_finge_o_envio(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'dona@melo.test', 'password' => 'senha1234']);
        $plain = $this->inviteLink('dona@melo.test', station: false);

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'dona@melo.test',
            'purpose' => 'invitation_register',
        ])->assertOk()
            ->assertJsonPath('sent', true)
            ->assertJsonPath('account_exists', false)
            ->assertJsonPath('message', 'Enviamos um código para este e-mail.');

        Mail::assertNotQueued(VerificationCodeMail::class);

        $this->travel(61)->seconds();

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'dona@melo.test',
            'purpose' => 'invitation_register',
            'token' => $plain,
        ])->assertOk()
            ->assertJsonPath('sent', false)
            ->assertJsonPath('account_exists', true)
            ->assertJsonPath('message', 'Este e-mail já tem conta. Entre para aceitar o convite.');

        Mail::assertNotQueued(VerificationCodeMail::class);
        $this->assertNull(
            VerificationCode::query()
                ->where('email', 'dona@melo.test')
                ->where('purpose', VerificationCode::INVITATION_REGISTER)
                ->whereNotNull('code_hash')
                ->value('code_hash'),
        );
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
     * @return array{0: TestResponse, 1: float}
     */
    private function inviteLink(string $email, bool $station): string
    {
        $plain = $station ? 'token-posto-novo' : 'token-empresa-nova';

        if ($station) {
            $place = FuelStation::query()->create([
                'cnpj' => '34028316000103',
                'corporate_name' => 'SIM REDE DE POSTOS',
                'status' => 'active',
            ]);
            Invitation::query()->create([
                'fuel_station_id' => $place->id,
                'email' => $email,
                'role' => 'member',
                'status' => 'pending',
                'token_hash' => hash('sha256', $plain),
                'expires_at' => now()->addDays(7),
            ]);

            return $plain;
        }

        $place = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        Invitation::query()->create([
            'company_id' => $place->id,
            'email' => $email,
            'role' => 'member',
            'status' => 'pending',
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addDays(7),
        ]);

        return $plain;
    }

    /**
     * @return array{0: TestResponse, 1: float}
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

    private function postReset(string $email, string $code): TestResponse
    {
        return $this->postJson('/api/auth/password/reset', [
            'email' => $email,
            'code' => $code,
            'password' => 'senha-nova-1',
            'password_confirmation' => 'senha-nova-1',
        ]);
    }

    /**
     * @param  array{0: TestResponse, 1: float}  $known
     * @param  array{0: TestResponse, 1: float}  $unknown
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
