<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_com_email_em_maiusculas(): void
    {
        User::factory()->create([
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => ' DONO@POSTO.COM ',
            'password' => 'senha-atual',
        ])->assertOk()
            ->assertJsonPath('user.email', 'dono@posto.com');
    }

    public function test_senha_errada_retorna_401(): void
    {
        User::factory()->create([
            'email' => 'dono@posto.com',
            'password' => 'senha-atual',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'dono@posto.com',
            'password' => 'errada',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'E-mail ou senha inválidos.');
    }

    public function test_login_respeita_o_limite(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'ninguem@posto.com',
                'password' => 'errada',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'ninguem@posto.com',
            'password' => 'errada',
        ])->assertStatus(429);
    }
}
