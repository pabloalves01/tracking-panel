<?php

namespace App\Services\Fleet;

use App\Enums\Fleet\VehicleKind;
use App\Models\Fleet\VehicleBrand;
use App\Models\Fleet\VehicleModel;
use App\Support\CurrentOrganization;
use App\Support\Text;
use Illuminate\Database\Eloquent\Collection;

/**
 * Marcas e modelos: as globais da LogIV mais as que a empresa acrescentou.
 *
 * Acrescentar não duplica: "scania" acha a "Scania" que já existe, global
 * ou da empresa, e devolve ela.
 */
class VehicleCatalogService
{
    /** @return Collection<int, VehicleBrand> */
    public function brands(VehicleKind $kind): Collection
    {
        return VehicleBrand::query()->visible()->where('kind', $kind)->orderBy('name')->get();
    }

    /** @return Collection<int, VehicleModel> */
    public function models(VehicleBrand $brand): Collection
    {
        return $brand->models()->visible()->orderBy('name')->get();
    }

    public function findBrand(int $id): VehicleBrand
    {
        return VehicleBrand::query()->visible()->findOrFail($id);
    }

    public function addBrand(VehicleKind $kind, string $name): VehicleBrand
    {
        $name = Text::plain($name);

        return VehicleBrand::query()->visible()->where('kind', $kind)->whereLike('name', $name)->first()
            ?? VehicleBrand::query()->create([
                'company_id' => app(CurrentOrganization::class)->id(),
                'kind' => $kind,
                'name' => $name,
            ]);
    }

    public function addModel(VehicleBrand $brand, string $name): VehicleModel
    {
        $name = Text::plain($name);

        return $brand->models()->visible()->whereLike('name', $name)->first()
            ?? $brand->models()->create([
                'company_id' => app(CurrentOrganization::class)->id(),
                'name' => $name,
            ]);
    }
}
