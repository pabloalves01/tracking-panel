<?php

namespace Tests\Feature\Access;

use App\Models\Company;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_membro_consulta_e_nao_altera_os_modulos_da_empresa(): void
    {
        [$company, $owner, $admin, $member] = $this->company();
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
        $this->actingToken($this->token($owner, $company))
            ->getJson('/api/company/users')
            ->assertOk();

        $writes = [
            ['post', '/api/registry/employees'],
            ['put', '/api/registry/entities/00000000-0000-4000-8000-000000000001'],
            ['post', '/api/registry/carriers'],
            ['post', '/api/registry/suppliers'],
            ['post', '/api/registry/customers'],
            ['post', '/api/registry/drivers'],
            ['post', '/api/registry/vehicle-owners'],
            ['post', '/api/registry/vehicle-responsibles'],
            ['post', '/api/registry/fuel-stations'],
            ['post', '/api/registry/vehicles'],
            ['patch', '/api/company/users/'.$owner->uuid],
            ['post', '/api/company/invitations'],
            ['post', '/api/ship-operation-perimeters'],
        ];

        foreach ($writes as [$method, $url]) {
            $this->actingToken($memberToken)->json($method, $url, [])
                ->assertForbidden()
                ->assertJsonPath('message', 'Seu papel neste contexto só permite consulta.');
        }

        $this->actingToken($memberToken)
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->actingToken($this->token($owner, $company))
            ->postJson('/api/registry/employees', [
                'entity' => [
                    'name' => 'Maria Silva',
                    'person_type' => 'pf',
                    'cpf_cnpj' => '52998224725',
                ],
            ])->assertCreated()
            ->assertJsonPath('message', 'Funcionário criado com sucesso');

        $this->actingToken($this->token($admin, $company))
            ->postJson('/api/registry/suppliers', [])
            ->assertUnprocessable();
    }

    public function test_membro_do_posto_consulta_e_nao_altera(): void
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
        $this->actingToken($this->stationToken($owner, $station))
            ->getJson('/api/station/users')
            ->assertOk();

        $this->actingToken($memberToken)
            ->postJson('/api/station/companies/00000000-0000-4000-8000-000000000001/accept')
            ->assertForbidden()
            ->assertJsonPath('message', 'Seu papel neste contexto só permite consulta.');

        $this->actingToken($memberToken)
            ->patchJson('/api/station/users/'.$owner->uuid, ['role' => 'member'])
            ->assertForbidden();

        $this->actingToken($memberToken)
            ->postJson('/api/station/invitations', [
                'email' => 'novo@posto.test',
                'role' => 'member',
            ])->assertForbidden();

        $this->actingToken($this->stationToken($owner, $station))
            ->patchJson('/api/station/users/'.$member->uuid, ['role' => 'admin'])
            ->assertOk();
    }

    /**
     * @return array{0: Company, 1: User, 2: User, 3: User}
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
        $member = User::factory()->create(['email' => 'membro@melo.test', 'password' => 'senha1234']);
        $owner->companies()->attach($company->id, ['role' => 'owner', 'status' => 'active']);
        $admin->companies()->attach($company->id, ['role' => 'admin', 'status' => 'active']);
        $member->companies()->attach($company->id, ['role' => 'member', 'status' => 'active']);

        return [$company, $owner, $admin, $member];
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
