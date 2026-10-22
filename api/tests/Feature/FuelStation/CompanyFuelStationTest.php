<?php

namespace Tests\Feature\FuelStation;

use App\Models\Company;
use App\Models\CompanyFuelStation;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompanyFuelStationTest extends TestCase
{
    use RefreshDatabase;

    private const CNPJ = '19131243000197';

    public function test_empresa_pede_vinculo_de_posto_que_ja_existe(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ);

        $this->postJson('/api/registry/fuel-stations', [
            'cnpj' => '19.131.243/0001-97',
        ])->assertCreated()
            ->assertJsonPath('message', 'Pedido de vínculo enviado');

        $this->assertDatabaseHas('company_fuel_station', [
            'company_id' => $company->id,
            'fuel_station_id' => $station->id,
            'cnpj' => self::CNPJ,
            'status' => 'pending',
        ]);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, FuelStation::query()->count());

        $this->getJson('/api/registry/fuel-stations')
            ->assertOk()
            ->assertJsonPath('data.0.cnpj', self::CNPJ)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.fuel_station.corporate_name', 'POSTO EXEMPLO LTDA')
            ->assertJsonPath('data.0.company.uuid', $company->uuid);
    }

    public function test_empresa_pede_vinculo_antes_do_posto_existir(): void
    {
        $company = $this->actAsCompany();

        $this->postJson('/api/registry/fuel-stations', [
            'cnpj' => self::CNPJ,
        ])->assertCreated();

        $this->assertDatabaseHas('company_fuel_station', [
            'company_id' => $company->id,
            'fuel_station_id' => null,
            'cnpj' => self::CNPJ,
            'status' => 'pending',
        ]);
        $this->assertSame(0, FuelStation::query()->count());

        $this->getJson('/api/registry/fuel-stations')
            ->assertOk()
            ->assertJsonPath('data.0.fuel_station', null);
    }

    public function test_cnpj_duplicado_nao_cria_outro_pedido(): void
    {
        $this->actAsCompany();

        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este CNPJ já foi solicitado');

        $this->assertSame(1, CompanyFuelStation::query()->count());
    }

    public function test_cnpj_invalido_nao_cria_pedido(): void
    {
        $this->actAsCompany();

        $this->postJson('/api/registry/fuel-stations', ['cnpj' => '123'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Informe um CNPJ válido');

        $this->assertSame(0, CompanyFuelStation::query()->count());
    }

    public function test_empresa_remove_o_pedido_e_pode_pedir_de_novo(): void
    {
        $this->actAsCompany();
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->deleteJson('/api/registry/fuel-stations/'.self::CNPJ)
            ->assertOk()
            ->assertJsonPath('message', 'Pedido removido');

        $this->assertSame(0, CompanyFuelStation::query()->count());

        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();
        $this->assertSame(1, CompanyFuelStation::query()->count());
    }

    public function test_empresa_so_ve_e_remove_os_proprios_pedidos(): void
    {
        $company = $this->actAsCompany('00000000000191', 'Empresa A');
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $other = $this->actAsCompany('11222333000181', 'Empresa B');

        $this->getJson('/api/registry/fuel-stations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->deleteJson('/api/registry/fuel-stations/'.self::CNPJ)
            ->assertNotFound()
            ->assertJsonPath('message', 'Pedido não encontrado');

        $this->assertDatabaseHas('company_fuel_station', [
            'company_id' => $company->id,
            'cnpj' => self::CNPJ,
        ]);
        $this->assertSame(0, CompanyFuelStation::query()->where('company_id', $other->id)->count());
    }

    public function test_token_de_posto_nao_acessa_os_pedidos_da_empresa(): void
    {
        $station = $this->station(self::CNPJ);
        $user = User::factory()->create([
            'email' => 'posto-'.Str::uuid().'@example.com',
        ]);
        FuelStationUser::query()->create([
            'fuel_station_id' => $station->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'company_id' => null,
            'fuel_station_id' => $station->id,
        ])->save();

        $this->withToken($token->plainTextToken)
            ->getJson('/api/registry/fuel-stations')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Selecione uma empresa');
    }

    private function actAsCompany(string $cnpj = '00000000000191', string $tradeName = 'Empresa Teste'): Company
    {
        $this->app['auth']->forgetGuards();

        $company = Company::query()->create([
            'legal_name' => $tradeName.' LTDA',
            'trade_name' => $tradeName,
            'cnpj' => $cnpj,
        ]);

        $user = User::factory()->create([
            'email' => 'empresa-'.Str::uuid().'@example.com',
        ]);
        $user->companies()->attach($company->id, ['role' => 'admin']);

        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'company_id' => $company->id,
            'fuel_station_id' => null,
        ])->save();

        $this->withToken($token->plainTextToken);

        return $company;
    }

    private function station(string $cnpj): FuelStation
    {
        return FuelStation::query()->create([
            'cnpj' => $cnpj,
            'corporate_name' => 'POSTO EXEMPLO LTDA',
            'trade_name' => 'Posto Exemplo',
            'status' => 'active',
        ]);
    }
}
