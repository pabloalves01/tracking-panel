<?php

namespace Tests\Feature\Access;

use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * Quem entra onde.
 *
 * Com `organizations` e sem papéis, resta uma pergunta por requisição: há
 * uma organização escolhida, o vínculo com ela ainda vale, e ela é do tipo
 * desta área? Os três casos têm código próprio na resposta porque o client
 * reage diferente a cada um — reescolher, deslogar ou voltar para a área
 * certa.
 */
class AccessControlTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    public function test_sem_organizacao_escolhida_a_resposta_pede_para_escolher(): void
    {
        $user = User::factory()->create();
        Membership::factory()->create(['user_id' => $user->getKey()]);

        $this->enter($user, null);

        $this->getJson('/api/registry/entities')
            ->assertStatus(409)
            ->assertJsonPath('code', 'organization_not_selected');
    }

    public function test_vinculo_suspenso_perde_o_acesso_mesmo_com_token_valido(): void
    {
        $this->actingAsMemberOf();

        $this->getJson('/api/registry/entities')->assertOk();

        Membership::query()
            ->where('organization_id', $this->organization->getKey())
            ->where('user_id', $this->member->getKey())
            ->update(['status' => Membership::SUSPENDED]);

        $this->getJson('/api/registry/entities')
            ->assertForbidden()
            ->assertJsonPath('code', 'organization_access_lost');
    }

    public function test_organizacao_excluida_perde_o_acesso(): void
    {
        $this->actingAsMemberOf();

        $this->organization->delete();

        $this->getJson('/api/registry/entities')
            ->assertForbidden()
            ->assertJsonPath('code', 'organization_access_lost');
    }

    public function test_cada_area_exige_o_tipo_certo_de_organizacao(): void
    {
        $this->actingAsMemberOf();

        $this->getJson('/api/registry/entities')->assertOk();
        $this->getJson('/api/station/companies')
            ->assertForbidden()
            ->assertJsonPath('code', 'organization_wrong_type');

        $this->actingAsStationMemberOf();

        $this->getJson('/api/station/companies')->assertOk();
        $this->getJson('/api/registry/entities')
            ->assertForbidden()
            ->assertJsonPath('code', 'organization_wrong_type');
    }

    /**
     * Membros e convites valem para qualquer tipo de organização — era o
     * ponto de unificar as rotas. O posto usa as mesmas que a empresa.
     */
    public function test_membros_e_convites_servem_aos_dois_tipos(): void
    {
        $this->actingAsMemberOf();
        $this->getJson('/api/organization/members')->assertOk();

        $this->actingAsStationMemberOf();
        $this->getJson('/api/organization/members')->assertOk();
    }

    public function test_uma_organizacao_nao_alcanca_os_membros_de_outra(): void
    {
        $this->actingAsMemberOf();
        $intruso = $this->member;

        $outra = $this->otherCompany();
        $alvo = User::factory()->create();
        Membership::query()->create([
            'organization_id' => $outra->getKey(),
            'user_id' => $alvo->getKey(),
            'status' => Membership::ACTIVE,
        ]);

        $this->enter($intruso, $this->organization);

        $this->getJson('/api/organization/members')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // O uuid existe, mas não nesta organização.
        $this->postJson('/api/organization/members/'.$alvo->uuid.'/suspend')
            ->assertNotFound();
    }

    public function test_area_do_sistema_so_com_admin_do_sistema(): void
    {
        $this->actingAsMemberOf();
        $this->getJson('/api/system/organizations')->assertForbidden();

        $this->actingAsSystemAdmin();
        $this->getJson('/api/system/organizations')->assertOk();
    }

    /**
     * O admin do sistema não pertence a organização alguma: ele administra
     * sem entrar. As rotas de empresa continuam fechadas para ele.
     */
    public function test_admin_do_sistema_nao_entra_na_empresa_sem_vinculo(): void
    {
        $this->actingAsSystemAdmin();

        Organization::factory()->company()->create();

        $this->getJson('/api/registry/entities')
            ->assertStatus(409)
            ->assertJsonPath('code', 'organization_not_selected');
    }

    public function test_nao_se_escolhe_uma_organizacao_sem_vinculo(): void
    {
        $this->actingAsMemberOf();

        $alheia = $this->otherCompany();

        $this->postJson('/api/auth/organization', ['uuid' => $alheia->uuid])
            ->assertForbidden();
    }

    public function test_sair_revoga_o_token(): void
    {
        $this->actingAsMemberOf();

        $this->postJson('/api/auth/logout')->assertOk();

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }
}
