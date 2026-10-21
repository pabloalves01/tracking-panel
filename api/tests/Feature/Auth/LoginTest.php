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
}
