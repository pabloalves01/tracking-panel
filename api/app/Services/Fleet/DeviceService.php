<?php

namespace App\Services\Fleet;

use App\Enums\Fleet\InstallationRole;
use App\Models\Fleet\Device;
use App\Models\Fleet\DeviceInstallation;
use App\Models\Fleet\Vehicle;
use App\Support\CurrentOrganization;
use App\Support\Text;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * O cadastro de rastreadores e a instalação deles pela tela do rastreador.
 */
class DeviceService
{
    public function __construct(private readonly DeviceInstallationService $installations) {}

    /**
     * @param  array{search?: string|null, status?: string|null, installed?: bool|null, per_page?: int|null}  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        return $this->query()
            ->status($filters['status'] ?? null)
            ->installed($filters['installed'] ?? null)
            ->search($filters['search'] ?? null)
            ->orderBy('imei')
            ->paginate($filters['per_page'] ?? 25)
            ->withQueryString();
    }

    public function get(string $uuid): Device
    {
        return $this->query()->where('uuid', $uuid)->firstOrFail();
    }

    /**
     * O IMEI e o identificador são únicos no sistema todo. Um excluído
     * desta empresa volta com o mesmo registro; um de outra empresa é
     * recusado sem dizer de quem.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Device
    {
        try {
            $device = DB::transaction(function () use ($data) {
                $imei = Text::digits($data['imei']);
                $this->assertIdentifiersAreFree($imei, $data['tracking_id'] ?? null);

                $device = Device::withTrashed()->where('imei', $imei)->first();
                $device?->restore();
                $device ??= new Device;
                $device->fill([...$data, 'status' => Device::ACTIVE])->save();

                return $device;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Este IMEI acabou de ser cadastrado em outra tela. Atualize e tente de novo.');
        }

        return $this->get($device->uuid);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $uuid, array $data): Device
    {
        try {
            DB::transaction(function () use ($uuid, $data) {
                $device = $this->get($uuid);
                $this->assertIdentifiersAreFree(Text::digits($data['imei']), $data['tracking_id'] ?? null, $device);

                $device->update($data);

                // TODO(traccar): instalado num veículo, atualizar o device
                // (`uniqueId`, modelo, chip, ICCID).
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('Este IMEI acabou de ser cadastrado em outra tela. Atualize e tente de novo.');
        }

        return $this->get($uuid);
    }

    /**
     * Onde o rastreador esteve, do mais recente para o mais antigo.
     *
     * @return Collection<int, DeviceInstallation>
     */
    public function installationHistory(string $uuid): Collection
    {
        return DeviceInstallation::query()
            ->where('device_id', $this->get($uuid)->getKey())
            ->with('vehicle')
            ->latest('installed_at')
            ->latest('id')
            ->get();
    }

    public function setStatus(string $uuid, string $status): void
    {
        $device = $this->get($uuid);

        if ($status === Device::INACTIVE && $device->installation !== null) {
            throw new DomainException("Retire o rastreador de {$device->installation->vehicle->plate} antes de desativar");
        }

        $device->update(['status' => $status]);
    }

    public function install(string $uuid, string $vehicleUuid, ?InstallationRole $role, bool $transfer): Device
    {
        try {
            DB::transaction(function () use ($uuid, $vehicleUuid, $role, $transfer) {
                $this->installations->install(
                    $this->get($uuid),
                    Vehicle::query()->where('uuid', $vehicleUuid)->firstOrFail(),
                    $role,
                    $transfer
                );
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainException('O veículo ou o rastreador acabou de mudar em outra tela. Atualize e tente de novo.');
        }

        return $this->get($uuid);
    }

    public function uninstall(string $uuid): Device
    {
        DB::transaction(fn () => $this->installations->remove($this->get($uuid)));

        return $this->get($uuid);
    }

    /**
     * Excluir retira do veículo antes: o rastreador sai do cadastro, a
     * história de onde ele esteve fica.
     */
    public function remove(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $device = $this->get($uuid);

            $this->installations->remove($device);
            $device->delete();
        });
    }

    /**
     * O que o Traccar usa como `uniqueId` — o identificador, ou o IMEI — não
     * pode colidir com o de outro rastreador, em nenhuma das duas colunas.
     */
    private function assertIdentifiersAreFree(string $imei, ?string $trackingId, ?Device $except = null): void
    {
        $codes = array_values(array_filter([$imei, $trackingId]));

        $taken = Device::query()
            ->withoutGlobalScope('company')
            ->where(fn (Builder $query) => $query->whereIn('imei', $codes)->orWhereIn('tracking_id', $codes))
            ->when($except, fn (Builder $query) => $query->whereKeyNot($except->getKey()))
            ->first();

        if ($taken === null) {
            return;
        }

        throw new DomainException((int) $taken->company_id === app(CurrentOrganization::class)->id()
            ? "Já existe um rastreador com o IMEI {$taken->imei}"
            : 'Este IMEI ou identificador já está cadastrado no LogIV por outra empresa');
    }

    private function query(): Builder
    {
        return Device::query()->with(['deviceModel', 'installation.vehicle.vehicleType']);
    }
}
