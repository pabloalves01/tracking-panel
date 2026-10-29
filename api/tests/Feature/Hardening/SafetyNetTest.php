<?php

namespace Tests\Feature\Hardening;

use App\Models\Organization;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * As garantias que não dependem de como o domínio está modelado.
 *
 * Entrada malformada responde 422 em vez de 500, as rotas que disparam
 * e-mail têm limite, e o que é de desenvolvimento não sobe em produção.
 * Sobreviveu à troca de `companies` + `fuel_stations` por `organizations`
 * porque nada aqui fala de papel nem de tipo.
 */
class SafetyNetTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    /**
     * Um array onde se espera texto faz o PHP quebrar lá dentro se a
     * validação não pegar antes — e o usuário recebe 500 com o SQL junto.
     */
    public function test_lixo_nos_campos_responde_422_e_nao_500(): void
    {
        $gigante = str_repeat('a', 400);

        $this->postJson('/api/auth/login', [
            'email' => ['dona@exemplo.com.br'],
            'password' => [$gigante],
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'email' => 'dona@exemplo.com.br',
            'password' => $gigante,
        ])->assertUnprocessable();

        $this->postJson('/api/auth/register', [
            'name' => [$gigante],
            'email' => ['nao@posto.test'],
            'password' => $gigante.$gigante,
            'password_confirmation' => [$gigante],
            'code' => ['123456'],
        ])->assertUnprocessable();

        $this->postJson('/api/auth/register/check', [
            'email' => ['nao@posto.test'],
            'code' => ['123456'],
        ])->assertUnprocessable();

        $this->postJson('/api/auth/verification-codes', [
            'email' => ['nao@posto.test'],
            'purpose' => ['account_register'],
        ])->assertUnprocessable();

        $this->postJson('/api/auth/password/forgot', ['email' => ['x@y.test']])
            ->assertUnprocessable();

        $this->postJson('/api/auth/password/reset', [
            'email' => ['x@y.test'],
            'code' => ['123456'],
            'password' => $gigante,
            'password_confirmation' => $gigante,
        ])->assertUnprocessable();

        $this->getJson('/api/auth/fuel-station-signup/cnpj/123')->assertUnprocessable();
    }

    public function test_token_de_convite_inexistente_e_404_nao_500(): void
    {
        $this->getJson('/api/auth/invitations/'.str_repeat('x', 80))->assertNotFound();
        $this->postJson('/api/auth/invitations/nao-existe/decline')->assertNotFound();
    }

    public function test_lixo_nas_rotas_autenticadas_responde_422(): void
    {
        $this->actingAsMemberOf();

        $this->postJson('/api/auth/organization', ['uuid' => ['nao-uuid']])
            ->assertUnprocessable();

        $this->getJson('/api/registry/fuel-stations/by-document?document[]=19131243000197')
            ->assertUnprocessable();

        $this->postJson('/api/registry/fuel-stations', ['document' => ['19131243000197']])
            ->assertUnprocessable();

        $this->postJson('/api/registry/drivers', [
            'kind' => 'individual',
            'name' => 'Motorista',
            'document' => ['52998224725'],
            'license_number' => '123',
            'license_category' => 'B',
        ])->assertUnprocessable();
    }

    /**
     * Ninguém vira admin do sistema mandando o campo junto: `is_system_admin`
     * não está em nenhum `$fillable` nem em nenhuma FormRequest.
     */
    public function test_nao_se_vira_admin_do_sistema_pelo_payload(): void
    {
        $this->actingAsMemberOf();

        $this->postJson('/api/organization/invitations', [
            'email' => 'novo@exemplo.com.br',
            'is_system_admin' => true,
        ]);

        $this->assertFalse(
            User::query()->where('email', 'novo@exemplo.com.br')->exists()
                && User::query()->where('email', 'novo@exemplo.com.br')->value('is_system_admin')
        );
        $this->assertFalse($this->member->fresh()->is_system_admin);
    }

    public function test_rotas_que_disparam_email_tem_limite(): void
    {
        $uris = [
            'api/organization/invitations',
            'api/organization/invitations/{uuid}/resend',
            'api/auth/verification-codes',
            'api/auth/password/forgot',
            'api/auth/register',
            'api/auth/register/check',
            'api/registry/fuel-stations',
        ];

        foreach ($uris as $uri) {
            $route = collect(Route::getRoutes())->first(
                fn ($route) => $route->uri() === $uri && in_array('POST', $route->methods(), true),
            );

            $this->assertNotNull($route, "rota ausente: {$uri}");
            $this->assertTrue(
                collect($route->gatherMiddleware())
                    ->contains(fn ($middleware) => str_starts_with((string) $middleware, 'throttle:')),
                "sem throttle: {$uri}",
            );
        }
    }

    public function test_limite_de_login_responde_429_com_o_tempo(): void
    {
        User::factory()->create(['email' => 'dona@exemplo.com.br']);

        $status = null;

        for ($tentativa = 0; $tentativa < 25; $tentativa++) {
            $response = $this->postJson('/api/auth/login', [
                'email' => 'dona@exemplo.com.br',
                'password' => 'errada',
            ]);

            if ($response->getStatusCode() === 429) {
                $status = $response;
                break;
            }
        }

        $this->assertNotNull($status, 'o login precisa ter limite');
        $status->assertJsonStructure(['message', 'retry_after']);
        $this->assertGreaterThan(0, $status->json('retry_after'));
    }

    // ============================== PRODUÇÃO ==============================

    public function test_mailer_de_log_nao_sobe_em_producao(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['mail.default' => 'log']);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('MAIL_MAILER=log');
            (new AppServiceProvider($this->app))->boot();
        } finally {
            config(['mail.default' => 'array']);
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_seeder_de_demonstracao_fica_fora_de_producao(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $seeder = new DatabaseSeeder;
        $seeder->setContainer($this->app);

        try {
            $seeder->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertTrue((bool) User::query()->sole()->is_system_admin);
    }
}
