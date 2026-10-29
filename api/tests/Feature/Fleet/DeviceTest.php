<?php

namespace Tests\Feature\Fleet;

use App\Models\Fleet\Device;
use App\Models\Fleet\DeviceInstallation;
use App\Models\Fleet\DeviceModel;
use App\Models\Fleet\Vehicle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * O cadastro de rastreadores: existe sem veículo, o IMEI é único no
 * sistema, e um rastreador fica em um veículo por vez.
 */
class DeviceTest extends TestCase
{
    use ActsAsMember;
    use BuildsFleet;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsMemberOf();
    }

    public function test_o_catalogo_traz_os_modelos(): void
    {
        $this->getJson('/api/fleet/device-models')
            ->assertOk()
            ->assertJsonCount(8, 'data')
            ->assertJsonPath('data.0.label', 'Allcom N4');
    }

    public function test_cadastra_disponivel_com_os_numeros_so_em_digitos(): void
    {
        $model = DeviceModel::query()->where('name', 'ST310U')->value('id');

        $this->postJson('/api/fleet/devices', [
            'device_model_id' => $model,
            'imei' => '35-889905-123456-7',
            'sim_number' => '(11) 98888-7777',
            'sim_iccid' => '8955 0312 3456 7890 123',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Rastreador cadastrado com sucesso')
            ->assertJsonPath('data.imei', '358899051234567')
            ->assertJsonPath('data.sim_number', '11988887777')
            ->assertJsonPath('data.sim_iccid', '8955031234567890123')
            ->assertJsonPath('data.model.label', 'Suntech ST310U')
            ->assertJsonPath('data.vehicle', null)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_valida_os_campos(): void
    {
        $this->postJson('/api/fleet/devices', ['device_model_id' => 999, 'imei' => '123', 'sim_number' => '9999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'device_model_id' => 'Selecione um modelo da lista',
                'imei' => 'O IMEI deve ter de 8 a 20 dígitos',
                'sim_number' => 'Informe o número do chip com DDD',
            ]);
    }

    public function test_imei_repetido_na_empresa_e_recusado(): void
    {
        $this->createDevice();

        $this->postJson('/api/fleet/devices', [
            'device_model_id' => DeviceModel::query()->value('id'),
            'imei' => '358899051234567',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe um rastreador com o IMEI 358899051234567');
    }

    public function test_imei_de_outra_empresa_e_recusado_sem_dizer_de_quem(): void
    {
        $this->createDevice();

        $this->actingAsMemberOf($this->otherCompany());

        $this->postJson('/api/fleet/devices', [
            'device_model_id' => DeviceModel::query()->value('id'),
            'imei' => '358899051234567',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este IMEI ou identificador já está cadastrado no LogIV por outra empresa');
    }

    public function test_editar_para_o_imei_de_outro_e_recusado(): void
    {
        $this->createDevice('358899051234567');
        $uuid = $this->createDevice('358899059999999');

        $this->putJson("/api/fleet/devices/{$uuid}", [
            'device_model_id' => DeviceModel::query()->value('id'),
            'imei' => '358899051234567',
        ])->assertStatus(409);
    }

    public function test_identificador_nao_colide_com_o_imei_de_outro(): void
    {
        $this->createDevice('358899051234567', ['tracking_id' => '907012345']);

        $this->postJson('/api/fleet/devices', [
            'device_model_id' => DeviceModel::query()->value('id'),
            'imei' => '907012345',
        ])->assertStatus(409);

        $this->postJson('/api/fleet/devices', [
            'device_model_id' => DeviceModel::query()->value('id'),
            'imei' => '358899059999999',
            'tracking_id' => '358899051234567',
        ])->assertStatus(409);

        $this->getJson('/api/fleet/devices?search=907012')->assertJsonCount(1, 'data')->assertJsonPath('data.0.tracking_id', '907012345');
    }

    public function test_imei_excluido_volta_com_o_mesmo_registro(): void
    {
        $uuid = $this->createDevice();
        $this->deleteJson("/api/fleet/devices/{$uuid}")->assertOk();

        $this->assertSame($uuid, $this->createDevice());
    }

    public function test_instala_e_retira_pela_tela_do_rastreador(): void
    {
        $device = $this->createDevice();
        $vehicle = $this->createVehicle($this->traction());

        $this->postJson("/api/fleet/devices/{$device}/install", ['vehicle' => $vehicle])
            ->assertOk()
            ->assertJsonPath('message', 'Rastreador instalado em ABC1D23')
            ->assertJsonPath('data.vehicle.uuid', $vehicle)
            ->assertJsonPath('data.vehicle.plate', 'ABC1D23')
            ->assertJsonPath('data.vehicle.role', 'primary');

        $this->getJson("/api/fleet/vehicles/{$vehicle}")->assertJsonPath('data.devices.0.uuid', $device);

        $this->postJson("/api/fleet/devices/{$device}/uninstall")
            ->assertOk()
            ->assertJsonPath('data.vehicle', null);

        $this->postJson("/api/fleet/devices/{$device}/install", ['vehicle' => $vehicle])->assertOk();

        // Reinstalar abre outro período; o anterior continua fechado.
        $this->getJson("/api/fleet/devices/{$device}/installations")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.removed_at', null)
            ->assertJsonPath('data.0.vehicle.plate', 'ABC1D23');
        $this->assertSame(1, DeviceInstallation::query()->whereNotNull('removed_at')->count());
    }

    public function test_o_segundo_rastreador_entra_como_reserva(): void
    {
        $vehicle = $this->createVehicle($this->traction());
        $first = $this->createDevice('358899051234567');
        $second = $this->createDevice('358899059999999');

        $this->postJson("/api/fleet/devices/{$first}/install", ['vehicle' => $vehicle])->assertJsonPath('data.vehicle.role', 'primary');
        $this->postJson("/api/fleet/devices/{$second}/install", ['vehicle' => $vehicle])->assertJsonPath('data.vehicle.role', 'backup');

        $this->postJson("/api/fleet/devices/{$second}/install", ['vehicle' => $vehicle, 'role' => 'primary'])
            ->assertStatus(409)
            ->assertJsonPath('message', 'ABC1D23 já tem o rastreador principal 358899051234567. Instale este como reserva ou retire o principal.');
    }

    public function test_transfere_de_um_veiculo_para_outro(): void
    {
        $owner = $this->owner();
        $device = $this->createDevice();
        $from = $this->createVehicle($this->traction(['owner' => $owner, 'devices' => [$this->primary($device)]]));
        $to = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));

        $this->postJson("/api/fleet/devices/{$device}/install", ['vehicle' => $to, 'transfer' => true])
            ->assertOk()
            ->assertJsonPath('data.vehicle.plate', 'CAR1A11');

        $this->getJson("/api/fleet/vehicles/{$from}")->assertJsonPath('data.devices', []);
        $this->getJson("/api/fleet/devices/{$device}/installations")
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.vehicle.plate', 'CAR1A11')
            ->assertJsonPath('data.1.vehicle.plate', 'ABC1D23');
    }

    public function test_o_banco_barra_duas_instalacoes_abertas_do_mesmo_rastreador(): void
    {
        $owner = $this->owner();
        $device = Device::query()->where('uuid', $this->createDevice())->value('id');
        $first = $this->createVehicle($this->traction(['owner' => $owner]));
        $second = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));

        $row = fn (string $vehicle, string $role) => [
            'company_id' => $this->organization->getKey(),
            'device_id' => $device,
            'vehicle_id' => Vehicle::query()->where('uuid', $vehicle)->value('id'),
            'role' => $role,
            'installed_at' => now(),
        ];

        DB::table('device_installations')->insert($row($first, 'primary'));

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('device_installations')->insert($row($second, 'backup'));
    }

    public function test_o_banco_barra_dois_principais_no_mesmo_veiculo(): void
    {
        $vehicle = Vehicle::query()->where('uuid', $this->createVehicle($this->traction()))->value('id');
        $first = Device::query()->where('uuid', $this->createDevice('358899051234567'))->value('id');
        $second = Device::query()->where('uuid', $this->createDevice('358899059999999'))->value('id');

        $row = fn (int $device) => [
            'company_id' => $this->organization->getKey(),
            'device_id' => $device,
            'vehicle_id' => $vehicle,
            'role' => 'primary',
            'installed_at' => now(),
        ];

        DB::table('device_installations')->insert($row($first));

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('device_installations')->insert($row($second));
    }

    public function test_um_veiculo_por_vez(): void
    {
        $owner = $this->owner();
        $device = $this->createDevice();
        $first = $this->createVehicle($this->traction(['owner' => $owner]));
        $second = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));

        $this->postJson("/api/fleet/devices/{$device}/install", ['vehicle' => $first])->assertOk();
        $this->postJson("/api/fleet/devices/{$device}/install", ['vehicle' => $first])->assertOk();

        $this->postJson("/api/fleet/devices/{$device}/install", ['vehicle' => $second])
            ->assertStatus(409)
            ->assertJsonPath('message', 'O rastreador 358899051234567 está instalado em ABC1D23. Retire-o de lá antes.');

        $this->assertSame(1, DeviceInstallation::query()->count());
    }

    public function test_rastreador_inativo_nao_e_instalado_e_instalado_nao_e_desativado(): void
    {
        $device = $this->createDevice();
        $vehicle = $this->createVehicle($this->traction());

        $this->postJson("/api/fleet/devices/{$device}/deactivate")->assertOk();
        $this->postJson("/api/fleet/devices/{$device}/install", ['vehicle' => $vehicle])
            ->assertStatus(409)
            ->assertJsonPath('message', 'O rastreador 358899051234567 está inativo');

        $this->postJson("/api/fleet/devices/{$device}/activate")->assertOk();
        $this->postJson("/api/fleet/devices/{$device}/install", ['vehicle' => $vehicle])->assertOk();

        $this->postJson("/api/fleet/devices/{$device}/deactivate")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Retire o rastreador de ABC1D23 antes de desativar');
    }

    public function test_excluir_instalado_retira_do_veiculo(): void
    {
        $device = $this->createDevice();
        $vehicle = $this->createVehicle($this->traction(['devices' => [$this->primary($device)]]));

        $this->deleteJson("/api/fleet/devices/{$device}")->assertOk();

        $this->getJson("/api/fleet/vehicles/{$vehicle}")->assertJsonPath('data.devices', []);
        $this->assertSoftDeleted(Device::query()->withTrashed()->sole());
    }

    public function test_filtra_instalados_e_disponiveis_e_busca_pela_placa(): void
    {
        $installed = $this->createDevice('358899051234567');
        $this->createDevice('358899059999999');
        $this->createVehicle($this->traction(['devices' => [$this->primary($installed)]]));

        $this->getJson('/api/fleet/devices?installed=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $installed);
        $this->getJson('/api/fleet/devices?installed=0')->assertJsonCount(1, 'data')->assertJsonPath('data.0.imei', '358899059999999');
        $this->getJson('/api/fleet/devices?search=abc1d')->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $installed);
        $this->getJson('/api/fleet/devices?search=9999')->assertJsonCount(1, 'data');
    }

    public function test_rastreador_de_outra_empresa_nao_aparece(): void
    {
        $uuid = $this->createDevice();

        $this->actingAsMemberOf($this->otherCompany());

        $this->getJson("/api/fleet/devices/{$uuid}")->assertNotFound();
        $this->getJson("/api/fleet/devices/{$uuid}/installations")->assertNotFound();
        $this->postJson("/api/fleet/devices/{$uuid}/uninstall")->assertNotFound();
    }
}
