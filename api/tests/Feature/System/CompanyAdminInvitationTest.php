<?php

namespace Tests\Feature\System;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Access\ActsInContext;
use Tests\TestCase;

class CompanyAdminInvitationTest extends TestCase
{
    use ActsInContext;
    use RefreshDatabase;

    private function systemAdmin(): User
    {
        $admin = User::factory()->create([
            'name' => 'Logiv',
            'email' => 'admin@logiv.com.br',
            'is_system_admin' => true,
        ]);
        $this->actIn($admin);

        return $admin;
    }

    private function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'legal_name' => 'Transportes X LTDA',
            'trade_name' => 'Transportes X',
            'cnpj' => '11222333000181',
            'admin_email' => ' Ana@X.com ',
        ], $overrides);
    }

    public function test_nova_empresa_cria_so_o_convite_de_admin_sem_criar_conta(): void
    {
        $this->systemAdmin();

        $response = $this->postJson('/api/system/companies', $this->companyPayload())
            ->assertCreated()
            ->assertJsonPath('data.trade_name', 'Transportes X')
            ->assertJsonPath('invitation.email', 'ana@x.com')
            ->assertJsonPath('invitation.role', 'admin')
            ->assertJsonPath('invitation.invited_by', 'Logiv');

        $this->assertSame(1, User::query()->count());
        $this->assertSame(0, CompanyUser::query()->count());

        $invitation = Invitation::query()->sole();
        $company = Company::query()->sole();
        $this->assertSame($company->id, $invitation->company_id);
        $this->assertSame(hash('sha256', $response->json('token')), $invitation->token_hash);

        $this->asGuest();
        $this->getJson('/api/auth/invitations/'.$response->json('token'))
            ->assertOk()
            ->assertJsonPath('data.type', 'company')
            ->assertJsonPath('data.name', 'Transportes X')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.has_account', false);
    }

    public function test_nova_empresa_com_email_de_conta_existente_tambem_so_convida(): void
    {
        $this->systemAdmin();
        $ana = $this->person('ana@x.com', 'Ana');

        $this->postJson('/api/system/companies', $this->companyPayload())->assertCreated();

        $this->assertSame(0, CompanyUser::query()->count());
        $this->assertSame('Ana', $ana->fresh()->name);
        $this->assertTrue(password_verify('senha-atual', $ana->fresh()->password));
    }

    public function test_nova_empresa_exige_email_do_admin_e_nao_quebra_com_array(): void
    {
        $this->systemAdmin();

        $this->postJson('/api/system/companies', $this->companyPayload(['admin_email' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['admin_email' => 'O e-mail do administrador é obrigatório']);
        $this->postJson('/api/system/companies', $this->companyPayload(['admin_email' => ['a@b.com']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('admin_email');
        $this->postJson('/api/system/companies', $this->companyPayload(['admin_email' => 'nao-e-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['admin_email' => 'Informe um e-mail válido']);

        $this->assertSame(0, Company::query()->count());
        $this->assertSame(0, Invitation::query()->count());
    }

    public function test_quem_aceita_vira_admin_e_aparece_na_edicao_da_empresa(): void
    {
        $logiv = $this->systemAdmin();
        $token = $this->postJson('/api/system/companies', $this->companyPayload())->json('token');
        $company = Company::query()->sole();

        $this->asGuest();
        $this->postJson("/api/auth/invitations/{$token}/register", [
            'name' => 'Ana',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ])->assertCreated();

        $this->actIn($logiv);
        $this->getJson("/api/system/companies/{$company->uuid}")
            ->assertOk()
            ->assertJsonCount(1, 'data.admins')
            ->assertJsonPath('data.admins.0.email', 'ana@x.com')
            ->assertJsonPath('data.admins.0.role', 'admin');
        $this->getJson("/api/system/companies/{$company->uuid}/invitations")
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->getJson('/api/system/companies')
            ->assertOk()
            ->assertJsonPath('data.0.users_count', 1);
    }

    public function test_logiv_lista_convida_renova_e_cancela_admin_da_empresa(): void
    {
        $this->systemAdmin();
        $first = $this->postJson('/api/system/companies', $this->companyPayload())->json('token');
        $company = Company::query()->sole();
        $base = "/api/system/companies/{$company->uuid}/invitations";

        $this->postJson($base, ['email' => 'ANA@x.com'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'Já existe um convite pendente para este e-mail. Use “Copiar novo link” na lista.']);

        $created = $this->postJson($base, ['email' => 'Bia@X.com', 'role' => 'member'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'bia@x.com')
            ->assertJsonPath('data.role', 'admin');

        $this->getJson($base)->assertOk()->assertJsonCount(2, 'data');

        $ana = Invitation::query()->where('email', 'ana@x.com')->sole();
        $renewed = $this->postJson("{$base}/{$ana->uuid}/renew")->assertOk()->json('token');
        $this->assertNotSame($first, $renewed);
        $this->assertSame(hash('sha256', $renewed), $ana->fresh()->token_hash);

        $this->deleteJson("{$base}/{$created->json('data.uuid')}")
            ->assertOk()
            ->assertJsonPath('message', 'Convite cancelado');
        $this->deleteJson("{$base}/{$created->json('data.uuid')}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este convite não está mais pendente');

        $this->getJson($base)->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_convite_de_outra_empresa_ou_empresa_inexistente_da_404(): void
    {
        $this->systemAdmin();
        $this->postJson('/api/system/companies', $this->companyPayload())->assertCreated();
        $invitation = Invitation::query()->sole();
        $other = $this->company('Outra');

        $this->postJson("/api/system/companies/{$other->uuid}/invitations/{$invitation->uuid}/renew")
            ->assertNotFound();
        $this->deleteJson("/api/system/companies/{$other->uuid}/invitations/{$invitation->uuid}")
            ->assertNotFound();
        $this->getJson("/api/system/companies/{$this->uuidNotFound()}/invitations")
            ->assertNotFound()
            ->assertJsonPath('message', 'Empresa não encontrada');
    }

    public function test_so_o_admin_da_logiv_mexe_nos_convites_da_empresa(): void
    {
        $company = $this->company();
        $admin = $this->person('admin@x.com');
        $admin->companies()->attach($company->id, ['role' => 'admin']);
        $this->actIn($admin, $company);

        $this->getJson("/api/system/companies/{$company->uuid}/invitations")->assertForbidden();
        $this->postJson("/api/system/companies/{$company->uuid}/invitations", ['email' => 'bia@x.com'])
            ->assertForbidden();
        $this->postJson('/api/system/companies', $this->companyPayload(['cnpj' => '99888777000166']))
            ->assertForbidden();
    }
}
