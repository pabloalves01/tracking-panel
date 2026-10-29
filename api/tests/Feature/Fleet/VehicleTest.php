<?php

namespace Tests\Feature\Fleet;

use App\Models\Fleet\DeviceInstallation;
use App\Models\Fleet\Vehicle;
use App\Models\Fleet\VehicleCoupling;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * O cadastro de veículos: automotor e implemento na mesma tabela, o grupo
 * vindo do tipo, engates com histórico e rastreadores instalados pelo
 * formulário do veículo.
 */
class VehicleTest extends TestCase
{
    use ActsAsMember;
    use BuildsFleet;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsMemberOf();
    }

    // ============================== CADASTRO ==============================

    /** @return array<string, array{string, string, ?string}> */
    public static function types(): array
    {
        return [
            'cavalo mecânico' => ['truck_tractor', 'motor_vehicle', null],
            'caminhão truck' => ['truck', 'motor_vehicle', 'closed'],
            'reboque' => ['trailer', 'trailer', 'open'],
            'semirreboque' => ['semi_trailer', 'trailer', 'bulk'],
            'automóvel' => ['car', 'motor_vehicle', null],
        ];
    }

    #[DataProvider('types')]
    public function test_o_grupo_sai_do_tipo(string $type, string $kind, ?string $body): void
    {
        $this->postJson('/api/fleet/vehicles', $this->traction([
            'vehicle_type_id' => $this->typeId($type),
            'body_type_id' => $body ? $this->bodyId($body) : null,
            'kind' => $kind === 'trailer' ? 'motor_vehicle' : 'trailer',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.kind', $kind)
            ->assertJsonPath('data.type.slug', $type)
            ->assertJsonPath('data.body_type.slug', $body);
    }

    public function test_cadastra_com_proprietario_transportador_e_responsavel(): void
    {
        $owner = $this->owner('Transportes Silva');
        $carrier = $this->carrier('Lima Logística', ['rntrc' => '12345678', 'antt_category' => 'etc']);
        $responsible = $this->responsible('Maria Souza');

        $this->postJson('/api/fleet/vehicles', $this->traction([
            'owner' => $owner,
            'carrier' => $carrier,
            'responsible' => $responsible,
            'plate' => 'abc-1d23',
            'chassis' => '9bsr6x40003901234',
            'gross_weight_kg' => 16500,
        ]))
            ->assertCreated()
            ->assertJsonPath('message', 'Veículo cadastrado com sucesso')
            ->assertJsonPath('data.plate', 'ABC1D23')
            ->assertJsonPath('data.plate_state', 'SP')
            ->assertJsonPath('data.chassis', '9BSR6X40003901234')
            ->assertJsonPath('data.type.category', 'heavy_commercial')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.owner.uuid', $owner)
            ->assertJsonPath('data.owner.name', 'Transportes Silva')
            ->assertJsonPath('data.carrier.name', 'Lima Logística')
            ->assertJsonPath('data.responsible.name', 'Maria Souza')
            ->assertJsonPath('data.gross_weight_kg', 16500)
            ->assertJsonPath('data.max_traction_weight_kg', 74000)
            ->assertJsonPath('data.trailers', [])
            ->assertJsonPath('data.devices', []);
    }

    public function test_renavam_antigo_de_nove_digitos_ganha_zeros(): void
    {
        $renavam = $this->renavam(1);

        $this->postJson('/api/fleet/vehicles', $this->traction(['renavam' => ltrim($renavam, '0')]))
            ->assertCreated()
            ->assertJsonPath('data.renavam', $renavam);
    }

    public function test_placa_repetida_na_empresa_e_recusada(): void
    {
        $owner = $this->owner();
        $this->createVehicle($this->traction(['owner' => $owner]));

        $this->postJson('/api/fleet/vehicles', $this->traction(['owner' => $owner, 'plate' => 'ABC-1D23']))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe um veículo com a placa ABC1D23');
    }

    public function test_a_mesma_placa_em_outra_empresa_e_outro_veiculo(): void
    {
        $this->createVehicle($this->traction());

        $this->actingAsMemberOf($this->otherCompany());
        $this->createVehicle($this->traction());

        $this->assertSame(2, Vehicle::query()->withoutGlobalScope('company')->count());
    }

    public function test_placa_de_veiculo_excluido_volta_com_o_mesmo_registro(): void
    {
        $owner = $this->owner();
        $uuid = $this->createVehicle($this->traction(['owner' => $owner]));
        $this->deleteJson("/api/fleet/vehicles/{$uuid}")->assertOk();

        $this->postJson('/api/fleet/vehicles', $this->traction(['owner' => $owner, 'brand' => 'Volvo']))
            ->assertCreated()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.brand', 'Volvo')
            ->assertJsonPath('data.status', 'active');
    }

    public function test_editar_para_a_placa_de_outro_veiculo_e_recusado(): void
    {
        $owner = $this->owner();
        $this->createVehicle($this->traction(['owner' => $owner]));
        $uuid = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));

        $this->putJson("/api/fleet/vehicles/{$uuid}", $this->trailer('ABC1D23', ['owner' => $owner]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe um veículo com a placa ABC1D23');
    }

    public function test_valida_documentos_anos_e_pesos(): void
    {
        $this->postJson('/api/fleet/vehicles', $this->traction([
            'vehicle_type_id' => 999,
            'plate' => 'AB12345',
            'renavam' => '12345678901',
            'chassis' => '9BSR6X4000390123O',
            'model_year' => 2020,
            'gross_weight_kg' => 5000,
            'responsible' => null,
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'responsible' => 'Selecione o responsável',
                'vehicle_type_id' => 'Selecione um tipo da lista',
                'plate' => 'Informe uma placa válida, como ABC1D23 ou ABC1234',
                'renavam' => 'Informe um RENAVAM válido',
                'chassis' => 'O chassi tem 17 letras e números, sem I, O e Q',
                'model_year' => 'O ano do modelo não pode ser anterior ao de fabricação',
                'gross_weight_kg' => 'O PBT não pode ser menor que a tara',
            ]);
    }

    public function test_cmt_so_vale_para_automotor(): void
    {
        $this->postJson('/api/fleet/vehicles', $this->trailer('CAR1A11', ['max_traction_weight_kg' => 50000]))
            ->assertCreated()
            ->assertJsonPath('data.kind', 'trailer')
            ->assertJsonPath('data.max_traction_weight_kg', null)
            ->assertJsonPath('data.load_capacity_kg', 32000);
    }

    public function test_proprietario_e_transportador_de_outra_empresa_nao_servem(): void
    {
        $owner = $this->owner();
        $carrier = $this->carrier();

        $this->actingAsMemberOf($this->otherCompany());

        $this->postJson('/api/fleet/vehicles', $this->traction(['owner' => $owner, 'carrier' => $carrier]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'owner' => 'Selecione um proprietário cadastrado',
                'carrier' => 'Selecione um transportador cadastrado',
            ]);
    }

    public function test_veiculo_de_outra_empresa_nao_aparece(): void
    {
        $uuid = $this->createVehicle($this->traction());

        $this->actingAsMemberOf($this->otherCompany());

        $this->getJson("/api/fleet/vehicles/{$uuid}")->assertNotFound();
        $this->getJson("/api/fleet/vehicles/{$uuid}/couplings")->assertNotFound();
        $this->getJson('/api/fleet/vehicles')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_busca_por_placa_e_por_proprietario_e_filtra_o_grupo(): void
    {
        $owner = $this->owner('Transportes Silva');
        $this->createVehicle($this->traction(['owner' => $owner]));
        $this->createVehicle($this->trailer('CAR1A11', ['owner' => $this->owner('Outro Dono')]));

        $this->getJson('/api/fleet/vehicles?search=abc-1d')->assertJsonCount(1, 'data')->assertJsonPath('data.0.plate', 'ABC1D23');
        $this->getJson('/api/fleet/vehicles?search=silva')->assertJsonCount(1, 'data')->assertJsonPath('data.0.plate', 'ABC1D23');
        $this->getJson('/api/fleet/vehicles?kind=trailer')->assertJsonCount(1, 'data')->assertJsonPath('data.0.plate', 'CAR1A11');
        $this->getJson('/api/fleet/vehicles?kind=motor_vehicle')->assertJsonCount(1, 'data')->assertJsonPath('data.0.plate', 'ABC1D23');
    }

    public function test_desativa_e_ativa(): void
    {
        $uuid = $this->createVehicle($this->traction());

        $this->postJson("/api/fleet/vehicles/{$uuid}/deactivate")->assertOk()->assertJsonPath('message', 'Veículo desativado com sucesso');
        $this->getJson("/api/fleet/vehicles/{$uuid}")->assertJsonPath('data.status', 'inactive');

        $this->postJson("/api/fleet/vehicles/{$uuid}/activate")->assertOk();
        $this->getJson("/api/fleet/vehicles/{$uuid}")->assertJsonPath('data.status', 'active');
    }

    public function test_os_catalogos_trazem_tipos_e_carrocerias(): void
    {
        $this->getJson('/api/fleet/vehicle-types')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'truck_tractor')
            ->assertJsonPath('data.0.kind', 'motor_vehicle');

        $this->getJson('/api/fleet/body-types')->assertOk()->assertJsonFragment(['slug' => 'tank']);
    }

    // =============================== ENGATES ===============================

    public function test_engata_os_implementos_na_ordem(): void
    {
        $owner = $this->owner();
        $first = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));
        $second = $this->createVehicle($this->trailer('CAR2B22', ['owner' => $owner]));

        $traction = $this->postJson('/api/fleet/vehicles', $this->traction(['owner' => $owner, 'trailers' => [$first, $second]]))
            ->assertCreated()
            ->assertJsonPath('data.trailers.0.position', 1)
            ->assertJsonPath('data.trailers.0.plate', 'CAR1A11')
            ->assertJsonPath('data.trailers.1.position', 2)
            ->assertJsonPath('data.trailers.1.plate', 'CAR2B22')
            ->json('data.uuid');

        $this->getJson("/api/fleet/vehicles/{$first}")
            ->assertJsonPath('data.traction.uuid', $traction)
            ->assertJsonPath('data.traction.plate', 'ABC1D23')
            ->assertJsonPath('data.traction.position', 1);
    }

    public function test_caminhao_tambem_puxa_reboque(): void
    {
        $owner = $this->owner();
        $trailer = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner, 'vehicle_type_id' => $this->typeId('trailer')]));

        $this->postJson('/api/fleet/vehicles', $this->traction([
            'owner' => $owner,
            'vehicle_type_id' => $this->typeId('truck'),
            'body_type_id' => $this->bodyId('closed'),
            'trailers' => [$trailer],
        ]))->assertCreated()->assertJsonPath('data.trailers.0.plate', 'CAR1A11');
    }

    public function test_tirar_ou_trocar_de_posicao_desengata_e_guarda_o_historico(): void
    {
        $owner = $this->owner();
        $first = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));
        $second = $this->createVehicle($this->trailer('CAR2B22', ['owner' => $owner]));
        $traction = $this->createVehicle($this->traction(['owner' => $owner, 'trailers' => [$first, $second]]));

        $this->putJson("/api/fleet/vehicles/{$traction}", $this->traction(['owner' => $owner, 'trailers' => [$second]]))
            ->assertOk()
            ->assertJsonCount(1, 'data.trailers')
            ->assertJsonPath('data.trailers.0.position', 1)
            ->assertJsonPath('data.trailers.0.plate', 'CAR2B22');

        $this->getJson("/api/fleet/vehicles/{$first}")->assertJsonPath('data.traction', null);

        // CAR2B22 na posição 1 (aberto), CAR2B22 na 2 e CAR1A11 na 1 (fechados).
        $history = $this->getJson("/api/fleet/vehicles/{$traction}/couplings")->assertOk()->json('data');
        $this->assertCount(3, $history);
        $this->assertSame(['CAR2B22', 1, null], [$history[0]['trailer']['plate'], $history[0]['position'], $history[0]['uncoupled_at']]);
        $this->assertNotNull($history[1]['uncoupled_at']);
        $this->assertNotNull($history[2]['uncoupled_at']);

        $this->getJson("/api/fleet/vehicles/{$first}/couplings")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.traction.plate', 'ABC1D23');
    }

    public function test_manter_a_mesma_lista_nao_mexe_nos_engates(): void
    {
        $owner = $this->owner();
        $trailer = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));
        $traction = $this->createVehicle($this->traction(['owner' => $owner, 'trailers' => [$trailer]]));

        $this->putJson("/api/fleet/vehicles/{$traction}", $this->traction(['owner' => $owner, 'trailers' => [$trailer]]))->assertOk();

        $this->assertSame(1, VehicleCoupling::query()->count());
    }

    public function test_implemento_engatado_em_outro_veiculo_e_recusado(): void
    {
        $owner = $this->owner();
        $trailer = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));
        $this->createVehicle($this->traction(['owner' => $owner, 'trailers' => [$trailer]]));

        $this->postJson('/api/fleet/vehicles', $this->traction([
            'owner' => $owner,
            'plate' => 'XYZ9876',
            'renavam' => $this->renavam(2),
            'trailers' => [$trailer],
        ]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'O implemento CAR1A11 está engatado em ABC1D23. Desengate lá antes.');

        $this->assertFalse(Vehicle::query()->where('plate', 'XYZ9876')->exists());
    }

    public function test_engate_simultaneo_do_mesmo_implemento_vira_409(): void
    {
        $owner = $this->owner();
        $trailer = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));
        $other = $this->createVehicle($this->traction(['owner' => $owner, 'plate' => 'XYZ9876', 'renavam' => $this->renavam(2)]));

        // Outra tela engata o implemento entre a conferência e a gravação
        // desta: o índice único parcial barra o segundo engate aberto.
        VehicleCoupling::creating(function (VehicleCoupling $coupling) use ($other) {
            if ($coupling->traction_vehicle_id !== Vehicle::query()->where('uuid', $other)->value('id')) {
                DB::table('vehicle_couplings')->insert([
                    'company_id' => $this->organization->getKey(),
                    'traction_vehicle_id' => Vehicle::query()->where('uuid', $other)->value('id'),
                    'trailer_vehicle_id' => $coupling->trailer_vehicle_id,
                    'position' => 1,
                    'coupled_at' => now(),
                ]);
            }
        });

        $this->postJson('/api/fleet/vehicles', $this->traction(['owner' => $owner, 'trailers' => [$trailer]]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este veículo acabou de ser cadastrado em outra tela. Atualize e tente de novo.');

        // A gravação inteira volta: nem o veículo novo fica pela metade.
        $this->assertFalse(Vehicle::query()->where('plate', 'ABC1D23')->exists());
    }

    public function test_o_banco_barra_dois_engates_abertos_do_mesmo_implemento(): void
    {
        $owner = $this->owner();
        $trailer = Vehicle::query()->where('uuid', $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner])))->value('id');
        $first = Vehicle::query()->where('uuid', $this->createVehicle($this->traction(['owner' => $owner])))->value('id');
        $second = Vehicle::query()->where('uuid', $this->createVehicle($this->traction(['owner' => $owner, 'plate' => 'XYZ9876', 'renavam' => $this->renavam(2)])))->value('id');

        $row = fn (int $traction) => [
            'company_id' => $this->organization->getKey(),
            'traction_vehicle_id' => $traction,
            'trailer_vehicle_id' => $trailer,
            'position' => 1,
            'coupled_at' => now(),
        ];

        DB::table('vehicle_couplings')->insert($row($first));

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('vehicle_couplings')->insert($row($second));
    }

    public function test_automotor_nao_e_engatado_como_implemento_nem_em_si_mesmo(): void
    {
        $owner = $this->owner();
        $other = $this->createVehicle($this->traction(['owner' => $owner, 'plate' => 'XYZ9876', 'renavam' => $this->renavam(2)]));

        $this->postJson('/api/fleet/vehicles', $this->traction(['owner' => $owner, 'trailers' => [$other]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['trailers.0' => 'XYZ9876 não é implemento']);

        $this->putJson("/api/fleet/vehicles/{$other}", $this->traction([
            'owner' => $owner,
            'plate' => 'XYZ9876',
            'renavam' => $this->renavam(2),
            'trailers' => [$other],
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['trailers.0' => 'Um veículo não engata nele mesmo']);
    }

    public function test_implemento_nao_puxa_outro(): void
    {
        $owner = $this->owner();
        $first = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));

        $this->postJson('/api/fleet/vehicles', $this->trailer('CAR2B22', ['owner' => $owner, 'trailers' => [$first]]))
            ->assertCreated()
            ->assertJsonPath('data.trailers', []);

        $this->assertSame(0, VehicleCoupling::query()->count());
    }

    public function test_trocar_o_tipo_para_o_outro_grupo_desfaz_os_engates(): void
    {
        $owner = $this->owner();
        $trailer = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));
        $traction = $this->createVehicle($this->traction(['owner' => $owner, 'trailers' => [$trailer]]));

        $this->putJson("/api/fleet/vehicles/{$traction}", $this->traction([
            'owner' => $owner,
            'vehicle_type_id' => $this->typeId('semi_trailer'),
            'trailers' => [$trailer],
        ]))->assertOk()->assertJsonPath('data.kind', 'trailer')->assertJsonPath('data.trailers', []);

        $this->getJson("/api/fleet/vehicles/{$trailer}")->assertJsonPath('data.traction', null);
    }

    public function test_trocar_o_tipo_no_mesmo_grupo_mantem_os_engates(): void
    {
        $owner = $this->owner();
        $trailer = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));
        $traction = $this->createVehicle($this->traction(['owner' => $owner, 'trailers' => [$trailer]]));

        $this->putJson("/api/fleet/vehicles/{$traction}", $this->traction([
            'owner' => $owner,
            'vehicle_type_id' => $this->typeId('truck'),
        ]))->assertOk()->assertJsonPath('data.trailers.0.plate', 'CAR1A11');

        $this->assertSame(1, VehicleCoupling::query()->count());
    }

    public function test_excluir_o_automotor_desengata_e_libera_os_rastreadores(): void
    {
        $owner = $this->owner();
        $trailer = $this->createVehicle($this->trailer('CAR1A11', ['owner' => $owner]));
        $device = $this->createDevice();
        $traction = $this->createVehicle($this->traction([
            'owner' => $owner,
            'trailers' => [$trailer],
            'devices' => [$this->primary($device)],
        ]));

        $this->deleteJson("/api/fleet/vehicles/{$traction}")->assertOk()->assertJsonPath('message', 'Veículo excluído com sucesso');

        $this->getJson("/api/fleet/vehicles/{$trailer}")->assertJsonPath('data.traction', null);
        $this->getJson("/api/fleet/devices/{$device}")->assertJsonPath('data.vehicle', null);
        $this->assertSame(1, VehicleCoupling::query()->whereNotNull('uncoupled_at')->count());
        $this->assertSame(1, DeviceInstallation::query()->whereNotNull('removed_at')->count());
    }

    // ============================= RASTREADORES =============================

    public function test_instala_principal_e_reserva_e_retira_pelo_veiculo(): void
    {
        $owner = $this->owner();
        $main = $this->createDevice('358899051234567');
        $spare = $this->createDevice('358899059999999');

        $vehicle = $this->postJson('/api/fleet/vehicles', $this->traction([
            'owner' => $owner,
            'devices' => [$this->primary($main), $this->backup($spare)],
        ]))
            ->assertCreated()
            ->assertJsonCount(2, 'data.devices')
            ->assertJsonPath('data.devices.0.uuid', $main)
            ->assertJsonPath('data.devices.0.role', 'primary')
            ->assertJsonPath('data.devices.0.model', 'Allcom N4')
            ->assertJsonPath('data.devices.1.role', 'backup')
            ->json('data.uuid');

        $this->getJson("/api/fleet/devices/{$main}")->assertJsonPath('data.vehicle.plate', 'ABC1D23');

        $this->putJson("/api/fleet/vehicles/{$vehicle}", $this->traction(['owner' => $owner, 'devices' => [$this->primary($spare)]]))
            ->assertOk()
            ->assertJsonCount(1, 'data.devices')
            ->assertJsonPath('data.devices.0.uuid', $spare)
            ->assertJsonPath('data.devices.0.role', 'primary');

        $this->getJson("/api/fleet/devices/{$main}")->assertJsonPath('data.vehicle', null);
    }

    public function test_trocar_principal_e_reserva_de_lugar(): void
    {
        $owner = $this->owner();
        $main = $this->createDevice('358899051234567');
        $spare = $this->createDevice('358899059999999');
        $vehicle = $this->createVehicle($this->traction(['owner' => $owner, 'devices' => [$this->primary($main), $this->backup($spare)]]));

        $this->putJson("/api/fleet/vehicles/{$vehicle}", $this->traction([
            'owner' => $owner,
            'devices' => [$this->backup($main), $this->primary($spare)],
        ]))->assertOk();

        $this->assertSame(2, DeviceInstallation::query()->count());
        $this->getJson("/api/fleet/devices/{$spare}")->assertJsonPath('data.vehicle.role', 'primary');
        $this->getJson("/api/fleet/devices/{$main}")->assertJsonPath('data.vehicle.role', 'backup');
    }

    public function test_dois_principais_sao_recusados(): void
    {
        $first = $this->createDevice('358899051234567');
        $second = $this->createDevice('358899059999999');

        $this->postJson('/api/fleet/vehicles', $this->traction(['devices' => [$this->primary($first), $this->primary($second)]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['devices' => 'Escolha um rastreador principal só']);
    }

    public function test_sem_a_lista_de_rastreadores_a_edicao_nao_mexe_neles(): void
    {
        $owner = $this->owner();
        $device = $this->createDevice();
        $vehicle = $this->createVehicle($this->traction(['owner' => $owner, 'devices' => [$this->primary($device)]]));

        $this->putJson("/api/fleet/vehicles/{$vehicle}", $this->traction(['owner' => $owner]))
            ->assertOk()
            ->assertJsonCount(1, 'data.devices');
    }

    public function test_rastreador_instalado_em_outro_veiculo_e_recusado(): void
    {
        $owner = $this->owner();
        $device = $this->createDevice();
        $this->createVehicle($this->traction(['owner' => $owner, 'devices' => [$this->primary($device)]]));

        $this->postJson('/api/fleet/vehicles', $this->trailer('CAR1A11', ['owner' => $owner, 'devices' => [$this->primary($device)]]))
            ->assertStatus(409)
            ->assertJsonPath('message', 'O rastreador 358899051234567 está instalado em ABC1D23. Retire-o de lá antes.');
    }
}
