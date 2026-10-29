<?php

namespace Tests\Feature\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\Auth\VerificationCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class VerificationCodeTest extends TestCase
{
    use RefreshDatabase;


    /**
     * O código de cadastro chega sempre, exista a conta ou não — quem decide
     * entre entrar e criar é a conferência, depois. A recuperação de senha só
     * entrega para conta existente. Nas duas, a resposta é a mesma para os
     * dois casos.
     */
    public function test_envio_publico_nao_revela_se_o_email_existe(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ja@posto.test', 'password' => 'senha1234']);

        foreach ([VerificationCode::ACCOUNT_REGISTER, VerificationCode::PASSWORD_RESET] as $purpose) {
            $this->travel(61)->seconds();

            $known = $this->postJson('/api/auth/verification-codes', [
                'email' => ' JA@posto.test ',
                'purpose' => $purpose,
            ])->assertOk();

            $unknown = $this->postJson('/api/auth/verification-codes', [
                'email' => 'nova@posto.test',
                'purpose' => $purpose,
            ])->assertOk();

            $this->assertSame($known->json(), $unknown->json());
            $this->assertSame('Enviamos um código para este e-mail.', $known->json('message'));
        }

        // Cadastro: os dois. Recuperação: só quem tem conta.
        Mail::assertQueued(VerificationCodeMail::class, 3);
        $this->assertNotNull($this->codeHash('ja@posto.test', VerificationCode::ACCOUNT_REGISTER));
        $this->assertNotNull($this->codeHash('nova@posto.test', VerificationCode::ACCOUNT_REGISTER));
        $this->assertNotNull($this->codeHash('ja@posto.test', VerificationCode::PASSWORD_RESET));
        $this->assertNull($this->codeHash('nova@posto.test', VerificationCode::PASSWORD_RESET));
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

    public function test_trava_do_reset_nao_trava_o_cadastro(): void
    {
        $this->freezeTime();
        $email = 'reset.cadastro@melo.test';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postReset($email, '000000')->assertUnprocessable();
        }

        $this->plant($email, '654321');
        $this->releasePasswordThrottle();

        $this->register($email, '654321')
            ->assertCreated()
            ->assertJsonPath('user.email', $email);

        // O antigo orçamento por e-mail travava a recuperação de senha da
        // vítima por uma hora e saiu no ADR-004. O que importa aqui é que
        // os palpites no reset não contaminaram o cadastro.
        $this->postReset($email, '123456')->assertUnprocessable();
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

    /**
     * O limite é por código, não por e-mail.
     *
     * O orçamento por e-mail saiu de propósito: um terceiro que soubesse o
     * endereço da vítima podia gastá-lo com palpites e trancar a
     * recuperação de senha dela por uma hora. Cada código morre depois de
     * cinco erros, e pedir outro recomeça a contagem — o que machuca quem
     * chuta, não quem é alvo.
     */
    public function test_palpites_gastam_o_codigo_e_nao_o_endereco(): void
    {
        $email = 'alvo@posto.test';
        $this->plant($email, '123456');

        for ($erro = 0; $erro < VerificationCodeService::MAX_ATTEMPTS; $erro++) {
            $this->register($email, '000000')->assertUnprocessable();
        }

        // O código certo já não vale: aquele código morreu.
        $this->register($email, '123456')->assertUnprocessable();

        // Mas um código novo para o mesmo e-mail volta a funcionar.
        $this->plant($email, '654321');
        $this->register($email, '654321')->assertCreated();
    }

    public function test_codigo_errado_expirado_esgotado_e_reutilizado(): void
    {
        $this->plant('nova@posto.test', '123456');

        $this->register('nova@posto.test', '000000')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        VerificationCode::query()->update(['expires_at' => now()->subMinute(), 'attempts' => 0]);

        $this->register('nova@posto.test', '123456')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        VerificationCode::query()->update([
            'expires_at' => now()->addMinutes(10),
            'attempts' => 4,
            'invalidated_at' => null,
        ]);

        $this->register('nova@posto.test', '000000')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        VerificationCode::query()->update([
            'attempts' => 0,
            'invalidated_at' => null,
            'consumed_at' => now(),
        ]);

        $this->register('nova@posto.test', '123456')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        $this->assertDatabaseMissing('users', ['email' => 'nova@posto.test']);
    }

    public function test_reenvio_espera_um_minuto_e_o_sexto_pedido_da_hora_para(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'nova@posto.test',
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
        ])->assertOk()->assertJsonPath('retry_after', 60);

