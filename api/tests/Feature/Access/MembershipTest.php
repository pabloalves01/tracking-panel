<?php

namespace Tests\Feature\Access;

use App\Models\Membership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * Os membros de uma organização.
 *
 * Sem papéis, sobra suspender, reativar e remover — e duas travas: ninguém
 * mexe no próprio acesso, e a organização nunca fica sem nenhum membro
 * ativo, porque aí ela ficaria inacessível a todos.
 */
class MembershipTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    public function test_lista_os_membros_da_organizacao_ativa(): void
    {
        $this->actingAsMemberOf();
        $colega = $this->addMember();

        $this->getJson('/api/organization/members')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['email' => $colega->email]);
    }

    public function test_suspender_tira_o_acesso_e_reativar_devolve(): void
    {
        $this->actingAsMemberOf();
        $colega = $this->addMember();

        $this->postJson("/api/organization/members/{$colega->uuid}/suspend")
            ->assertOk()
            ->assertJsonPath('message', 'Acesso suspenso');

        $this->assertSame(Membership::SUSPENDED, $this->statusOf($colega));

        // Com o vínculo suspenso, o token dele não abre mais a área.
        $this->enter($colega, $this->organization);
        $this->getJson('/api/registry/entities')
            ->assertForbidden()
            ->assertJsonPath('code', 'organization_access_lost');

        $this->enter($this->member, $this->organization);
        $this->postJson("/api/organization/members/{$colega->uuid}/reactivate")->assertOk();

        $this->assertSame(Membership::ACTIVE, $this->statusOf($colega));

        $this->enter($colega, $this->organization);
        $this->getJson('/api/registry/entities')->assertOk();
    }

    public function test_remover_desfaz_o_vinculo(): void
    {
        $this->actingAsMemberOf();
        $colega = $this->addMember();

        $this->deleteJson("/api/organization/members/{$colega->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Membro removido da organização');

        $this->getJson('/api/organization/members')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->enter($colega, $this->organization);
        $this->getJson('/api/registry/entities')->assertForbidden();
    }

    public function test_ninguem_mexe_no_proprio_acesso(): void
    {
        $this->actingAsMemberOf();
        $this->addMember();

        $uuid = $this->member->uuid;

        $this->postJson("/api/organization/members/{$uuid}/suspend")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Você não pode alterar o próprio acesso');

        $this->deleteJson("/api/organization/members/{$uuid}")->assertStatus(409);
    }

    /**
     * Sem esta trava a organização pode ficar sem ninguém dentro, e só o
     * admin do sistema conseguiria resgatá-la.
     */
    public function test_a_ultima_pessoa_com_acesso_nao_e_removida(): void
    {
        $this->actingAsMemberOf();
        $colega = $this->addMember();

        // Suspende o colega: resta um ativo.
        $this->postJson("/api/organization/members/{$colega->uuid}/suspend")->assertOk();

        // Agora o único ativo é quem está agindo — e ele não mexe em si.
        $this->enter($colega, $this->organization);
        $this->getJson('/api/organization/members')->assertForbidden();

        $this->enter($this->member, $this->organization);
        $this->postJson("/api/organization/members/{$colega->uuid}/reactivate")->assertOk();

        // Com dois ativos, remover um passa; o outro vira o último.
        $this->deleteJson("/api/organization/members/{$colega->uuid}")->assertOk();

        $this->assertSame(1, Membership::query()
            ->where('organization_id', $this->organization->getKey())
            ->where('status', Membership::ACTIVE)
            ->count());
    }

    public function test_membro_de_outra_organizacao_nao_e_alcancado(): void
    {
        $this->actingAsMemberOf();
        $dono = $this->member;
        $minha = $this->organization;

        $this->actingAsMemberOf($this->otherCompany());
        $alheio = $this->member;

        $this->enter($dono, $minha);

        $this->postJson("/api/organization/members/{$alheio->uuid}/suspend")
            ->assertNotFound()
            ->assertJsonPath('message', 'Membro não encontrado nesta organização');
    }

    /** As mesmas regras valem no posto: são as mesmas rotas. */
    public function test_o_posto_segue_as_mesmas_regras(): void
    {
        $this->actingAsFuelStationMemberOf();
        $colega = $this->addMember();

        $this->postJson("/api/organization/members/{$colega->uuid}/suspend")->assertOk();
        $this->assertSame(Membership::SUSPENDED, $this->statusOf($colega));

        $this->postJson('/api/organization/members/'.$this->member->uuid.'/suspend')
            ->assertStatus(409);
    }

    // ================================ HELPERS ================================

    private function addMember(): User
    {
        $user = User::factory()->create();

        Membership::query()->create([
            'organization_id' => $this->organization->getKey(),
            'user_id' => $user->getKey(),
            'status' => Membership::ACTIVE,
        ]);

        return $user;
    }

    private function statusOf(User $user): ?string
    {
        return Membership::query()
            ->where('organization_id', $this->organization->getKey())
            ->where('user_id', $user->getKey())
            ->value('status');
    }
}
