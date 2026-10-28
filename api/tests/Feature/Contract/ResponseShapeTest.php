<?php

namespace Tests\Feature\Contract;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\Registry\Entity;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * O envelope de cada resposta, contra o que o client desembrulha.
 *
 * Dois bugs chegaram ao usuário por aqui: a consulta de CNPJ passou a vir
 * dentro de `data` e a tela continuou lendo o corpo direto, e `me` devolve
 * o corpo plano enquanto a tela de convite lia `data.user`. Nenhum dos dois
 * aparece em teste de serviço — só na junta entre as duas pontas.
 *
 * A lista abaixo é o contrato: `true` é resposta embrulhada em `data`
 * (o client lê `response.data.data`), `false` é corpo plano
 * (`response.data`). Mudar um lado sem o outro quebra este teste.
 */
class ResponseShapeTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    public function test_endpoints_de_sessao_tem_o_corpo_plano(): void
    {
        $this->actingAsMemberOf();

        foreach (['/api/auth/me', '/api/auth/organizations'] as $uri) {
            $body = $this->getJson($uri)->assertOk()->json();

            $this->assertArrayNotHasKey('data', $body, "{$uri} passou a vir dentro de data");
        }

        $this->assertArrayHasKey('user', $this->getJson('/api/auth/me')->json());
        $this->assertArrayHasKey('current_organization', $this->getJson('/api/auth/me')->json());
        $this->assertArrayHasKey('organizations', $this->getJson('/api/auth/organizations')->json());
    }

    public function test_colecoes_e_recursos_vem_dentro_de_data(): void
    {
        $this->actingAsMemberOf();

        // Pela própria API: o model tem escopo por organização e não pode
        // ser criado fora do contexto de uma requisição.
        $this->postJson('/api/registry/entities/customer', [
            'kind' => Entity::INDIVIDUAL,
            'document' => '52998224725',
            'name' => 'Fulano de Tal',
        ])->assertCreated();

        $envelopadas = [
            '/api/organization/members',
            '/api/organization/invitations',
            '/api/registry/entities',
            '/api/registry/entities/customer',
            '/api/registry/fuel-stations',
            '/api/locations/states',
        ];

        foreach ($envelopadas as $uri) {
            $body = $this->getJson($uri)->assertOk()->json();

            $this->assertArrayHasKey('data', $body, "{$uri} deixou de vir dentro de data");
        }
    }

    public function test_consulta_de_cnpj_vem_dentro_de_data(): void
    {
        Http::fake(['brasilapi.com.br/api/cnpj/v1/*' => Http::response([
            'cnpj' => '19131243000197',
            'razao_social' => 'POSTO EXEMPLO LTDA',
            'descricao_situacao_cadastral' => 'ATIVA',
            'situacao_cadastral' => 2,
            'uf' => 'SP',
            'municipio' => 'SAO PAULO',
        ])]);

        $body = $this->getJson('/api/auth/fuel-station-signup/cnpj/19131243000197')->assertOk()->json();

        // A tela lê `response.data.data.legal_name` — sem o envelope ela
        // preenche o formulário com undefined e nada acontece ao consultar.
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('legal_name', $body['data']);
        $this->assertArrayHasKey('document', $body['data']);
    }

    public function test_preview_do_convite_vem_dentro_de_data(): void
    {
        $this->actingAsMemberOf();

        $created = $this->postJson('/api/organization/invitations', ['email' => 'novo@exemplo.com.br'])
            ->assertCreated()
            ->json();

        // O client lê `response.data.invitation` ao criar e
        // `response.data.data` no preview público.
        $this->assertArrayHasKey('data', $created);
        $this->assertArrayHasKey('invite_url', $created['data']);
    }

    public function test_login_e_troca_de_organizacao_devolvem_o_que_o_client_usa(): void
    {
        $user = User::factory()->create(['email' => 'contrato@exemplo.com.br']);
        $organization = Organization::factory()->company()->create();
        Membership::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'status' => Membership::ACTIVE,
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'contrato@exemplo.com.br',
            'password' => 'mesquita2016',
        ])->assertOk();

        // `login()` grava `response.data.token` no localStorage.
        $login->assertJsonStructure(['token', 'user', 'organizations', 'invitations']);

        $this->withToken($login->json('token'));
        $this->app['auth']->forgetGuards();

        // `selectOrganization()` navega por `redirect_to`.
        $this->postJson('/api/auth/organization', ['uuid' => $organization->uuid])
            ->assertOk()
            ->assertJsonStructure(['data', 'redirect_to']);
    }

    /**
     * O AccountGate lê `account_exists` da conferência, e trata a resposta do
     * cadastro como a do login (`token`, `user`). O 409 da corrida precisa
     * do `code`, que é o que a tela usa para trocar o formulário de cadastro
     * pela entrada com senha.
     */
    public function test_cadastro_de_conta_devolve_o_que_o_account_gate_le(): void
    {
        foreach (['nova@posto.test' => '111111', 'outra@posto.test' => '222222'] as $email => $code) {
            VerificationCode::query()->create([
                'email' => $email,
                'purpose' => VerificationCode::ACCOUNT_REGISTER,
                'code_hash' => hash('sha256', $code),
                'expires_at' => now()->addMinutes(10),
            ]);
        }

        $check = $this->postJson('/api/auth/register/check', ['email' => 'nova@posto.test', 'code' => '111111'])
            ->assertOk()
            ->json();
        $this->assertArrayNotHasKey('data', $check);
        $this->assertIsBool($check['account_exists']);

        $register = $this->postJson('/api/auth/register', [
            'email' => 'nova@posto.test',
            'code' => '111111',
            'name' => 'Nova',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertCreated()->json();
        $login = $this->postJson('/api/auth/login', [
            'email' => 'nova@posto.test',
            'password' => 'senha-nova-123',
        ])->assertOk()->json();

        foreach (['token', 'user', 'organizations', 'invitations'] as $key) {
            $this->assertArrayHasKey($key, $register, "cadastro sem {$key}");
            $this->assertArrayHasKey($key, $login, "login sem {$key}");
        }

        User::factory()->create(['email' => 'outra@posto.test']);
        $this->postJson('/api/auth/register', [
            'email' => 'outra@posto.test',
            'code' => '222222',
            'name' => 'Outra',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertStatus(409)->assertJsonPath('code', 'account_exists');
    }
}
