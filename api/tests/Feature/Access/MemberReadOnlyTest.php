<?php

namespace Tests\Feature\Access;

use App\Models\Company;
use App\Models\CompanyFuelStation;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_membro_grava_cadastros_e_postos_e_nao_gerencia_usuarios(): void
    {
        [$company, $owner, $member] = $this->company();
        $memberToken = $this->token($member, $company);

        $reads = [
            '/api/registry/employees',
            '/api/registry/entities',
            '/api/registry/carriers',
            '/api/registry/suppliers',
            '/api/registry/customers',
            '/api/registry/drivers',
            '/api/registry/vehicle-owners',
            '/api/registry/vehicle-responsibles',
            '/api/registry/fuel-stations',
            '/api/registry/vehicles',
        ];

        foreach ($reads as $url) {
            $this->actingToken($memberToken)->getJson($url)->assertOk();
        }

        $this->actingToken($memberToken)
            ->getJson('/api/company/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não gerencia os usuários deste contexto.');
        $this->actingToken($memberToken)
            ->getJson('/api/company/invitations')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não gerencia os usuários deste contexto.');

        $this->actingToken($memberToken)
            ->postJson('/api/company/invitations', [
                'email' => 'novo@melo.test',
                'role' => 'member',
            ])->assertForbidden()
            ->assertJsonPath('message', 'Você não gerencia os usuários deste contexto.');

        $this->actingToken($memberToken)
            ->patchJson('/api/company/users/'.$owner->uuid, ['role' => 'member'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não gerencia os usuários deste contexto.');

        $this->actingToken($memberToken)
            ->postJson('/api/registry/employees', [
                'entity' => [
                    'name' => 'Maria Silva',
                    'person_type' => 'pf',
                    'cpf_cnpj' => '52998224725',
                ],
            ])->assertCreated()
            ->assertJsonPath('message', 'Funcionário criado com sucesso');

        $created = $this->actingToken($memberToken)
            ->getJson('/api/registry/employees')
            ->assertOk()
            ->json('data.0');

        $this->actingToken($memberToken)
            ->putJson('/api/registry/entities/'.$created['entity_uuid'], [
                'name' => 'Maria Corrigida',
                'person_type' => 'pf',
                'cpf_cnpj' => '52998224725',
            ])->assertOk();

        $this->actingToken($memberToken)
            ->getJson('/api/registry/employees')
            ->assertJsonPath('data.0.name', 'Maria Corrigida');

        $this->actingToken($memberToken)
            ->postJson('/api/registry/employees/'.$created['uuid'].'/deactivate')
            ->assertOk()
            ->assertJsonPath('message', 'Funcionário desativado com sucesso');

        $station = FuelStation::query()->create([
            'cnpj' => '19131243000197',
            'corporate_name' => 'Posto Exemplo LTDA',
            'status' => 'active',
        ]);

        $this->actingToken($memberToken)
            ->postJson('/api/registry/fuel-stations', [
                'cnpj' => '19131243000197',
            ])->assertCreated()
            ->assertJsonPath('message', 'Pedido de vínculo enviado');

        $this->actingToken($memberToken)
            ->deleteJson('/api/registry/fuel-stations/'.$station->uuid)
            ->assertOk()
            ->assertJsonPath('message', 'Pedido removido');

        $this->assertSame(0, CompanyFuelStation::query()->count());
    }

    public function test_membro_do_posto_aceita_vinculo_e_nao_gerencia_usuarios(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
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
        CompanyFuelStation::query()->create([
            'company_id' => $company->id,
            'fuel_station_id' => $station->id,
            'status' => 'request_sent',
        ]);

        $memberToken = $this->stationToken($member, $station);

        $this->actingToken($memberToken)->getJson('/api/station/companies')->assertOk();
        $this->actingToken($memberToken)->getJson('/api/station/me')->assertOk();
        $this->actingToken($memberToken)
            ->getJson('/api/station/users')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não gerencia os usuários deste contexto.');
        $this->actingToken($memberToken)
            ->getJson('/api/station/invitations')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não gerencia os usuários deste contexto.');

        $this->actingToken($memberToken)
            ->postJson('/api/station/invitations', [
                'email' => 'novo@posto.test',
                'role' => 'member',
            ])->assertForbidden()
            ->assertJsonPath('message', 'Você não gerencia os usuários deste contexto.');

        $this->actingToken($memberToken)
            ->patchJson('/api/station/users/'.$owner->uuid, ['role' => 'member'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não gerencia os usuários deste contexto.');

        $this->actingToken($memberToken)
            ->postJson('/api/station/companies/'.$company->uuid.'/accept')
            ->assertOk()
            ->assertJsonPath('message', 'Vínculo aceito');

        $this->assertSame('active', CompanyFuelStation::query()->first()->status);
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
        $member = User::factory()->create(['email' => 'membro@melo.test', 'password' => 'senha1234']);
        $owner->companies()->attach($company->id, ['role' => 'owner', 'status' => 'active']);
        $member->companies()->attach($company->id, ['role' => 'member', 'status' => 'active']);

        return [$company, $owner, $member];
    }

    private function actingToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function token(User $user, Company $company): string
    {
        $access = $user->createToken('web');
        $access->accessToken->forceFill([
            'company_id' => $company->id,
            'fuel_station_id' => null,
        ])->save();

        return $access->plainTextToken;
    }

    private function stationToken(User $user, FuelStation $station): string
    {
        $access = $user->createToken('web');
        $access->accessToken->forceFill([
            'company_id' => null,
            'fuel_station_id' => $station->id,
        ])->save();

        return $access->plainTextToken;
    }
}