        $this->travel(10)->seconds();

        $again = $this->postJson('/api/auth/verification-codes', [
            'email' => 'nova@posto.test',
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
        ])->assertOk();

        $this->assertSame(50, $again->json('retry_after'));
        $this->assertSame(1, VerificationCode::query()->count());

        for ($attempt = 1; $attempt < 5; $attempt++) {
            $this->travel(61)->seconds();
            $this->postJson('/api/auth/verification-codes', [
                'email' => 'nova@posto.test',
                'purpose' => VerificationCode::ACCOUNT_REGISTER,
            ])->assertOk();
        }

        Mail::assertQueued(VerificationCodeMail::class, 5);
        $this->travel(61)->seconds();

        $blocked = $this->postJson('/api/auth/verification-codes', [
            'email' => 'nova@posto.test',
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
        ])->assertOk();

        $this->assertGreaterThan(60, $blocked->json('retry_after'));
        Mail::assertQueued(VerificationCodeMail::class, 5);
        $this->assertSame(5, VerificationCode::query()->count());
    }

    public function test_cadastro_de_conta_so_conclui_com_o_codigo_do_email(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'nova@posto.test',
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
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

        $this->register('nova@posto.test', $code)
            ->assertCreated()
            ->assertJsonPath('user.email', 'nova@posto.test');

        $this->assertNotNull(User::query()->where('email', 'nova@posto.test')->first()->email_verified_at);
        $this->assertNotNull(VerificationCode::query()->first()->consumed_at);
    }

    /**
     * As finalidades que criavam conta junto com posto ou convite saíram:
     * conta se cria por um caminho só.
     */
    public function test_finalidades_antigas_sao_recusadas(): void
    {
        Mail::fake();

        foreach (['fuel_station_signup', 'invitation_register', 'qualquer'] as $purpose) {
            $this->postJson('/api/auth/verification-codes', [
                'email' => 'nova@posto.test',
                'purpose' => $purpose,
            ])->assertUnprocessable()->assertJsonValidationErrors('purpose');
        }

        Mail::assertNothingQueued();
    }

    public function test_codigo_de_outra_finalidade_nao_serve_para_o_cadastro(): void
    {
        $this->plant('nova@posto.test', '111111', VerificationCode::PASSWORD_RESET);

        $this->register('nova@posto.test', '111111')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        $this->assertDatabaseMissing('users', ['email' => 'nova@posto.test']);
    }

    public function test_cadastro_nao_vira_admin_do_sistema_pelo_payload(): void
    {
        $this->plant('nova@posto.test', '123456');

        $this->postJson('/api/auth/register', [
            ...$this->registerPayload('nova@posto.test', '123456'),
            'is_system_admin' => true,
        ])->assertCreated()
            ->assertJsonPath('user.is_system_admin', false);
    }

    public function test_envio_publico_respeita_o_limite(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson('/api/auth/verification-codes', [
                'email' => 'alguem'.$attempt.'@posto.test',
                'purpose' => VerificationCode::ACCOUNT_REGISTER,
            ])->assertOk();
        }

        $this->postJson('/api/auth/verification-codes', [
            'email' => 'mais-um@posto.test',
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
        ])->assertStatus(429)
            ->assertJsonStructure(['retry_after']);
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

    private function register(string $email, string $code): TestResponse
    {
        return $this->postJson('/api/auth/register', $this->registerPayload($email, $code));
    }

    /**
     * @return array<string, string>
     */
    private function registerPayload(string $email, string $code): array
    {
        return [
            'email' => $email,
            'code' => $code,
            'name' => 'Pessoa de Teste',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ];
    }

    private function codeHash(string $email, string $purpose): ?string
    {
        return VerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->latest('id')
            ->value('code_hash');
    }

    private function plant(string $email, string $code, string $purpose = VerificationCode::ACCOUNT_REGISTER): void
    {
        VerificationCode::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

}
