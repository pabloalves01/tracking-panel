<?php

namespace Tests\Feature\Access;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_proprietario_muda_papel_e_protege_o_ultimo(): void
    {
        [$company, $owner, $admin] = $this->company();

        $this->actingToken($this->token($owner, $company))
            ->patchJson('/api/company/users/'.$admin->uuid, ['role' => 'member'])
            ->assertOk();

        $this->assertSame('member', CompanyUser::query()->where('user_id', $admin->id)->value('role'));

        $this->actingToken($this->token($owner, $company))
            ->patchJson('/api/company/users/'.$owner->uuid, ['role' => 'admin'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Você não pode alterar o próprio papel');

        $this->actingToken($this->token($owner, $company))
            ->deleteJson('/api/company/users/'.$owner->uuid)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'É preciso manter um proprietário');
    }

    public function test_admin_nao_mexe_no_proprietario_e_remove_membro(): void
    {
        [$company, $owner, $admin] = $this->company();
        $member = User::factory()->create(['email' => 'membro@melo.test']);
        $member->companies()->attach($company->id, ['role' => 'member', 'status' => 'active']);

        $this->actingToken($this->token($admin, $company))
            ->patchJson('/api/company/users/'.$owner->uuid, ['role' => 'admin'])
            ->assertForbidden();

        $this->actingToken($this->token($admin, $company))
            ->deleteJson('/api/company/users/'.$member->uuid)
            ->assertOk();

        $this->assertSame('suspended', CompanyUser::query()->where('user_id', $member->id)->value('status'));
        $this->assertFalse($member->fresh()->companies()->whereKey($company->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'membership.suspended']);
    }

    public function test_membro_nao_lista_usuarios(): void
    {
        [$company, $owner, $admin] = $this->company();
        CompanyUser::query()->where('user_id', $admin->id)->update(['role' => 'member']);

        $this->actingToken($this->token($admin, $company))
            ->getJson('/api/company/users')
            ->assertForbidden();

        $this->actingToken($this->token($owner, $company))
            ->getJson('/api/company/users')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_empresa_inativa_e_membro_suspenso_somem_do_login(): void
    {
        [$company, $owner] = $this->company();
        $company->update(['status' => 'inactive']);

        $login = $this->postJson('/api/auth/login', [
            'email' => $owner->email,
            'password' => 'senha1234',
        ])->assertOk();

        $this->assertSame([], $login->json('companies'));
        $this->assertFalse($login->json('user.has_company'));

        $company->update(['status' => 'active']);
        CompanyUser::query()->where('user_id', $owner->id)->update(['status' => 'suspended']);

        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->postJson('/api/auth/login', [
            'email' => $owner->email,
            'password' => 'senha1234',
        ])->assertOk()
            ->assertJsonPath('user.has_company', false);
    }

    public function test_perda_de_acesso_devolve_codigo_fixo(): void
    {
        [$company, $owner] = $this->company();
        $token = $this->token($owner, $company);
        CompanyUser::query()->where('user_id', $owner->id)->update(['status' => 'suspended']);

        $this->actingToken($token)
            ->getJson('/api/registry/fuel-stations')
            ->assertForbidden()
            ->assertJsonPath('code', 'context_access_lost');
    }

    public function test_posto_replica_as_mesmas_regras_de_papel(): void
    {
        $station = FuelStation::query()->create([
            'cnpj' => '19131243000197',
            'corporate_name' => 'Posto LTDA',
            'status' => 'active',
        ]);
        $owner = User::factory()->create(['email' => 'dono@posto.test', 'password' => 'senha1234']);
        $member = User::factory()->create(['email' => 'equipe@posto.test', 'password' => 'senha1234']);
        FuelStationUser::query()->create([
            'fuel_station_id' => $station->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        FuelStationUser::query()->create([
            'fuel_station_id' => $station->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $token = $owner->createToken('web');
        $token->accessToken->forceFill(['fuel_station_id' => $station->id])->save();

        $this->actingToken($token->plainTextToken)
            ->patchJson('/api/station/users/'.$member->uuid, ['role' => 'admin'])
            ->assertOk();

        $this->assertSame('admin', FuelStationUser::query()->where('user_id', $member->id)->value('role'));
    }

    /**
     * @return array{0: Company, 1: User, 2: User}
     */
    private function company(): array
    {
        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        $owner = User::factory()->create(['email' => 'owner@melo.test', 'password' => 'senha1234']);
        $admin = User::factory()->create(['email' => 'admin@melo.test', 'password' => 'senha1234']);
        $owner->companies()->attach($company->id, ['role' => 'owner', 'status' => 'active']);
        $admin->companies()->attach($company->id, ['role' => 'admin', 'status' => 'active']);

        return [$company, $owner, $admin];
    }

    private function actingToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function token(User $user, Company $company): string
    {
        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'company_id' => $company->id,
            'fuel_station_id' => null,
        ])->save();

        return $token->plainTextToken;
    }
}
