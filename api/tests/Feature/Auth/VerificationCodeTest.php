<?php

namespace Tests\Feature\Auth;

use App\Mail\StationSignupExistingAccountMail;
use App\Mail\VerificationCodeMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
            ->assertJsonPath('message', 'Tentativas esgotadas');

        $this->assertSame(5, VerificationCode::query()->where('email', 'dona@melo.test')->value('attempts'));
        $this->assertNotNull(VerificationCode::query()->where('email', 'dona@melo.test')->value('invalidated_at'));

        $this->postJson('/api/auth/password/reset', [
            'email' => 'dona@melo.test',
            'code' => '123456',
            'password' => 'senha-nova-1',
            'password_confirmation' => 'senha-nova-1',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Tentativas esgotadas');

        $this->assertNull(VerificationCode::query()->where('email', 'dona@melo.test')->value('consumed_at'));
        $this->assertNull(User::query()->where('email', 'dona@melo.test')->first());
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
            ->assertJsonPath('message', 'Este código expirou');

        VerificationCode::query()->update([
            'expires_at' => now()->addMinutes(10),
            'attempts' => 4,
            'invalidated_at' => null,
        ]);

        $this->postJson('/api/auth/station-signup', $this->signup('000000'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tentativas esgotadas');

        VerificationCode::query()->update([
            'attempts' => 0,
            'invalidated_at' => null,
            'consumed_at' => now(),
        ]);

        $this->postJson('/api/auth/station-signup', $this->signup('123456'))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este código já foi usado');
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
