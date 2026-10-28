<?php

namespace Tests\Feature\Access;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * O ciclo de vida de um convite.
 *
 * Sem papéis, convidar é só dizer o e-mail: quem aceita vira membro com o
 * mesmo acesso de todos. O que sobrou de regra é sobre estado — pendente,
 * aceito, recusado, revogado, expirado — e sobre não deixar um token velho
 * servir depois que o convite mudou de estado.
 */
class InvitationTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->actingAsMemberOf();
    }

    // ================================ CONVIDAR ================================

    public function test_convidar_envia_o_email_e_lista_como_pendente(): void
    {
        $this->invite('novo@exemplo.com.br')->assertCreated();

        Mail::assertQueued(InvitationMail::class);

        $this->getJson('/api/organization/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'novo@exemplo.com.br')
            ->assertJsonPath('data.0.status', Invitation::PENDING);
    }

    public function test_dois_convites_pendentes_para_o_mesmo_email_nao_coexistem(): void
    {
        $this->invite('novo@exemplo.com.br')->assertCreated();

        $this->invite('novo@exemplo.com.br')->assertStatus(409);

        $this->assertSame(1, Invitation::query()->count());
    }

    public function test_nao_se_convida_quem_ja_e_membro(): void
    {
        $this->invite($this->member->email)->assertStatus(409);
    }

    /**
     * Recusado e revogado não bloqueiam um convite novo — é por isso que o
     * índice único é parcial, só sobre os pendentes.
     */
    public function test_depois_de_recusado_cabe_um_convite_novo(): void
    {
        $token = $this->inviteAndToken('novo@exemplo.com.br');

        $this->withToken('')->postJson("/api/auth/invitations/{$token}/decline")->assertOk();

        $this->actingAsMemberOf($this->organization, $this->member);
        $this->invite('novo@exemplo.com.br')->assertCreated();

        $this->assertSame(2, Invitation::query()->count());
        $this->assertSame(1, Invitation::query()->where('status', Invitation::PENDING)->count());
    }

    public function test_revogar_encerra_o_convite_e_fica_no_historico(): void
    {
        $created = $this->invite('novo@exemplo.com.br');
        $token = $this->tokenFromMail();
        $uuid = $created->json('data.uuid');

        $this->deleteJson("/api/organization/invitations/{$uuid}")->assertOk();

        // O link continua abrindo, agora dizendo que o convite foi encerrado:
        // um 404 aqui faria a tela parecer quebrada para quem clicou.
        $this->withToken('')
            ->getJson("/api/auth/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.status', Invitation::REVOKED);

        // A listagem é o histórico: o convite continua lá, com a situação
        // trocada, e é isso que a coluna "Situação" da tela mostra.
        $this->getJson('/api/organization/invitations')
            ->assertOk()
            ->assertJsonPath('data.0.status', Invitation::REVOKED);
    }

    public function test_reenviar_troca_o_token_e_o_antigo_para_de_valer(): void
    {
        $created = $this->invite('novo@exemplo.com.br');
        $antigo = $this->tokenFromMail();
        $uuid = $created->json('data.uuid');

        Mail::fake();
        $this->postJson("/api/organization/invitations/{$uuid}/resend")->assertOk();
        $novo = $this->tokenFromMail();

        $this->assertNotSame($antigo, $novo);

        $this->withToken('')->getJson("/api/auth/invitations/{$antigo}")->assertNotFound();
        $this->withToken('')->getJson("/api/auth/invitations/{$novo}")->assertOk();
    }

    // ================================ ACEITAR ================================

    public function test_quem_ja_tem_conta_aceita_e_cai_na_organizacao(): void
    {
        $convidado = User::factory()->create(['email' => 'tem@conta.com.br']);
        $token = $this->inviteAndToken('tem@conta.com.br');

        $this->enter($convidado, null);

        $this->postJson("/api/auth/invitations/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('redirect_to', '/app');

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $this->organization->getKey(),
            'user_id' => $convidado->getKey(),
            'status' => Membership::ACTIVE,
        ]);

        $this->getJson('/api/registry/entities')->assertOk();
    }

    public function test_conta_de_outro_email_nao_aceita_o_convite(): void
    {
        $outro = User::factory()->create(['email' => 'outro@exemplo.com.br']);
        $token = $this->inviteAndToken('tem@conta.com.br');

        $this->enter($outro, null);

        $this->postJson("/api/auth/invitations/{$token}/accept")->assertStatus(409);

        $this->assertDatabaseMissing('memberships', [
            'organization_id' => $this->organization->getKey(),
            'user_id' => $outro->getKey(),
        ]);
    }

    public function test_convite_expirado_nao_e_aceito(): void
    {
        $convidado = User::factory()->create(['email' => 'tem@conta.com.br']);
        $token = $this->inviteAndToken('tem@conta.com.br');

        Invitation::query()->update(['expires_at' => now()->subDay()]);

        $this->enter($convidado, null);

        $this->postJson("/api/auth/invitations/{$token}/accept")->assertStatus(409);
    }

    public function test_o_mesmo_token_nao_serve_duas_vezes(): void
    {
        $convidado = User::factory()->create(['email' => 'tem@conta.com.br']);
        $token = $this->inviteAndToken('tem@conta.com.br');

        $this->enter($convidado, null);
        $this->postJson("/api/auth/invitations/{$token}/accept")->assertOk();

        $this->app['auth']->forgetGuards();
        $this->postJson("/api/auth/invitations/{$token}/accept")->assertStatus(409);
    }

    /**
     * Aceitar não é um jeito de voltar: quem foi suspenso continua suspenso,
     * senão o convite viraria uma porta dos fundos para reativar o acesso.
     */
    public function test_aceitar_nao_reativa_quem_esta_suspenso(): void
    {
        $suspenso = User::factory()->create(['email' => 'suspenso@exemplo.com.br']);

        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $suspenso->getKey(),
            'status' => Membership::SUSPENDED,
        ]);

        // Já é membro, ainda que suspenso: não cabe convite.
        $this->invite('suspenso@exemplo.com.br')->assertStatus(409);

        $this->assertSame(
            Membership::SUSPENDED,
            Membership::query()
                ->where('user_id', $suspenso->getKey())
                ->value('status'),
        );
    }

    public function test_sem_conta_o_registro_pelo_convite_cria_e_entra(): void
    {
        $token = $this->inviteAndToken('novo@exemplo.com.br');

        VerificationCode::query()->create([
            'email' => 'novo@exemplo.com.br',
            'purpose' => VerificationCode::INVITATION_REGISTER,
            'code_hash' => hash('sha256', '123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withToken('')
            ->postJson("/api/auth/invitations/{$token}/register", [
                'name' => 'Pessoa Nova',
                'password' => 'senha-bem-grande',
                'password_confirmation' => 'senha-bem-grande',
                'code' => '123456',
            ])
            ->assertCreated()
            ->assertJsonPath('redirect_to', '/app');

        $novo = User::query()->where('email', 'novo@exemplo.com.br')->sole();

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $this->organization->getKey(),
            'user_id' => $novo->getKey(),
            'status' => Membership::ACTIVE,
        ]);
    }

    public function test_registro_com_codigo_errado_nao_cria_conta(): void
    {
        $token = $this->inviteAndToken('novo@exemplo.com.br');

        VerificationCode::query()->create([
            'email' => 'novo@exemplo.com.br',
            'purpose' => VerificationCode::INVITATION_REGISTER,
            'code_hash' => hash('sha256', '123456'),
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->withToken('')
            ->postJson("/api/auth/invitations/{$token}/register", [
                'name' => 'Pessoa Nova',
                'password' => 'senha-bem-grande',
                'password_confirmation' => 'senha-bem-grande',
                'code' => '999999',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseMissing('users', ['email' => 'novo@exemplo.com.br']);
    }

    // ============================= ISOLAMENTO =============================

    public function test_uma_organizacao_nao_ve_nem_revoga_convite_de_outra(): void
    {
        $created = $this->invite('novo@exemplo.com.br');
        $uuid = $created->json('data.uuid');

        $this->actingAsMemberOf($this->otherCompany());

        $this->getJson('/api/organization/invitations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->deleteJson("/api/organization/invitations/{$uuid}")->assertNotFound();
    }

    // ================================ HELPERS ================================

    private function invite(string $email)
    {
        return $this->postJson('/api/organization/invitations', ['email' => $email]);
    }

    private function inviteAndToken(string $email): string
    {
        $this->invite($email)->assertCreated();

        return $this->tokenFromMail();
    }

    /** O token em claro só existe no e-mail — no banco fica o sha256. */
    private function tokenFromMail(): string
    {
        $token = null;

        Mail::assertQueued(InvitationMail::class, function (InvitationMail $mail) use (&$token) {
            preg_match('#/auth/convite/([^\s"\'<]+)#', $mail->url, $matches);
            $token = $matches[1] ?? null;

            return true;
        });

        $this->assertNotNull($token);

        return $token;
    }
}
