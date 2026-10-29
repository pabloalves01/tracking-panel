<?php

namespace App\Services\Fleet;

use App\Enums\Fleet\VehicleKind;
use App\Models\Fleet\Vehicle;
use App\Models\Fleet\VehicleCoupling;
use App\Models\Fleet\VehicleType;
use App\Models\Registry\Carrier;
use App\Models\Registry\VehicleOwner;
use App\Models\Registry\VehicleResponsible;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * O cadastro de veículos: os dados dele e, na mesma gravação, os
 * implementos engatados e os rastreadores instalados.
 */
class VehicleService
{
    public function __construct(
        private readonly VehicleCouplingService $couplings,
        private readonly DeviceInstallationService $installations,
    ) {}

    /**
     * @param  array{search?: string|null, kind?: string|null, status?: string|null, per_page?: int|null}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->query()
            ->ofKind($filters['kind'] ?? null)
            ->status($filters['status'] ?? null)
            ->search($filters['search'] ?? null)
            ->orderBy('plate')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function get(string $uuid): Vehicle
    {
        return $this->query()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * Placa de um veículo excluído volta com o mesmo registro, como as
     * entidades: o que a operação gravou para ele continua ligado.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Vehicle
    {
        try {
            $vehicle = DB::transaction(function () use ($data) {
                $vehicle = Vehicle::withTrashed()->where('plate', Vehicle::normalizePlate($data['plate']))->first();

                if ($vehicle !== null && ! $vehicle->trashed()) {
                    throw new DomainException("Já existe um veículo com a placa {$vehicle->plate}");
                }

                if ($vehicle?->trashed()) {
                    $vehicle->restore();
                }

                $vehicle ??= new Vehicle;
                $vehicle->fill([...$this->attributes($data), 'status' => Vehicle::ACTIVE])->save();
                $this->syncLinks($vehicle, $data);

                return $vehicle;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Este veículo acabou de ser cadastrado em outra tela. Atualize e tente de novo.');
        }

        return $this->get($vehicle->uuid);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $data): Vehicle
    {
        try {
            DB::transaction(function () use ($uuid, $data) {
                $vehicle = $this->get($uuid);
                $plate = Vehicle::normalizePlate($data['plate']);

                if (Vehicle::query()->where('plate', $plate)->whereKeyNot($vehicle->getKey())->exists()) {
                    throw new DomainException("Já existe um veículo com a placa {$plate}");
                }

                $vehicle->update($this->attributes($data));
                $this->syncLinks($vehicle, $data);

                // TODO(traccar): placa trocada com rastreador instalado —
                // renomear os devices dele para a placa nova.
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Outro cadastro acabou de usar esta placa, implemento ou rastreador. Atualize e tente de novo.');
        }

        return $this->get($uuid);
    }

    /**
     * Todos os engates do veículo, puxando e sendo puxado, do mais recente
     * para o mais antigo.
     *
     * @return Collection<int, VehicleCoupling>
     */
    public function couplingHistory(string $uuid): Collection
    {
        $vehicle = $this->get($uuid);

        return VehicleCoupling::query()
            ->where(fn (Builder $query) => $query
                ->where('traction_vehicle_id', $vehicle->getKey())
                ->orWhere('trailer_vehicle_id', $vehicle->getKey()))
            ->with(['traction', 'trailer'])
            ->latest('coupled_at')
            ->latest('id')
            ->get();
    }

    public function setStatus(string $uuid, string $status): void
    {
        $this->get($uuid)->update(['status' => $status]);
    }

    /**
     * Exclui o veículo. Os engates e as instalações são encerrados, não
     * apagados; os rastreadores ficam disponíveis para outro veículo.
     */
    public function remove(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $vehicle = $this->get($uuid);

            $this->couplings->releaseAll($vehicle);
            $this->installations->removeAll($vehicle);
            $vehicle->delete();
        });
    }

    /**
     * Os campos do veículo, com proprietário, transportador e responsável
     * trocados de `uuid` para `id`.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = collect($data)->except(['owner', 'carrier', 'responsible', 'trailers', 'devices'])->all();

        $attributes['owner_id'] = VehicleOwner::query()->where('uuid', $data['owner'])->value('id');
        $attributes['carrier_id'] = isset($data['carrier'])
            ? Carrier::query()->where('uuid', $data['carrier'])->value('id')
            : null;
        $attributes['responsible_id'] = VehicleResponsible::query()->where('uuid', $data['responsible'])->value('id');
        $attributes['max_traction_weight_kg'] = $data['max_traction_weight_kg'] ?? null;

        return $attributes;
    }

    /**
     * Implementos e rastreadores. `trailers` e `devices` ausentes não
     * mexem no que já está; presentes, são a lista inteira.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncLinks(Vehicle $vehicle, array $data): void
    {
        $vehicle->load('vehicleType');

        // Tipo trocado para o outro grupo: o automotor que virou implemento
        // não puxa mais nada, e vice-versa.
        if ($vehicle->wasChanged('vehicle_type_id')
            && VehicleType::query()->find($vehicle->getPrevious()['vehicle_type_id'])?->kind !== $vehicle->kind()) {
            $this->couplings->releaseAll($vehicle);
        }

        if ($vehicle->kind() === VehicleKind::MotorVehicle && array_key_exists('trailers', $data)) {
            $this->couplings->sync($vehicle, $data['trailers']);
        }

        if (array_key_exists('devices', $data)) {
            $this->installations->sync($vehicle, $data['devices']);
        }
    }

    private function query(): Builder
    {
        $person = fn ($query) => $query->withTrashed();

        return Vehicle::query()->with([
            'vehicleType',
            'bodyType',
            'owner.entity' => $person,
            'carrier.entity' => $person,
            'responsible.entity' => $person,
            'trailerCouplings.trailer.vehicleType',
            'tractionCoupling.traction.vehicleType',
            'installations.device.deviceModel',
        ]);
    }
}
