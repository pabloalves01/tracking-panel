<?php

namespace Tests\Feature\Access;

use App\Models\CompanyUser;
use App\Models\FuelStationUser;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use ActsInContext;
    use RefreshDatabase;

    private const INVALID = 'Este link de convite não é válido. Peça um novo ao administrador.';

    // ===================== CRIAR, LISTAR, RENOVAR, CANCELAR =====================

    public function test_admin_da_empresa_cria_convite_e_recebe_o_token_uma_vez(): void
    {
        [$company, $admin] = $this->companyWithAdmin();
        $this->freezeSecond();

        $response = $this->postJson('/api/company/invitations', [
            'email' => '  Ana@X.com ',
            'role' => 'member',
        ])->assertCreated()
            ->assertJsonPath('data.email', 'ana@x.com')
            ->assertJsonPath('data.role', 'member')
            ->assertJsonPath('data.invited_by', $admin->name)
            ->assertJsonPath('data.expired', false);

        $token = $response->json('token');
        $this->assertSame(48, strlen($token));

        $invitation = Invitation::query()->first();
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertSame($company->id, $invitation->company_id);
        $this->assertNull($invitation->fuel_station_id);
        $this->assertSame('pending', $invitation->status);
        $this->assertTrue($invitation->expires_at->equalTo(now()->addDays(7)));

        $this->getJson('/api/company/invitations')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.token')
            ->assertJsonMissingPath('data.0.token_hash');
    }

    public function test_convite_valida_email_e_papel_sem_quebrar_com_array(): void
    {
        $this->companyWithAdmin();

        $this->postJson('/api/company/invitations', ['email' => ['a@b.com'], 'role' => ['admin']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'role']);
        $this->postJson('/api/company/invitations', ['email' => 'nao-e-email', 'role' => 'owner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'role']);
    }

    public function test_nao_convida_quem_ja_faz_parte(): void
    {
        [$company] = $this->companyWithAdmin();
        $this->person('ana@x.com')->companies()->attach($company->id, ['role' => 'member']);

        $this->postJson('/api/company/invitations', ['email' => 'ANA@x.com', 'role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Este e-mail já faz parte da empresa.');

        $this->assertSame(0, Invitation::query()->count());
    }

    public function test_nao_cria_dois_convites_pendentes_pro_mesmo_email_nem_se_expirado(): void
    {
        $this->companyWithAdmin();
        $this->invite('ana@x.com');

        $this->postJson('/api/company/invitations', ['email' => 'ana@x.com', 'role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Já existe um convite pendente para este e-mail. Use “Copiar novo link” na lista.');

        $this->travel(8)->days();

        $this->postJson('/api/company/invitations', ['email' => 'ana@x.com', 'role' => 'member'])
            ->assertUnprocessable();
        $this->getJson('/api/company/invitations')->assertJsonPath('data.0.expired', true);
    }

    public function test_convite_cancelado_libera_novo_convite_pro_mesmo_email(): void
    {
        $this->companyWithAdmin();
        $uuid = $this->invite('ana@x.com')['data']['uuid'];

        $this->deleteJson("/api/company/invitations/{$uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Convite cancelado');
        $this->deleteJson("/api/company/invitations/{$uuid}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este convite não está mais pendente');

        $this->getJson('/api/company/invitations')->assertJsonCount(0, 'data');
        $this->invite('ana@x.com');
    }

    public function test_novo_link_invalida_o_anterior_e_renova_o_prazo_mesmo_expirado(): void
    {
        $this->companyWithAdmin();
        $created = $this->invite('ana@x.com');
        $oldToken = $created['token'];

        $this->travel(10)->days();
        $this->freezeSecond();

        $renewed = $this->postJson("/api/company/invitations/{$created['data']['uuid']}/renew")
            ->assertOk()
            ->assertJsonPath('data.expired', false)
            ->json();

        $this->assertNotSame($oldToken, $renewed['token']);
        $this->assertTrue(Invitation::query()->first()->expires_at->equalTo(now()->addDays(7)));

        $this->asGuest();
        $this->getJson("/api/auth/invitations/{$oldToken}")
            ->assertNotFound()
            ->assertJsonPath('message', self::INVALID);
        $this->getJson("/api/auth/invitations/{$renewed['token']}")
            ->assertOk()
            ->assertJsonPath('data.expired', false);
    }

    public function test_nao_mexe_em_convite_de_outra_empresa(): void
    {
        $this->companyWithAdmin('Transportes X');
        $uuid = $this->invite('ana@x.com')['data']['uuid'];

        $this->companyWithAdmin('Outra Empresa', 'admin@outra.com');

        $this->postJson("/api/company/invitations/{$uuid}/renew")->assertNotFound();
        $this->deleteJson("/api/company/invitations/{$uuid}")->assertNotFound();
        $this->getJson('/api/company/invitations')->assertJsonCount(0, 'data');
        $this->deleteJson('/api/company/invitations/nao-e-uuid')->assertNotFound();
    }

    public function test_membro_comum_nao_gerencia_convites(): void
    {
        $company = $this->company();
        $member = $this->person('membro@x.com');
        $member->companies()->attach($company->id, ['role' => 'member']);
        $this->actIn($member, $company);

        $message = 'Apenas administradores podem gerenciar usuários.';
        $this->getJson('/api/company/invitations')->assertForbidden()->assertJsonPath('message', $message);
        $this->postJson('/api/company/invitations', ['email' => 'a@b.com', 'role' => 'member'])
            ->assertForbidden()
            ->assertJsonPath('message', $message);
        $this->postJson('/api/company/invitations/'.$this->uuidNotFound().'/renew')->assertForbidden();
        $this->deleteJson('/api/company/invitations/'.$this->uuidNotFound())->assertForbidden();
    }

    public function test_token_de_posto_nao_usa_rotas_de_convite_da_empresa(): void
    {
        [$station] = $this->stationWithAdmin();

        $this->getJson('/api/company/invitations')->assertStatus(409);
        $this->getJson('/api/station/invitations')->assertOk();
        $this->assertNotNull($station);
    }

    // ===================== TELA DO CONVITE =====================

    public function test_convite_mostra_os_dados_para_quem_nao_tem_conta(): void
    {
        $this->companyWithAdmin();
        $token = $this->invite('ana@x.com', 'admin')['token'];
        $this->asGuest();

        $this->getJson("/api/auth/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.type', 'company')
            ->assertJsonPath('data.name', 'Transportes X')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.email', 'ana@x.com')
            ->assertJsonPath('data.has_account', false)
            ->assertJsonPath('data.expired', false)
            ->assertJsonPath('data.is_member', false);
    }

    public function test_token_inexistente_tem_a_mensagem_de_link_invalido(): void
    {
        $this->getJson('/api/auth/invitations/'.str_repeat('a', 48))
            ->assertNotFound()
            ->assertJsonPath('message', self::INVALID);
        $this->postJson('/api/auth/invitations/'.str_repeat('a', 48).'/register', [
            'name' => 'Ana',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertNotFound()
            ->assertJsonPath('message', self::INVALID);
    }

    public function test_criar_conta_pelo_convite_entra_direto_na_empresa(): void
    {
        [$company] = $this->companyWithAdmin();
        $token = $this->invite('ana@x.com')['token'];
        $this->asGuest();

        $response = $this->postJson("/api/auth/invitations/{$token}/register", [
            'name' => 'Ana',
            'email' => 'outro@email.com',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertCreated()
            ->assertJsonPath('type', 'company');

        $ana = User::query()->where('email', 'ana@x.com')->first();
        $this->assertNotNull($ana);
        $this->assertNull(User::query()->where('email', 'outro@email.com')->first());
        $this->assertNull($ana->email_verified_at);
        $this->assertSame('member', CompanyUser::query()->where('user_id', $ana->id)->value('role'));

        $invitation = Invitation::query()->first();
        $this->assertSame('accepted', $invitation->status);
        $this->assertNotNull($invitation->accepted_at);

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.current_company.uuid', $company->uuid)
            ->assertJsonPath('data.current_role', 'member');
        $this->getJson('/api/registry/entities')->assertOk();

        $this->asGuest();
        $this->getJson("/api/auth/invitations/{$token}")
            ->assertNotFound()
            ->assertJsonPath('message', self::INVALID);
    }

    public function test_criar_conta_valida_os_campos(): void
    {
        $this->companyWithAdmin();
        $token = $this->invite('ana@x.com')['token'];
        $this->asGuest();

        $this->postJson("/api/auth/invitations/{$token}/register", [
            'name' => ['Ana'],
            'password' => 'curta',
            'password_confirmation' => 'outra',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'password']);

        $this->postJson("/api/auth/invitations/{$token}/register", [
            'name' => 'Ana',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-diferente',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'A confirmação da senha não confere');

        $this->assertSame(1, User::query()->count());
    }

    public function test_criar_conta_com_email_que_ja_tem_conta_pede_login(): void
    {
        $this->companyWithAdmin();
        $token = $this->invite('ana@x.com')['token'];
        $this->person('ana@x.com');
        $this->asGuest();

        $this->getJson("/api/auth/invitations/{$token}")->assertJsonPath('data.has_account', true);

        $this->postJson("/api/auth/invitations/{$token}/register", [
            'name' => 'Ana',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'Este e-mail já tem conta. Entre com ela para aceitar o convite.');

        $this->assertSame('pending', Invitation::query()->value('status'));
    }

    public function test_conta_existente_entra_e_aceita(): void
    {
        [$company] = $this->companyWithAdmin();
        $token = $this->invite('ana@x.com', 'admin')['token'];
        $ana = $this->person('ana@x.com');

        $login = $this->asGuestLogin('ana@x.com');
        $this->assertNull($login['user']['current_company']);

        $this->app['auth']->forgetGuards();
        $this->withToken($login['token'])
            ->postJson("/api/auth/invitations/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('type', 'company')
            ->assertJsonPath('already_member', false);

        $this->assertSame('admin', CompanyUser::query()->where('user_id', $ana->id)->value('role'));
        $this->app['auth']->forgetGuards();
        $this->withToken($login['token'])->getJson('/api/auth/me')
            ->assertJsonPath('data.current_company.uuid', $company->uuid)
            ->assertJsonPath('data.current_role', 'admin');
    }

    public function test_logado_com_outro_email_nao_aceita(): void
    {
        $this->companyWithAdmin();
        $token = $this->invite('ana@x.com')['token'];
        $joao = $this->person('joao@y.com');
        $this->actIn($joao);

        $this->postJson("/api/auth/invitations/{$token}/accept")
            ->assertForbidden()
            ->assertJsonPath('message', 'Este convite é para ana@x.com, mas você entrou como joao@y.com.');

        $this->assertSame(0, CompanyUser::query()->where('user_id', $joao->id)->count());
        $this->assertSame('pending', Invitation::query()->value('status'));
    }

    public function test_quem_ja_e_membro_so_entra_e_o_convite_fica_aceito(): void
    {
        [$company] = $this->companyWithAdmin();
        $token = $this->invite('ana@x.com', 'admin')['token'];
        $ana = $this->person('ana@x.com');
        $ana->companies()->attach($company->id, ['role' => 'member']);
        $this->actIn($ana);

        $this->getJson("/api/auth/invitations/{$token}")->assertJsonPath('data.is_member', true);

        $this->postJson("/api/auth/invitations/{$token}/accept")
            ->assertOk()
            ->assertJsonPath('already_member', true);

        $this->assertSame(1, CompanyUser::query()->where('user_id', $ana->id)->count());
        $this->assertSame('member', CompanyUser::query()->where('user_id', $ana->id)->value('role'));
        $this->assertSame('accepted', Invitation::query()->value('status'));
    }

    public function test_aceitar_exige_login(): void
    {
        $this->companyWithAdmin();
        $token = $this->invite('ana@x.com')['token'];
        $this->asGuest();

        $this->postJson("/api/auth/invitations/{$token}/accept")->assertUnauthorized();
    }

    public function test_convite_expirado(): void
    {
        $this->companyWithAdmin();
        $token = $this->invite('ana@x.com')['token'];
        $ana = $this->person('ana@x.com');
        $this->travel(7)->days();
        $this->travel(1)->minute();
        $this->asGuest();

        $this->getJson("/api/auth/invitations/{$token}")
            ->assertOk()
            ->assertJsonPath('data.expired', true)
            ->assertJsonPath('data.name', 'Transportes X');

        $expired = 'Este convite expirou. Peça um novo link ao administrador da Transportes X.';
        $this->postJson("/api/auth/invitations/{$token}/register", [
            'name' => 'Ana',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertUnprocessable()->assertJsonPath('message', $expired);

        $this->actIn($ana);
        $this->postJson("/api/auth/invitations/{$token}/accept")
            ->assertUnprocessable()
            ->assertJsonPath('message', $expired);
        $this->assertSame(0, CompanyUser::query()->where('user_id', $ana->id)->count());
    }

    public function test_convite_cancelado_mostra_link_invalido(): void
    {
        $this->companyWithAdmin();
        $created = $this->invite('ana@x.com');
        $this->deleteJson("/api/company/invitations/{$created['data']['uuid']}")->assertOk();
        $this->asGuest();

        $this->getJson("/api/auth/invitations/{$created['token']}")
            ->assertNotFound()
            ->assertJsonPath('message', self::INVALID);
    }

    public function test_empresa_inativa_invalida_o_convite(): void
    {
        [$company] = $this->companyWithAdmin();
        $token = $this->invite('ana@x.com')['token'];
        $company->update(['status' => 'inactive']);
        $this->asGuest();

        $this->getJson("/api/auth/invitations/{$token}")
            ->assertNotFound()
            ->assertJsonPath('message', self::INVALID);
        $this->postJson("/api/auth/invitations/{$token}/register", [
            'name' => 'Ana',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertNotFound();
    }

    public function test_posto_excluido_invalida_o_convite(): void
    {
        [$station] = $this->stationWithAdmin();
        $token = $this->postJson('/api/station/invitations', ['email' => 'ana@x.com', 'role' => 'member'])
            ->assertCreated()
            ->json('token');
        $station->delete();
        $this->asGuest();

        $this->getJson("/api/auth/invitations/{$token}")->assertNotFound();
    }

    public function test_convite_do_posto_cria_conta_e_entra_no_posto(): void
    {
        [$station] = $this->stationWithAdmin();
        $token = $this->postJson('/api/station/invitations', ['email' => 'frentista@posto.com', 'role' => 'member'])
            ->assertCreated()
            ->json('token');
        $this->asGuest();

        $this->getJson("/api/auth/invitations/{$token}")
            ->assertJsonPath('data.type', 'fuel_station')
            ->assertJsonPath('data.name', 'Posto Exemplo');

        $response = $this->postJson("/api/auth/invitations/{$token}/register", [
            'name' => 'Frentista',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'senha-nova-123',
        ])->assertCreated()
            ->assertJsonPath('type', 'fuel_station');

        $user = User::query()->where('email', 'frentista@posto.com')->first();
        $this->assertSame('member', FuelStationUser::query()->where('user_id', $user->id)->value('role'));
        $this->assertSame(2, FuelStationUser::query()->where('fuel_station_id', $station->id)->count());

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('token'))->getJson('/api/station/me')->assertOk();
    }

    public function test_convite_publico_limita_20_por_minuto(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->getJson('/api/auth/invitations/'.str_repeat('b', 48))->assertNotFound();
        }

        $this->getJson('/api/auth/invitations/'.str_repeat('b', 48))
            ->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas. Aguarde um minuto e tente de novo.');
    }

    // ===================== HELPERS =====================

    private function companyWithAdmin(string $name = 'Transportes X', string $email = 'admin@x.com'): array
    {
        $company = $this->company($name);
        $admin = $this->person($email, 'Admin '.$name);
        $admin->companies()->attach($company->id, ['role' => 'admin']);
        $this->actIn($admin, $company);

        return [$company, $admin];
    }

    private function stationWithAdmin(): array
    {
        $station = $this->station();
        $admin = $this->person('dono@posto.com', 'Dono');
        $admin->fuelStations()->attach($station->id, ['role' => 'admin']);
        $this->actIn($admin, $station);

        return [$station, $admin];
    }

    private function invite(string $email, string $role = 'member'): array
    {
        return $this->postJson('/api/company/invitations', ['email' => $email, 'role' => $role])
            ->assertCreated()
            ->json();
    }

    private function asGuestLogin(string $email): array
    {
        $this->asGuest();
        $response = $this->postJson('/api/auth/login', [
            'email' => $email,
            'password' => 'senha-atual',
        ])->assertOk()->json();

        auth()->logout();

        return $response;
    }
}
