<?php

namespace App\Services\Fleet;

use App\Models\Fleet\Vehicle;
use App\Models\Fleet\VehicleCoupling;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Engate e desengate de implementos. Desengatar fecha a linha
 * (`uncoupled_at`); nada é apagado.
 */
class VehicleCouplingService
{
    /**
     * Deixa a tração com exatamente estes implementos, nesta ordem — o
     * primeiro na posição 1. O que sair da lista ou mudar de posição é
     * desengatado.
     *
     * Implemento engatado em outro veículo é recusado: trocar de cavalo é
     * desengatar lá e engatar aqui, de propósito.
     *
     * As linhas da tração e dos implementos ficam travadas até o fim da
     * transação: duas telas engatando o mesmo implemento ao mesmo tempo
     * esperam uma pela outra, e a segunda vê o engate da primeira. O
     * índice único parcial segura o que escapar disso. Chamar dentro de uma
     * transação.
     *
     * @param  array<int, string>  $trailerUuids
     */
    public function sync(Vehicle $traction, array $trailerUuids): void
    {
        Vehicle::query()->whereKey($traction->getKey())->lockForUpdate()->first();
        $trailers = Vehicle::query()->whereIn('uuid', $trailerUuids)->lockForUpdate()->get()->keyBy('uuid');
        $desired = collect(array_values($trailerUuids))
            ->mapWithKeys(fn (string $uuid, int $index) => [$index + 1 => $trailers[$uuid]]);

        $kept = [];

        foreach ($traction->trailerCouplings()->get() as $coupling) {
            if ($desired->get($coupling->position)?->getKey() === $coupling->trailer_vehicle_id) {
                $kept[$coupling->position] = true;
            } else {
                $coupling->uncouple();
            }
        }

        foreach ($desired as $position => $trailer) {
            if (isset($kept[$position])) {
                continue;
            }

            if ($trailer->is($traction)) {
                throw new DomainException('Um veículo não engata nele mesmo');
            }

            $elsewhere = $trailer->tractionCoupling()->with('traction')->first();

            if ($elsewhere !== null) {
                throw new DomainException(sprintf(
                    'O implemento %s está engatado em %s. Desengate lá antes.',
                    $trailer->plate,
                    $elsewhere->traction->plate
                ));
            }

            VehicleCoupling::query()->create([
                'traction_vehicle_id' => $traction->getKey(),
                'trailer_vehicle_id' => $trailer->getKey(),
                'position' => $position,
                'coupled_at' => now(),
            ]);
        }
    }

    /**
     * Desfaz os engates do veículo, puxando e sendo puxado — ao excluí-lo,
     * ou quando o tipo dele muda de grupo.
     */
    public function releaseAll(Vehicle $vehicle): void
    {
        VehicleCoupling::query()
            ->whereNull('uncoupled_at')
            ->where(fn (Builder $query) => $query
                ->where('traction_vehicle_id', $vehicle->getKey())
                ->orWhere('trailer_vehicle_id', $vehicle->getKey()))
            ->update(['uncoupled_at' => now()]);
    }
}
