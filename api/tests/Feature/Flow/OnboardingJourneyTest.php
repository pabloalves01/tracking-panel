<?php

namespace Tests\Feature\Flow;

use App\Mail\InvitationMail;
use App\Mail\VerificationCodeMail;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\VerificationCode;
use Database\Factories\OrganizationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * A jornada inteira, de ponta a ponta, numa corrida só.
 *
 * Os testes vizinhos cobrem cada serviço isolado. Este existe porque os bugs
 * que chegaram ao usuário não estavam dentro de um serviço — estavam nas
 * juntas: a coluna que o model declarava e a migration não criava, a view
 * que só é compilada quando o e-mail sai, o payload que o controller embrulha
 * e a tela desembrulha diferente. Nenhum deles aparece sem percorrer o
 * caminho todo com requisição de verdade.
 */
class OnboardingJourneyTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    public function test_do_cadastro_da_empresa_ate_o_primeiro_acesso(): void
    {
        Mail::fake();

        // ---- 1. o admin do sistema cadastra a empresa -------------------
        $this->actingAsSystemAdmin();

        $document = OrganizationFactory::cnpj();

        $created = $this->postJson('/api/system/organizations', [
            'document' => $document,
            'legal_name' => 'TRANSPORTES EXEMPLO LTDA',
            'trade_name' => 'Exemplo Log',
            'owner_email' => 'dono@exemplo.com.br',
        ]);

        $created->assertCreated();
        $created->assertJsonPath('data.document', $document);

        $organization = Organization::query()->where('document', $document)->sole();
        $this->assertTrue($organization->isActive());

        // O convite nasce junto com a empresa, pendente e com prazo.
        $this->assertDatabaseHas('invitations', [
            'organization_id' => $organization->getKey(),
            'email' => 'dono@exemplo.com.br',
            'status' => 'pending',
            'responded_at' => null,
        ]);

        // ---- 2. o e-mail do convite é realmente renderizável ------------
        // Compila as duas views. É o passo que pega erro de Blade: ele só
        // acontece quando a mensagem é construída, não quando é enfileirada.
        $token = null;

        Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) use (&$token) {
            $mail->assertSeeInHtml('Exemplo Log');
            $mail->assertSeeInText('Exemplo Log');

            preg_match('#/auth/convite/([^\s"\'<]+)#', $mail->url, $matches);
            $token = $matches[1] ?? null;

            return true;
        });

        $this->assertNotNull($token, 'o e-mail do convite precisa carregar o link com o token');

        // ---- 3. a tela pública do convite abre sem sessão ---------------
        $preview = $this->withToken('')->getJson("/api/auth/invitations/{$token}");

        $preview->assertOk();
        $preview->assertJsonPath('data.email', 'dono@exemplo.com.br');
        $preview->assertJsonPath('data.status', 'pending');
        $preview->assertJsonPath('data.account_exists', false);

        // ---- 4. pede o código: conta primeiro, convite depois ------------
        $sent = $this->postJson('/api/auth/verification-codes', [
            'email' => 'dono@exemplo.com.br',
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
        ]);

        $sent->assertOk();
        $sent->assertJsonPath('sent', true);

        $code = $this->codeFromMail('dono@exemplo.com.br');

        // ---- 5. confere o código: só agora o sistema diz que não há conta
        $this->postJson('/api/auth/register/check', ['email' => 'dono@exemplo.com.br', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('account_exists', false);

        // ---- 6. cria a conta: volta a sessão, sem organização ainda ------
        $registered = $this->postJson('/api/auth/register', [
            'email' => 'dono@exemplo.com.br',
            'code' => $code,
            'name' => 'Dono do Exemplo',
            'password' => 'senha-bem-grande',
            'password_confirmation' => 'senha-bem-grande',
        ]);

        $registered->assertCreated();
        $registered->assertJsonPath('organizations', []);
        $registered->assertJsonCount(1, 'invitations');

        $sessionToken = $registered->json('token');
        $this->assertNotEmpty($sessionToken, 'o cadastro precisa devolver a sessão pronta');

        $user = User::query()->where('email', 'dono@exemplo.com.br')->sole();
        $this->assertNotNull($user->email_verified_at, 'quem confirmou o código já chega verificado');

        // ---- 7. aceita o convite, já logado ------------------------------
        // O guard do Sanctum guarda o usuário resolvido, e dentro de um teste
        // as requisições compartilham a mesma instância da aplicação: sem
        // esquecer os guards, a próxima requisição responderia como o admin.
        $this->app['auth']->forgetGuards();
        $this->withToken($sessionToken);

        $this->postJson("/api/auth/invitations/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('redirect_to', '/app')
            ->assertJsonPath('organization.document', $document);

        // O convite fecha, com data — a coluna que faltava no banco.
        $this->assertDatabaseHas('invitations', [
            'organization_id' => $organization->getKey(),
            'email' => 'dono@exemplo.com.br',
            'status' => 'accepted',
        ]);
        $this->assertNotNull(
            $organization->invitations()->sole()->responded_at,
            'o aceite precisa datar o convite'
        );

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'status' => Membership::ACTIVE,
        ]);

        // ---- 8. a mesma sessão já abre a área da empresa -----------------
        $me = $this->getJson('/api/auth/me');
        $me->assertOk();
        $me->assertJsonPath('user.email', 'dono@exemplo.com.br');
        $me->assertJsonPath('current_organization.document', $document);

        $this->getJson('/api/registry/entities')->assertOk();

        // ---- 9. o código não serve duas vezes ----------------------------
        $this->assertDatabaseMissing('verification_codes', [
            'email' => 'dono@exemplo.com.br',
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
            'consumed_at' => null,
        ]);
    }

    /**
     * O caso que motivou o redesenho, do jeito que aconteceu: quem administra
     * uma empresa sai, vai cadastrar o posto com o mesmo e-mail, e em nenhum
     * momento define uma senha nova — o código chega de verdade, a
     * conferência diz que a conta existe, e a pessoa entra com a senha que já
     * tem. No fim, a mesma conta vê a empresa e o posto.
     */
    public function test_quem_administra_empresa_cadastra_posto_deslogado_sem_senha_nova(): void
    {
        Mail::fake();
        Http::preventStrayRequests();
        Http::fake(['brasilapi.com.br/api/cnpj/v1/*' => Http::response([
            'cnpj' => '19131243000197',
            'razao_social' => 'SIM REDE DE POSTOS LTDA',
            'nome_fantasia' => 'POSTO SIM',
            'descricao_situacao_cadastral' => 'ATIVA',
            'situacao_cadastral' => 2,
            'uf' => 'SP',
            'municipio' => 'SAO PAULO',
        ])]);

        $pablo = User::factory()->create(['email' => 'pablo@melo.test', 'password' => 'senha-de-sempre']);
        $hash = $pablo->password;
        $company = Organization::factory()->company()->create();
        Membership::query()->create([
            'organization_id' => $company->getKey(),
            'user_id' => $pablo->getKey(),
            'status' => Membership::ACTIVE,
        ]);

        // ---- 1. deslogado, consulta o CNPJ ------------------------------
        $this->getJson('/api/auth/fuel-station-signup/cnpj/19131243000197')
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'SIM REDE DE POSTOS LTDA');

        // ---- 2. informa o e-mail: o código chega de verdade -------------
        $this->postJson('/api/auth/verification-codes', [
            'email' => 'pablo@melo.test',
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
        ])->assertOk()->assertJsonPath('sent', true);

        $code = $this->codeFromMail('pablo@melo.test');

        // ---- 3. confere: só agora o sistema diz que a conta existe ------
        $this->postJson('/api/auth/register/check', ['email' => 'pablo@melo.test', 'code' => $code])
            ->assertOk()
            ->assertJsonPath('account_exists', true);

        // ---- 4. entra com a senha que já tem ----------------------------
        $login = $this->postJson('/api/auth/login', [
            'email' => 'pablo@melo.test',
            'password' => 'senha-de-sempre',
        ])->assertOk();

        // ---- 5. cria o posto na mesma conta -----------------------------
        $this->app['auth']->forgetGuards();
        $this->withToken($login->json('token'))
            ->postJson('/api/auth/fuel-stations', ['cnpj' => '19131243000197'])
            ->assertCreated()
            ->assertJsonPath('redirect_to', '/posto');

        // ---- 6. uma conta, a mesma senha, duas organizações -------------
        $this->assertSame(1, User::query()->where('email', 'pablo@melo.test')->count());
        $this->assertSame($hash, $pablo->fresh()->password);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonCount(2, 'organizations')
            ->assertJsonPath('current_organization.type', 'fuel_station');
    }

    public function test_login_lista_organizacoes_e_troca_entre_elas(): void
    {
        $user = User::factory()->create(['email' => 'multi@exemplo.com.br']);

        $company = Organization::factory()->company()->create();
        $fuelStation = Organization::factory()->fuelStation()->create();

        foreach ([$company, $fuelStation] as $organization) {
            Membership::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
                'status' => Membership::ACTIVE,
            ]);
        }

        $login = $this->postJson('/api/auth/login', [
            'email' => 'multi@exemplo.com.br',
            'password' => 'mesquita2016',
        ]);

        $login->assertOk();
        $token = $login->json('token');
        $this->assertNotEmpty($token);

        $this->withToken($token);

        // Sem escolher, as duas aparecem para a tela de seleção.
        $list = $this->getJson('/api/auth/organizations');
        $list->assertOk();
        $this->assertEqualsCanonicalizing(
            [$company->uuid, $fuelStation->uuid],
            collect($list->json('organizations'))->pluck('uuid')->all(),
        );

        // Entrar na empresa abre a área da empresa e fecha a do posto.
        $this->postJson('/api/auth/organization', ['uuid' => $company->uuid])
            ->assertOk()
            ->assertJsonPath('redirect_to', '/app');

        $this->getJson('/api/registry/entities')->assertOk();
        $this->getJson('/api/fuel-station/companies')->assertForbidden();

        // Trocar para o posto inverte exatamente isso.
        $this->postJson('/api/auth/organization', ['uuid' => $fuelStation->uuid])
            ->assertOk()
            ->assertJsonPath('redirect_to', '/posto');

        $this->getJson('/api/fuel-station/companies')->assertOk();
        $this->getJson('/api/registry/entities')->assertForbidden();
    }

    public function test_membros_e_convites_da_organizacao_ativa(): void
    {
        Mail::fake();

        $this->actingAsMemberOf();

        $this->getJson('/api/organization/members')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $invited = $this->postJson('/api/organization/invitations', [
            'email' => 'colega@exemplo.com.br',
        ]);

        $invited->assertCreated();

        Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) {
            $mail->assertSeeInHtml('convite');
            $mail->assertSeeInText('convite');

            return true;
        });

        $this->getJson('/api/organization/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'colega@exemplo.com.br');
    }

    private function codeFromMail(string $email): string
    {
        $code = null;

        Mail::assertQueued(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code, $email) {
            if (! $mail->hasTo($email)) {
                return false;
            }

            $mail->assertSeeInHtml($mail->code);
            $code = $mail->code;

            return true;
        });

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $code);

        return $code;
    }
}
