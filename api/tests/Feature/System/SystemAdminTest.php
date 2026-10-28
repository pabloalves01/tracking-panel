<?php

namespace Tests\Feature\System;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Organization;
use Database\Factories\OrganizationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * A área do admin do sistema.
 *
 * Ele administra as organizações sem pertencer a nenhuma: cadastra, ativa,
 * desativa e edita, mas não opera os dados de dentro delas. Criar uma
 * empresa já dispara o convite do primeiro acesso — sem isso ela nasceria
 * sem ninguém que pudesse entrar.
 */
class SystemAdminTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->actingAsSystemAdmin();
    }

    public function test_cadastrar_empresa_cria_e_convida_o_primeiro_acesso(): void
    {
        $document = OrganizationFactory::cnpj();

        $this->postJson('/api/system/organizations', [
            'document' => $document,
            'legal_name' => 'TRANSPORTES EXEMPLO LTDA',
            'trade_name' => 'Exemplo Log',
            'owner_email' => 'dono@exemplo.com.br',
        ])
            ->assertCreated()
            ->assertJsonPath('data.document', $document)
            ->assertJsonPath('data.type', 'company')
            ->assertJsonPath('invitation.email', 'dono@exemplo.com.br')
            ->assertJsonPath('message', 'Empresa criada. O convite foi enviado para dono@exemplo.com.br');

        Mail::assertQueued(InvitationMail::class);

        $this->assertDatabaseHas('invitations', [
            'email' => 'dono@exemplo.com.br',
            'status' => Invitation::PENDING,
        ]);
    }

    public function test_cnpj_invalido_e_repetido_nao_passam(): void
    {
        $this->postJson('/api/system/organizations', [
            'document' => '11111111111111',
            'legal_name' => 'EMPRESA LTDA',
            'owner_email' => 'dono@exemplo.com.br',
        ])->assertUnprocessable();

        $existente = Organization::factory()->company()->create();

        $this->postJson('/api/system/organizations', [
            'document' => $existente->document,
            'legal_name' => 'OUTRA LTDA',
            'owner_email' => 'outro@exemplo.com.br',
        ])->assertUnprocessable();
    }

    public function test_grava_estado_e_cidade_quando_informados(): void
    {
        $state = State::query()->create([
            'ibge_code' => 35, 'name' => 'São Paulo', 'uf' => 'SP', 'region' => 'Sudeste',
        ]);
        $city = City::query()->create([
            'ibge_code' => 3550308, 'state_id' => $state->id, 'name' => 'São Paulo',
        ]);

        $this->postJson('/api/system/organizations', [
            'document' => OrganizationFactory::cnpj(),
            'legal_name' => 'TRANSPORTES EXEMPLO LTDA',
            'owner_email' => 'dono@exemplo.com.br',
            'state_id' => $state->id,
            'city_id' => $city->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.city', 'São Paulo')
            ->assertJsonPath('data.state', 'SP')
            ->assertJsonPath('data.city_id', $city->id);
    }

    public function test_estado_e_cidade_inexistentes_sao_recusados(): void
    {
        $this->postJson('/api/system/organizations', [
            'document' => OrganizationFactory::cnpj(),
            'legal_name' => 'TRANSPORTES EXEMPLO LTDA',
            'owner_email' => 'dono@exemplo.com.br',
            'state_id' => 999,
            'city_id' => 999,
        ])->assertUnprocessable();
    }

    public function test_lista_filtra_por_tipo_e_por_busca(): void
    {
        Organization::factory()->company()->create(['trade_name' => 'Melo Transportes']);
        Organization::factory()->fuelStation()->create(['trade_name' => 'Posto Central']);

        $this->getJson('/api/system/organizations')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->getJson('/api/system/organizations?type=fuel_station')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trade_name', 'Posto Central');

        $this->getJson('/api/system/organizations?search=Melo')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.trade_name', 'Melo Transportes');
    }

    public function test_desativar_tira_a_organizacao_de_quem_usa(): void
    {
        $this->actingAsMemberOf();
        $organization = $this->organization;
        $member = $this->member;

        $this->getJson('/api/registry/entities')->assertOk();

        $this->actingAsSystemAdmin();
        $this->postJson("/api/system/organizations/{$organization->uuid}/deactivate")
            ->assertOk()
            ->assertJsonPath('message', 'Organização desativada');

        $this->enter($member, $organization);
        $this->getJson('/api/auth/organizations')
            ->assertOk()
            ->assertJsonCount(0, 'organizations');

        $this->actingAsSystemAdmin();
        $this->postJson("/api/system/organizations/{$organization->uuid}/activate")->assertOk();

        $this->enter($member, $organization);
        $this->getJson('/api/auth/organizations')
            ->assertOk()
            ->assertJsonCount(1, 'organizations');
    }

    public function test_editar_altera_os_dados_cadastrais(): void
    {
        $organization = Organization::factory()->company()->create();

        $this->putJson("/api/system/organizations/{$organization->uuid}", [
            'legal_name' => 'RAZAO NOVA LTDA',
            'trade_name' => 'Nome Novo',
        ])
            ->assertOk()
            ->assertJsonPath('data.legal_name', 'RAZAO NOVA LTDA')
            ->assertJsonPath('data.trade_name', 'Nome Novo');
    }

    public function test_uuid_inexistente_e_404(): void
    {
        $this->getJson('/api/system/organizations/'.fake()->uuid())->assertNotFound();
        $this->postJson('/api/system/organizations/'.fake()->uuid().'/activate')->assertNotFound();
    }
}
