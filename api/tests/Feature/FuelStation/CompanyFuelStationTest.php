<?php

namespace Tests\Feature\FuelStation;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyFuelStation;
use App\Models\CompanyUser;
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
            'status' => 'request_sent',
        ]);
        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, FuelStation::query()->count());

        $this->getJson('/api/registry/fuel-stations')
            ->assertOk()
            ->assertJsonPath('data.0.cnpj', self::CNPJ)
            ->assertJsonPath('data.0.status', 'request_sent')
            ->assertJsonPath('data.0.fuel_station.corporate_name', 'POSTO EXEMPLO LTDA')
            ->assertJsonPath('data.0.company.uuid', $company->uuid);
    }

    public function test_cnpj_sem_cadastro_no_logiv_nao_cria_pedido(): void
    {
        $this->actAsCompany();

        $this->postJson('/api/registry/fuel-stations', [
            'cnpj' => self::CNPJ,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Este posto não possui cadastro no Logiv')
            ->assertJsonPath('errors.cnpj.0', 'Este posto não possui cadastro no Logiv');

        $this->assertSame(0, CompanyFuelStation::query()->count());
        $this->assertSame(0, FuelStation::query()->count());
    }

    public function test_cnpj_mascarado_sem_cadastro_nao_cria_pedido(): void
    {
        $this->actAsCompany();

        $this->postJson('/api/registry/fuel-stations', [
            'cnpj' => '19.131.243/0001-97',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Este posto não possui cadastro no Logiv')
            ->assertJsonPath('errors.cnpj.0', 'Este posto não possui cadastro no Logiv');

        $this->assertSame(0, CompanyFuelStation::query()->count());
    }

    public function test_posto_excluido_nao_cria_pedido(): void
    {
        $this->actAsCompany();
        $this->station(self::CNPJ)->delete();

        $this->postJson('/api/registry/fuel-stations', [
            'cnpj' => self::CNPJ,
        ])->assertUnprocessable()
            ->assertJsonPath('errors.cnpj.0', 'Este posto não possui cadastro no Logiv');

        $this->assertSame(0, CompanyFuelStation::query()->count());
    }

    public function test_consulta_posto_por_cnpj(): void
    {
        $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $station->update([
            'zip_code' => '01310100',
            'street' => 'Avenida Paulista',
            'number' => '1000',
            'complement' => 'Loja 1',
            'district' => 'Bela Vista',
        ]);

        $this->getJson('/api/registry/fuel-stations/by-cnpj?cnpj='.urlencode('19.131.243/0001-97'))
            ->assertOk()
            ->assertJsonPath('data.cnpj', self::CNPJ)
            ->assertJsonPath('data.corporate_name', 'POSTO EXEMPLO LTDA')
            ->assertJsonPath('data.trade_name', 'Posto Exemplo')
            ->assertJsonPath('data.zip_code', '01310100')
            ->assertJsonPath('data.street', 'Avenida Paulista')
            ->assertJsonPath('data.number', '1000')
            ->assertJsonPath('data.complement', 'Loja 1')
            ->assertJsonPath('data.district', 'Bela Vista')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonMissingPath('data.id');
    }

    public function test_consulta_de_posto_por_cnpj_respeita_o_limite(): void
    {
        $this->actAsCompany();

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->getJson('/api/registry/fuel-stations/by-cnpj?cnpj='.self::CNPJ)
                ->assertUnprocessable();
        }

        $this->getJson('/api/registry/fuel-stations/by-cnpj?cnpj='.self::CNPJ)
            ->assertStatus(429);
    }

    public function test_consulta_cnpj_sem_cadastro(): void
    {
        $this->actAsCompany();

        $this->getJson('/api/registry/fuel-stations/by-cnpj?cnpj='.urlencode('19.131.243/0001-97'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.cnpj.0', 'Este posto não possui cadastro no Logiv');
    }

    public function test_consulta_posto_excluido(): void
    {
        $this->actAsCompany();
        $this->station(self::CNPJ)->delete();

        $this->getJson('/api/registry/fuel-stations/by-cnpj?cnpj='.self::CNPJ)
            ->assertUnprocessable()
            ->assertJsonPath('errors.cnpj.0', 'Este posto não possui cadastro no Logiv');
    }

    public function test_posto_inativo_pode_ser_consultado_e_vinculado(): void
    {
        $this->actAsCompany();
        $this->station(self::CNPJ, 'POSTO EXEMPLO LTDA', 'inactive');

        $this->getJson('/api/registry/fuel-stations/by-cnpj?cnpj='.self::CNPJ)
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.corporate_name', 'POSTO EXEMPLO LTDA');

        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])
            ->assertCreated();

        $this->assertSame(1, CompanyFuelStation::query()->count());
    }

    public function test_listagem_mostra_o_nome_do_posto_excluido(): void
    {
        $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();
        $station->delete();

        $this->getJson('/api/registry/fuel-stations')
            ->assertOk()
            ->assertJsonPath('data.0.cnpj', self::CNPJ)
            ->assertJsonPath('data.0.fuel_station.corporate_name', 'POSTO EXEMPLO LTDA')
            ->assertJsonPath('data.0.fuel_station.trade_name', 'Posto Exemplo');
    }

    public function test_cnpj_duplicado_nao_cria_outro_pedido(): void
    {
        $this->actAsCompany();
        $this->station(self::CNPJ);

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
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->deleteJson('/api/registry/fuel-stations/'.$station->uuid)
            ->assertOk()
            ->assertJsonPath('message', 'Pedido removido');

        $this->assertSame(0, CompanyFuelStation::query()->count());

        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();
        $this->assertSame(1, CompanyFuelStation::query()->count());
    }

    public function test_empresa_so_ve_e_remove_os_proprios_pedidos(): void
    {
        $company = $this->actAsCompany('00000000000191', 'Empresa A');
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $other = $this->actAsCompany('11222333000181', 'Empresa B');

        $this->getJson('/api/registry/fuel-stations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->deleteJson('/api/registry/fuel-stations/'.$station->uuid)
            ->assertNotFound()
            ->assertJsonPath('message', 'Pedido não encontrado');

        $this->assertDatabaseHas('company_fuel_station', [
            'company_id' => $company->id,
            'fuel_station_id' => $station->id,
        ]);
        $this->assertSame(0, CompanyFuelStation::query()->where('company_id', $other->id)->count());
    }

    public function test_posto_aceita_e_desfaz_o_vinculo(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->actAsStation($station);

        $this->postJson('/api/station/companies/'.$company->uuid.'/accept')
            ->assertOk()
            ->assertJsonPath('message', 'Vínculo aceito');

        $this->assertSame('active', CompanyFuelStation::query()->first()->status);
        $this->assertTrue($company->fuelStations()->whereKey($station->id)->exists());

        $this->postJson('/api/station/companies/'.$company->uuid.'/reject')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este pedido não está pendente');

        $this->postJson('/api/station/companies/'.$company->uuid.'/disconnect')
            ->assertOk()
            ->assertJsonPath('message', 'Conexão encerrada');

        $this->assertSame('disconnected', CompanyFuelStation::query()->first()->status);
        $this->assertFalse($company->fuelStations()->whereKey($station->id)->exists());

        $this->postJson('/api/station/companies/'.$company->uuid.'/disconnect')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este vínculo não está ativo');
    }

    public function test_posto_recusa_pedido_pendente(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->actAsStation($station);

        $this->postJson('/api/station/companies/'.$company->uuid.'/reject')
            ->assertOk()
            ->assertJsonPath('message', 'Pedido recusado');

        $this->assertSame('rejected', CompanyFuelStation::query()->first()->status);
        $this->assertFalse($company->fuelStations()->whereKey($station->id)->exists());

        $this->postJson('/api/station/companies/'.$company->uuid.'/accept')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este pedido não está pendente');

        $this->postJson('/api/station/companies/'.$company->uuid.'/reject')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este pedido já foi recusado');

        $this->actAsExistingCompany($company);

        $this->postJson('/api/registry/fuel-stations/'.$station->uuid.'/resend-request')
            ->assertOk()
            ->assertJsonPath('message', 'Pedido reenviado');

        $this->assertSame('request_sent', CompanyFuelStation::query()->first()->status);
    }

    public function test_empresa_reenvia_vinculo_desconectado(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->actAsStation($station);
        $this->postJson('/api/station/companies/'.$company->uuid.'/accept')->assertOk();
        $this->postJson('/api/station/companies/'.$company->uuid.'/disconnect')->assertOk();

        $this->actAsExistingCompany($company);

        $this->postJson('/api/registry/fuel-stations/'.$station->uuid.'/resend-request')
            ->assertOk()
            ->assertJsonPath('message', 'Pedido reenviado');

        $this->assertSame('request_sent', CompanyFuelStation::query()->first()->status);
        $this->assertFalse($company->fuelStations()->whereKey($station->id)->exists());
    }

    public function test_reenvio_com_uuid_invalido_nao_encontra_o_pedido(): void
    {
        $this->actAsCompany();
        $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => '19.131.243/0001-97'])->assertCreated();

        $this->postJson('/api/registry/fuel-stations/'.self::CNPJ.'/resend-request')
            ->assertNotFound()
            ->assertJsonPath('message', 'Pedido não encontrado');
    }

    public function test_reenvio_falha_quando_o_posto_foi_excluido(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->actAsStation($station);
        $this->postJson('/api/station/companies/'.$company->uuid.'/reject')->assertOk();
        $station->delete();

        $this->actAsExistingCompany($company);

        $this->postJson('/api/registry/fuel-stations/'.$station->uuid.'/resend-request')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este posto não possui cadastro no Logiv')
            ->assertJsonPath('errors.cnpj.0', 'Este posto não possui cadastro no Logiv');

        $this->assertSame('rejected', CompanyFuelStation::query()->first()->status);
        $this->assertSame(1, CompanyFuelStation::query()->count());
    }

    public function test_empresa_encerra_vinculo_ativo_em_vez_de_apagar(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->actAsStation($station);
        $this->postJson('/api/station/companies/'.$company->uuid.'/accept')->assertOk();

        $this->actAsExistingCompany($company);

        $this->postJson('/api/registry/fuel-stations/'.$station->uuid.'/disconnect')
            ->assertOk()
            ->assertJsonPath('message', 'Conexão encerrada');

        $this->assertSame(1, CompanyFuelStation::query()->count());
        $this->assertSame('disconnected', CompanyFuelStation::query()->first()->status);
        $this->assertFalse($company->fuelStations()->whereKey($station->id)->exists());
    }

    public function test_empresa_remove_pedido_recusado(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->actAsStation($station);
        $this->postJson('/api/station/companies/'.$company->uuid.'/reject')->assertOk();

        $this->actAsExistingCompany($company);

        $this->deleteJson('/api/registry/fuel-stations/'.$station->uuid)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Só é possível cancelar um convite pendente');

        $this->assertSame(1, CompanyFuelStation::query()->count());
        $this->assertSame('rejected', CompanyFuelStation::query()->first()->status);
    }

    public function test_empresa_nao_remove_conexao_encerrada(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->actAsStation($station);
        $this->postJson('/api/station/companies/'.$company->uuid.'/accept')->assertOk();
        $this->postJson('/api/station/companies/'.$company->uuid.'/disconnect')->assertOk();

        $this->actAsExistingCompany($company);

        $this->deleteJson('/api/registry/fuel-stations/'.$station->uuid)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Só é possível cancelar um convite pendente');

        $this->assertSame('disconnected', CompanyFuelStation::query()->first()->status);
    }

    public function test_posto_so_ve_e_responde_os_proprios_pedidos(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ, 'POSTO EXEMPLO LTDA');
        $other = $this->station('34028316000103', 'OUTRO POSTO LTDA');
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->actAsStation($station);

        $this->getJson('/api/station/companies')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cnpj', self::CNPJ)
            ->assertJsonPath('data.0.company.uuid', $company->uuid);

        $this->postJson('/api/station/companies/'.$company->uuid.'/accept')->assertOk();

        $this->actAsStation($other);

        $this->getJson('/api/station/companies')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/station/companies/'.$company->uuid.'/reject')
            ->assertNotFound()
            ->assertJsonPath('message', 'Pedido não encontrado');

        $this->assertSame('active', CompanyFuelStation::query()->where('fuel_station_id', $station->id)->value('status'));
    }

    public function test_token_de_empresa_nao_acessa_os_pedidos_do_posto(): void
    {
        $this->actAsCompany();

        $this->getJson('/api/station/companies')
            ->assertStatus(409)
            ->assertJsonPath('message', 'Selecione um posto');
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

        $this->getJson('/api/registry/fuel-stations/by-cnpj?cnpj='.self::CNPJ)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Selecione uma empresa');
    }

    public function test_empresa_nao_convida_o_proprio_cnpj(): void
    {
        $company = $this->actAsCompany(self::CNPJ);
        $this->station(self::CNPJ);

        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])
            ->assertUnprocessable()
            ->assertJsonPath('errors.cnpj.0', 'A empresa não pode convidar o próprio CNPJ');

        $this->assertSame(0, CompanyFuelStation::query()->where('company_id', $company->id)->count());
    }

    public function test_membro_nao_pede_nem_encerra_vinculo(): void
    {
        $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        CompanyUser::query()->update(['role' => 'member']);

        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])
            ->assertForbidden();

        CompanyUser::query()->update(['role' => 'admin']);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();
        CompanyUser::query()->update(['role' => 'member']);

        $this->deleteJson('/api/registry/fuel-stations/'.$station->uuid)
            ->assertForbidden();
    }

    public function test_pedido_grava_auditoria(): void
    {
        $this->actAsCompany();
        $this->station(self::CNPJ);

        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'company_fuel_station.requested',
        ]);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_empresa_inativa_ou_excluida_nao_aparece_para_o_posto(): void
    {
        $company = $this->actAsCompany();
        $station = $this->station(self::CNPJ);
        $this->postJson('/api/registry/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $company->update(['status' => 'inactive']);
        $this->actAsStation($station);

        $this->getJson('/api/station/companies')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/station/companies/'.$company->uuid.'/accept')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta empresa não está disponível');

        $company->update(['status' => 'active']);
        $company->delete();

        $this->getJson('/api/station/companies')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/station/companies/'.$company->uuid.'/accept')
            ->assertNotFound();
    }

    public function test_uuid_invalido_no_posto_nao_vira_erro_de_servidor(): void
    {
        $station = $this->station(self::CNPJ);
        $this->actAsStation($station);

        $this->postJson('/api/station/companies/nao-e-uuid/accept')
            ->assertNotFound()
            ->assertJsonPath('message', 'Pedido não encontrado');
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

    private function actAsExistingCompany(Company $company): void
    {
        $this->app['auth']->forgetGuards();

        $user = User::query()
            ->whereHas('companies', fn ($query) => $query->whereKey($company->id))
            ->first();

        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'company_id' => $company->id,
            'fuel_station_id' => null,
        ])->save();

        $this->withToken($token->plainTextToken);
    }

    private function actAsStation(FuelStation $station): void
    {
        $this->app['auth']->forgetGuards();

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

        $this->withToken($token->plainTextToken);
    }

    private function station(string $cnpj, string $corporateName = 'POSTO EXEMPLO LTDA', string $status = 'active'): FuelStation
    {
        return FuelStation::query()->create([
            'cnpj' => $cnpj,
            'corporate_name' => $corporateName,
            'trade_name' => 'Posto Exemplo',
            'status' => $status,
        ]);
    }
}
