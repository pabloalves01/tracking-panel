<?php

namespace Tests\Feature\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_pedido_de_senha_nao_revela_se_a_conta_existe(): void
    {
        Mail::fake();
        User::factory()->create([
            'email' => 'dona@melo.test',
            'password' => 'senha-antiga',
        ]);

        $known = $this->postJson('/api/auth/password/forgot', [
            'email' => ' Dona@Melo.TEST ',
        ])->assertOk();

        $unknown = $this->postJson('/api/auth/password/forgot', [
            'email' => 'ninguem@melo.test',
        ])->assertOk();

        $this->assertSame($known->json(), $unknown->json());
        Mail::assertQueued(VerificationCodeMail::class, 1);
        Mail::assertQueued(VerificationCodeMail::class, fn (VerificationCodeMail $mail) => $mail->hasTo('dona@melo.test'));
        $this->assertNull(VerificationCode::query()->where('email', 'ninguem@melo.test')->value('code_hash'));
    }

    public function test_codigo_do_email_troca_a_senha_uma_vez(): void
    {
        Mail::fake();
        $user = User::factory()->create([
            'email' => 'dona@melo.test',
            'password' => 'senha-antiga',
            'email_verified_at' => null,
        ]);

        $oldToken = $user->createToken('web')->plainTextToken;

        $this->postJson('/api/auth/password/forgot', [
            'email' => 'dona@melo.test',
        ])->assertOk();

        $code = null;
        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return true;
        });

        $this->postJson('/api/auth/password/reset', [
            'email' => 'dona@melo.test',
            'code' => $code,
            'password' => 'senha-nova-1',
            'password_confirmation' => 'senha-nova-1',
        ])->assertOk()
            ->assertJsonPath('message', 'Senha atualizada. Entre com a nova senha.');

        $this->assertTrue(Hash::check('senha-nova-1', $user->fresh()->password));
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertSame(0, $user->tokens()->count());

        $this->withToken($oldToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();

        $this->flushHeaders();

        $this->postJson('/api/auth/login', [
            'email' => 'dona@melo.test',
            'password' => 'senha-antiga',
        ])->assertUnauthorized();

        $this->postJson('/api/auth/login', [
            'email' => 'dona@melo.test',
            'password' => 'senha-nova-1',
        ])->assertOk();

        $this->postJson('/api/auth/password/reset', [
            'email' => 'dona@melo.test',
            'code' => $code,
            'password' => 'outra-senha-2',
            'password_confirmation' => 'outra-senha-2',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');
    }

    public function test_codigo_errado_nao_troca_a_senha(): void
    {
        $user = User::factory()->create([
            'email' => 'dona@melo.test',
            'password' => 'senha-antiga',
        ]);
        VerificationCode::query()->create([
            'email' => 'dona@melo.test',
            'purpose' => VerificationCode::PASSWORD_RESET,
            'code_hash' => hash('sha256', '123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->postJson('/api/auth/password/reset', [
            'email' => 'dona@melo.test',
            'code' => '000000',
            'password' => 'senha-nova-1',
            'password_confirmation' => 'senha-nova-1',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        $this->assertTrue(Hash::check('senha-antiga', $user->fresh()->password));
    }
}
