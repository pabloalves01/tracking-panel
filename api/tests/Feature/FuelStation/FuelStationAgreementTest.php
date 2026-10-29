<?php

namespace Tests\Feature\FuelStation;

use App\Models\FuelStationAgreement;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * O convênio entre uma empresa e um posto.
 *
 * A empresa pede, o posto aceita ou recusa, e qualquer um dos dois lados
 * encerra depois. O estado vive em `fuel_station_agreements` e as transições são
 * fechadas: só se aceita o que está pendente, só se encerra o que está
 * ativo. Sem isso, um duplo clique reabriria um convênio encerrado.
 */
class FuelStationAgreementTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    private Organization $company;

    private Organization $fuelStation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsMemberOf();
        $this->company = $this->organization;
        $this->fuelStation = Organization::factory()->fuelStation()->create();
    }

    // ================================ PEDIR ================================

    public function test_empresa_encontra_o_posto_pelo_cnpj_e_pede_convenio(): void
    {
        $this->getJson('/api/registry/fuel-stations/by-document?document='.$this->fuelStation->document)
            ->assertOk()
            ->assertJsonPath('data.document', $this->fuelStation->document);

        $this->postJson('/api/registry/fuel-stations', ['document' => $this->fuelStation->document])
            ->assertCreated()
            ->assertJsonPath('message', 'Pedido de convênio enviado ao posto');

        $this->assertDatabaseHas('fuel_station_agreements', [
            'company_id' => $this->company->getKey(),
            'fuel_station_id' => $this->fuelStation->getKey(),
            'status' => FuelStationAgreement::REQUEST_SENT,
        ]);
    }

    public function test_cnpj_sem_posto_cadastrado_devolve_nulo(): void
    {
        $this->getJson('/api/registry/fuel-stations/by-document?document=11444777000161')
            ->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_nao_se_pede_convenio_a_si_mesmo(): void
    {
        $this->postJson('/api/registry/fuel-stations', ['document' => $this->company->document])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este CNPJ é o da sua própria empresa');
    }

    public function test_pedido_repetido_enquanto_pendente_e_recusado(): void
    {
        $this->request();

        $this->postJson('/api/registry/fuel-stations', ['document' => $this->fuelStation->document])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe um pedido aguardando resposta deste posto');
    }

    public function test_posto_inativo_nao_recebe_pedido(): void
    {
        $this->fuelStation->update(['status' => Organization::INACTIVE]);

        $this->postJson('/api/registry/fuel-stations', ['document' => $this->fuelStation->document])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este posto está inativo no sistema');
    }

    // ============================== RESPONDER ==============================

    public function test_posto_aceita_e_o_convenio_fica_ativo(): void
    {
        $link = $this->request();

        $this->asFuelStation();

        $this->getJson('/api/fuel-station/companies')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->postJson("/api/fuel-station/companies/{$link->uuid}/accept")
            ->assertOk()
            ->assertJsonPath('message', 'Convênio aceito');

        $this->assertSame(FuelStationAgreement::ACTIVE, $link->fresh()->status);
    }

    public function test_posto_recusa_e_a_empresa_pode_pedir_de_novo(): void
    {
        $link = $this->request();

        $this->asFuelStation();
        $this->postJson("/api/fuel-station/companies/{$link->uuid}/reject")->assertOk();

        $this->assertSame(FuelStationAgreement::REJECTED, $link->fresh()->status);

        $this->asCompany();
        $this->postJson('/api/registry/fuel-stations', ['document' => $this->fuelStation->document])
            ->assertCreated();

        $this->assertSame(FuelStationAgreement::REQUEST_SENT, $link->fresh()->status);
        $this->assertSame(1, FuelStationAgreement::query()->count());
    }

    public function test_aceitar_duas_vezes_nao_passa(): void
    {
        $link = $this->request();

        $this->asFuelStation();
        $this->postJson("/api/fuel-station/companies/{$link->uuid}/accept")->assertOk();
        $this->postJson("/api/fuel-station/companies/{$link->uuid}/accept")->assertStatus(409);
    }

    // ============================== ENCERRAR ==============================

    public function test_os_dois_lados_encerram_um_convenio_ativo(): void
    {
        $link = $this->accepted();

        $this->asCompany();
        $this->postJson("/api/registry/fuel-stations/{$link->uuid}/disconnect")
            ->assertOk()
            ->assertJsonPath('message', 'Convênio encerrado');

        $this->assertSame(FuelStationAgreement::DISCONNECTED, $link->fresh()->status);

        // E pelo lado do posto, num convênio novo.
        $outro = $this->accepted();

        $this->asFuelStation();
        $this->postJson("/api/fuel-station/companies/{$outro->uuid}/disconnect")->assertOk();

        $this->assertSame(FuelStationAgreement::DISCONNECTED, $outro->fresh()->status);
    }

    public function test_nao_se_encerra_o_que_ainda_esta_pendente(): void
    {
        $link = $this->request();

        $this->postJson("/api/registry/fuel-stations/{$link->uuid}/disconnect")
            ->assertStatus(409);
    }

    public function test_empresa_cancela_o_proprio_pedido(): void
    {
        $link = $this->request();

        $this->deleteJson("/api/registry/fuel-stations/{$link->uuid}")
            ->assertOk()
            ->assertJsonPath('message', 'Pedido cancelado');
    }

    // ============================== ISOLAMENTO ==============================

    public function test_um_posto_nao_responde_pelo_convenio_de_outro(): void
    {
        $link = $this->request();

        $this->actingAsFuelStationMemberOf();

        $this->getJson('/api/fuel-station/companies')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/fuel-station/companies/{$link->uuid}/accept")->assertNotFound();
    }

    public function test_uuid_inexistente_e_404_nao_500(): void
    {
        $this->postJson('/api/registry/fuel-stations/nao-existe/disconnect')->assertNotFound();

        $this->asFuelStation();
        $this->postJson('/api/fuel-station/companies/nao-existe/accept')->assertNotFound();
    }

    // ================================ HELPERS ================================

    private function request(): FuelStationAgreement
    {
        $this->asCompany();

        $this->postJson('/api/registry/fuel-stations', ['document' => $this->fuelStation->document])
            ->assertCreated();

        return FuelStationAgreement::query()->latest('id')->firstOrFail();
    }

    private function accepted(): FuelStationAgreement
    {
        $link = $this->request();

        $this->asFuelStation();
        $this->postJson("/api/fuel-station/companies/{$link->uuid}/accept")->assertOk();

        return $link->fresh();
    }

    private function asCompany(): void
    {
        $this->actingAsMemberOf($this->company, $this->member);
    }

    private function asFuelStation(): void
    {
        $this->actingAsMemberOf($this->fuelStation);
    }
}
