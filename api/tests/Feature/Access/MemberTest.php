<?php

namespace Tests\Feature\Access;

use App\Models\CompanyUser;
use App\Models\FuelStationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberTest extends TestCase
{
    use ActsInContext;
    use RefreshDatabase;

    private const ONLY_ADMIN = 'Apenas administradores podem gerenciar usuários.';

    public function test_admin_lista_os_membros_da_empresa(): void
    {
        [$company, $admin, $member] = $this->team();
        User::factory()->create(['email' => 'logiv@logiv.com.br', 'is_system_admin' => true]);
        $other = $this->company('Outra');
        $this->person('fora@outra.com')->companies()->attach($other->id, ['role' => 'admin']);

        $this->actIn($admin, $company);

        $this->getJson('/api/company/users')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.uuid', $admin->uuid)
            ->assertJsonPath('data.0.email', 'admin@x.com')
            ->assertJsonPath('data.0.role', 'admin')
            ->assertJsonPath('data.0.is_you', true)
            ->assertJsonPath('data.1.uuid', $member->uuid)
            ->assertJsonPath('data.1.role', 'member')
            ->assertJsonPath('data.1.is_you', false)
            ->assertJsonStructure(['data' => [['uuid', 'name', 'email', 'role', 'joined_at', 'is_you']]]);
    }

    public function test_membro_comum_nao_ve_nem_altera_usuarios(): void
    {
        [$company, $admin, $member] = $this->team();
        $this->actIn($member, $company);

        $this->getJson('/api/company/users')->assertForbidden()->assertJsonPath('message', self::ONLY_ADMIN);
        $this->patchJson("/api/company/users/{$admin->uuid}", ['role' => 'member'])
            ->assertForbidden()
            ->assertJsonPath('message', self::ONLY_ADMIN);
        $this->deleteJson("/api/company/users/{$admin->uuid}")
            ->assertForbidden()
            ->assertJsonPath('message', self::ONLY_ADMIN);

        $this->getJson('/api/auth/me')->assertJsonPath('data.current_role', 'member');
        $this->assertSame(2, CompanyUser::query()->count());
    }

    public function test_admin_troca_o_papel(): void
    {
        [$company, $admin, $member] = $this->team();
        $this->actIn($admin, $company);

        $this->patchJson("/api/company/users/{$member->uuid}", ['role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('message', 'Papel alterado');
        $this->assertSame('admin', $this->roleOf($member));

        // Com dois admins, dá pra rebaixar a si mesmo.
        $this->patchJson("/api/company/users/{$admin->uuid}", ['role' => 'member'])->assertOk();
        $this->assertSame('member', $this->roleOf($admin));
    }

    public function test_papel_invalido_ou_em_array(): void
    {
        [$company, $admin, $member] = $this->team();
        $this->actIn($admin, $company);

        $this->patchJson("/api/company/users/{$member->uuid}", ['role' => 'owner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
        $this->patchJson("/api/company/users/{$member->uuid}", ['role' => ['admin']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    public function test_nao_rebaixa_nem_remove_o_ultimo_admin(): void
    {
        [$company, $admin, $member] = $this->team();
        $this->actIn($admin, $company);
        $message = 'A empresa precisa ter pelo menos um administrador.';

        $this->patchJson("/api/company/users/{$admin->uuid}", ['role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonPath('message', $message);
        $this->deleteJson("/api/company/users/{$admin->uuid}")
            ->assertUnprocessable()
            ->assertJsonPath('message', $message);

        $this->assertSame('admin', $this->roleOf($admin));
        $this->assertNotNull($member);
    }

    public function test_admin_remove_membro_e_ele_perde_o_acesso_na_hora(): void
    {
        [$company, $admin, $member] = $this->team();
        $memberToken = $this->actIn($member, $company);
        $this->getJson('/api/registry/entities')->assertOk();

        $this->actIn($admin, $company);
        $this->deleteJson("/api/company/users/{$member->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Usuário removido');

        $this->app['auth']->forgetGuards();
        $this->withToken($memberToken)->getJson('/api/registry/entities')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não tem acesso a esta empresa')
            ->assertJsonPath('code', 'context_access_lost');
        $this->withToken($memberToken)->getJson('/api/auth/contexts')->assertJsonCount(0, 'companies');
    }

    public function test_membro_sai_da_empresa(): void
    {
        [$company, , $member] = $this->team();
        $this->actIn($member, $company);

        $this->deleteJson("/api/company/users/{$member->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Você saiu da empresa');

        $this->assertSame(1, CompanyUser::query()->count());
    }

    public function test_ultimo_admin_nao_sai(): void
    {
        [$company, $admin] = $this->team();
        $this->actIn($admin, $company);

        $this->deleteJson("/api/company/users/{$admin->uuid}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'A empresa precisa ter pelo menos um administrador.');
    }

    public function test_quem_saiu_pode_voltar_por_convite(): void
    {
        [$company, $admin, $member] = $this->team();
        $this->actIn($admin, $company);
        $this->deleteJson("/api/company/users/{$member->uuid}")->assertOk();

        $token = $this->postJson('/api/company/invitations', ['email' => $member->email, 'role' => 'admin'])
            ->assertCreated()
            ->json('token');

        $this->actIn($member);
        $this->postJson("/api/auth/invitations/{$token}/accept")->assertOk();

        $this->assertSame('admin', $this->roleOf($member));
    }

    public function test_usuario_que_nao_e_membro_ou_uuid_invalido(): void
    {
        [$company, $admin] = $this->team();
        $outsider = $this->person('fora@y.com');
        $this->actIn($admin, $company);

        $this->patchJson("/api/company/users/{$outsider->uuid}", ['role' => 'admin'])
            ->assertNotFound()
            ->assertJsonPath('message', 'Usuário não encontrado');
        $this->deleteJson('/api/company/users/'.$this->uuidNotFound())->assertNotFound();
        $this->deleteJson('/api/company/users/123')->assertNotFound();
    }

    public function test_usuarios_do_posto_seguem_as_mesmas_regras(): void
    {
        $station = $this->station();
        $admin = $this->person('dono@posto.com', 'Dono');
        $admin->fuelStations()->attach($station->id, ['role' => 'admin']);
        $member = $this->person('frentista@posto.com', 'Frentista');
        $member->fuelStations()->attach($station->id, ['role' => 'member']);

        $this->actIn($member, $station);
        $this->getJson('/api/station/users')->assertForbidden()->assertJsonPath('message', self::ONLY_ADMIN);

        $this->actIn($admin, $station);
        $this->getJson('/api/station/users')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/company/users')->assertStatus(409);
        $this->deleteJson("/api/station/users/{$admin->uuid}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'O posto precisa ter pelo menos um administrador.');
        $this->patchJson("/api/station/users/{$member->uuid}", ['role' => 'admin'])->assertOk();
        $this->deleteJson("/api/station/users/{$admin->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Você saiu do posto');

        $this->assertSame(1, FuelStationUser::query()->count());
        $this->assertSame('admin', FuelStationUser::query()->value('role'));
    }

    private function team(): array
    {
        $company = $this->company();
        $admin = $this->person('admin@x.com', 'Admin');
        $admin->companies()->attach($company->id, ['role' => 'admin']);
        $member = $this->person('membro@x.com', 'Membro');
        $member->companies()->attach($company->id, ['role' => 'member']);

        // Entrou depois do admin.
        CompanyUser::query()->where('user_id', $member->id)->update(['created_at' => now()->addMinute()]);

        return [$company, $admin, $member];
    }

    private function roleOf(User $user): ?string
    {
        return CompanyUser::query()->where('user_id', $user->id)->value('role');
    }
}
