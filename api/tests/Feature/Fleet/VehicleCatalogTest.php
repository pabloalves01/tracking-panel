<?php

namespace Tests\Feature\Fleet;

use App\Models\Fleet\VehicleBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * Marcas e modelos: a lista da LogIV mais o que cada empresa acrescenta,
 * sem duplicar e sem uma empresa ver o que a outra acrescentou.
 */
class VehicleCatalogTest extends TestCase
{
    use ActsAsMember;
    use BuildsFleet;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsMemberOf();
    }

    public function test_marcas_separadas_por_grupo(): void
    {
        $motor = collect($this->getJson('/api/fleet/vehicle-brands?kind=motor_vehicle')->assertOk()->json('data'))->pluck('name');
        $trailer = collect($this->getJson('/api/fleet/vehicle-brands?kind=trailer')->assertOk()->json('data'))->pluck('name');

        $this->assertTrue($motor->contains('Scania'));
        $this->assertFalse($motor->contains('Randon'));
        $this->assertTrue($trailer->contains('Randon'));
    }

    public function test_os_modelos_sao_da_marca(): void
    {
        $this->getJson("/api/fleet/vehicle-brands/{$this->brandId('Scania')}/models")
            ->assertOk()
            ->assertJsonFragment(['name' => 'R 450', 'custom' => false])
            ->assertJsonMissing(['name' => 'FH 540']);
    }

    public function test_acrescentar_nao_duplica(): void
    {
        $this->postJson('/api/fleet/vehicle-brands', ['kind' => 'motor_vehicle', 'name' => ' scania '])
            ->assertOk()
            ->assertJsonPath('data.id', $this->brandId('Scania'))
            ->assertJsonPath('data.custom', false);

        $id = $this->postJson('/api/fleet/vehicle-brands', ['kind' => 'trailer', 'name' => 'Implementos Sul'])
            ->assertCreated()
            ->assertJsonPath('data.custom', true)
            ->json('data.id');

        $this->postJson('/api/fleet/vehicle-brands', ['kind' => 'trailer', 'name' => 'IMPLEMENTOS SUL'])
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        $model = $this->postJson("/api/fleet/vehicle-brands/{$id}/models", ['name' => 'Graneleiro 3 eixos'])->assertCreated()->json('data.id');
        $this->postJson("/api/fleet/vehicle-brands/{$id}/models", ['name' => 'graneleiro 3 EIXOS'])->assertOk()->assertJsonPath('data.id', $model);
    }

    public function test_o_que_uma_empresa_acrescenta_a_outra_nao_ve(): void
    {
        $id = $this->postJson('/api/fleet/vehicle-brands', ['kind' => 'trailer', 'name' => 'Implementos Sul'])->json('data.id');

        $this->actingAsMemberOf($this->otherCompany());

        $this->assertFalse(collect($this->getJson('/api/fleet/vehicle-brands?kind=trailer')->json('data'))->contains('id', $id));
        $this->getJson("/api/fleet/vehicle-brands/{$id}/models")->assertNotFound();

        // Acrescentar o mesmo nome cria a da outra empresa, sem achar a da primeira.
        $this->postJson('/api/fleet/vehicle-brands', ['kind' => 'trailer', 'name' => 'Implementos Sul'])->assertCreated();
        $this->assertSame(2, VehicleBrand::query()->where('name', 'Implementos Sul')->count());

        $this->postJson('/api/fleet/vehicles', $this->trailer('CAR1A11', ['vehicle_brand_id' => $id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['vehicle_brand_id' => 'Selecione uma marca da lista']);
    }
}
