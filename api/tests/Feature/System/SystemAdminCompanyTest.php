<?php

namespace Tests\Feature\System;

use App\Models\Company;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SystemAdminCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_do_sistema_grava_estado_e_cidade_com_ou_sem_contexto(): void
    {
        [$company, $state, $city] = $this->place();
        $admin = User::factory()->create([
            'email' => 'logiv@logiv.test',
            'password' => 'senha1234',
            'is_system_admin' => true,
        ]);
        $admin->companies()->attach($company->id, ['role' => 'member', 'status' => 'active']);
        $station = FuelStation::query()->create([
            'cnpj' => '19131243000197',
            'corporate_name' => 'Posto LTDA',
            'status' => 'active',
        ]);
        FuelStationUser::query()->create([
            'fuel_station_id' => $station->id,
            'user_id' => $admin->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $payload = [
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
            'state_id' => $state->id,
            'city_id' => $city->id,
        ];

        $this->actingToken($this->token($admin))
            ->putJson('/api/system/companies/'.$company->uuid, $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Empresa atualizada com sucesso');

        $company->update(['state_id' => null, 'city_id' => null, 'trade_name' => 'Antes']);

        $this->actingToken($this->token($admin, $company))
            ->putJson('/api/system/companies/'.$company->uuid, $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Empresa atualizada com sucesso');

        $company->refresh();
        $this->assertSame($state->id, $company->state_id);
        $this->assertSame($city->id, $company->city_id);
        $this->assertSame('Melo', $company->trade_name);

        $company->update(['trade_name' => 'Antes']);

        $this->actingToken($this->token($admin, null, $station))
            ->putJson('/api/system/companies/'.$company->uuid, $payload)
            ->assertOk();

        $this->assertSame('Melo', $company->fresh()->trade_name);

        $this->actingToken($this->token($admin, $company))
            ->getJson('/api/locations/states')
            ->assertOk()
            ->assertJsonFragment(['id' => $state->id]);

        $this->actingToken($this->token($admin, $company))
            ->getJson('/api/locations/states/'.$state->id.'/cities')
            ->assertOk()
            ->assertJsonFragment(['id' => $city->id]);
    }

    public function test_admin_do_sistema_membro_escreve_nas_rotas_de_sistema(): void
    {
        Mail::fake();
        [$company] = $this->place();
        $admin = User::factory()->create([
            'email' => 'logiv@logiv.test',
            'password' => 'senha1234',
            'is_system_admin' => true,
        ]);
        $admin->companies()->attach($company->id, ['role' => 'member', 'status' => 'active']);
        $token = $this->token($admin, $company);

        $this->actingToken($token)
            ->postJson('/api/registry/employees', [])
            ->assertUnprocessable();

        $this->actingToken($token)
            ->postJson('/api/system/companies/'.$company->uuid.'/deactivate')
            ->assertOk()
            ->assertJsonPath('message', 'Empresa desativada com sucesso');

        $this->assertSame('inactive', $company->fresh()->status);

        $this->actingToken($token)
            ->postJson('/api/system/companies/'.$company->uuid.'/activate')
            ->assertOk()
            ->assertJsonPath('message', 'Empresa ativada com sucesso');

        $this->actingToken($token)
            ->postJson('/api/system/companies/'.$company->uuid.'/invitations', [
                'email' => 'novo.admin@melo.test',
                'role' => 'admin',
            ])
            ->assertCreated()
            ->assertJsonPath('data.email', 'novo.admin@melo.test');

        $member = User::factory()->create(['email' => 'membro@melo.test', 'password' => 'senha1234']);
        $member->companies()->attach($company->id, ['role' => 'member', 'status' => 'active']);

        $this->actingToken($this->token($member, $company))
            ->putJson('/api/system/companies/'.$company->uuid, [
                'legal_name' => 'Melo LTDA',
                'trade_name' => 'Melo',
                'cnpj' => '00000000000191',
            ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Acesso restrito ao administrador do sistema.');
    }

    /**
     * @return array{0: Company, 1: State, 2: City}
     */
    private function place(): array
    {
        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        $state = State::query()->create([
            'ibge_code' => 31,
            'name' => 'Minas Gerais',
            'uf' => 'MG',
            'region' => 'Sudeste',
        ]);
        $city = City::query()->create([
            'ibge_code' => 3106200,
            'state_id' => $state->id,
            'name' => 'Belo Horizonte',
        ]);

        return [$company, $state, $city];
    }

    private function actingToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function token(User $user, ?Company $company = null, ?FuelStation $station = null): string
    {
        $access = $user->createToken('web');
        $access->accessToken->forceFill([
            'company_id' => $company?->id,
            'fuel_station_id' => $station?->id,
        ])->save();

        return $access->plainTextToken;
    }
}
