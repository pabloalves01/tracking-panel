<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_cria_a_conta_sem_contexto_e_ja_entra(): void
    {
        $response = $this->postJson('/api/auth/register', $this->payload())
            ->assertCreated()
            ->assertJsonPath('user.name', 'Pablo Alves')
            ->assertJsonPath('user.email', 'pablo@logiv.com.br')
            ->assertJsonPath('user.is_system_admin', false)
            ->assertJsonPath('user.has_company', false)
            ->assertJsonPath('user.has_fuel_station', false)
            ->assertJsonPath('companies', [])
            ->assertJsonPath('fuel_stations', [])
            ->assertJsonMissingPath('user.password');

        $user = User::query()->sole();
        $this->assertTrue(Hash::check('senha1234', $user->password));
        $this->assertFalse($user->is_system_admin);
        $this->assertSame(0, $user->companies()->count());
        $this->assertSame(0, $user->fuelStations()->count());

        $token = $user->tokens()->sole();
        $this->assertNull($token->company_id);
        $this->assertNull($token->fuel_station_id);

        $this->withToken($response->json('token'))->getJson('/api/auth/contexts')
            ->assertOk()
            ->assertJsonCount(0, 'companies')
            ->assertJsonCount(0, 'fuel_stations');

        $this->withToken($response->json('token'))->getJson('/api/registry/entities')->assertStatus(409);
        $this->withToken($response->json('token'))->getJson('/api/station/me')->assertStatus(409);
        $this->withToken($response->json('token'))->getJson('/api/system/companies')->assertForbidden();
    }

    public function test_email_em_maiusculas_e_gravado_em_minusculas(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['email' => ' Pablo@LOGIV.com.br ']))
            ->assertCreated()
            ->assertJsonPath('user.email', 'pablo@logiv.com.br');

        $this->assertSame('pablo@logiv.com.br', User::query()->sole()->email);

        $this->postJson('/api/auth/login', [
            'email' => 'PABLO@logiv.com.br',
            'password' => 'senha1234',
        ])->assertOk();
    }

    public function test_email_que_ja_tem_conta_nao_cria_outra(): void
    {
        User::factory()->create([
            'name' => 'Conta Antiga',
            'email' => 'pablo@logiv.com.br',
            'password' => 'senha-antiga',
        ]);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'PABLO@logiv.com.br']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Este e-mail já tem conta. Entre com ele']);

        $user = User::query()->sole();
        $this->assertSame('Conta Antiga', $user->name);
        $this->assertTrue(Hash::check('senha-antiga', $user->password));
    }

    public function test_valida_os_campos(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name' => 'O nome é obrigatório',
                'email' => 'O e-mail é obrigatório',
                'password' => 'A senha é obrigatória',
            ]);

        $this->postJson('/api/auth/register', $this->payload([
            'email' => 'nao-e-email',
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'Informe um e-mail válido',
                'password' => 'A senha deve ter no mínimo 8 caracteres',
            ]);

        $this->postJson('/api/auth/register', $this->payload([
            'password_confirmation' => 'outra-senha',
        ]))->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'A confirmação da senha não confere']);

        $this->assertSame(0, User::query()->count());
    }

    public function test_nao_aceita_virar_admin_do_sistema_pelo_cadastro(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['is_system_admin' => true]))
            ->assertCreated()
            ->assertJsonPath('user.is_system_admin', false);

        $this->assertFalse(User::query()->sole()->is_system_admin);
    }

    public function test_cadastro_respeita_o_limite(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/auth/register', [])->assertUnprocessable();
        }

        $this->postJson('/api/auth/register', [])->assertStatus(429);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Pablo Alves',
            'email' => 'pablo@logiv.com.br',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ], $overrides);
    }
}
