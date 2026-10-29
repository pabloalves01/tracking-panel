<?php

namespace App\Services\Fleet;

use App\Enums\Fleet\InstallationRole;
use App\Models\Fleet\Device;
use App\Models\Fleet\DeviceInstallation;
use App\Models\Fleet\Vehicle;
use DomainException;

/**
 * Instalar, retirar e transferir rastreadores. Retirar fecha a linha
 * (`removed_at`); instalar de novo abre outra. Nada é apagado.
 *
 * As datas são do servidor, no momento da operação, e o índice único
 * parcial garante uma instalação aberta por rastreador: os períodos de um
 * mesmo rastreador não se sobrepõem.
 *
 * É aqui que o Traccar entra. Quando entrar, a chamada vai para um job
 * disparado depois do commit (`afterCommit`), com nova tentativa — não no
 * meio da transação, como no logiv-server. Chamar tudo dentro de uma
 * transação.
 */
class DeviceInstallationService
{
    /**
     * Sem `role`, o rastreador entra como principal se o veículo não tiver
     * um, e como reserva se tiver.
     *
     * `transfer` tira o rastreador do veículo em que está antes; sem ele,
     * rastreador instalado em outro veículo é recusado.
     */
    public function install(Device $device, Vehicle $vehicle, ?InstallationRole $role = null, bool $transfer = false): DeviceInstallation
    {
        $device = Device::query()->whereKey($device->getKey())->lockForUpdate()->firstOrFail();

        if (! $device->isActive()) {
            throw new DomainException("O rastreador {$device->imei} está inativo");
        }

        $current = $device->installation()->with('vehicle')->first();

        if ($current?->vehicle_id === $vehicle->getKey()) {
            if ($role !== null && $current->role !== $role) {
                $this->assertPrimaryIsFree($vehicle, $role, $current);
                $current->update(['role' => $role]);
            }

            return $current;
        }

        if ($current !== null && ! $transfer) {
            throw new DomainException(sprintf(
                'O rastreador %s está instalado em %s. Retire-o de lá antes.',
                $device->imei,
                $current->vehicle->plate
            ));
        }

        $role ??= $vehicle->installations()->where('role', InstallationRole::Primary)->exists()
            ? InstallationRole::Backup
            : InstallationRole::Primary;

        $this->assertPrimaryIsFree($vehicle, $role);
        $this->remove($device);

        $installation = DeviceInstallation::query()->create([
            'device_id' => $device->getKey(),
            'vehicle_id' => $vehicle->getKey(),
            'role' => $role,
            'installed_at' => now(),
        ]);

        // TODO(traccar): criar o device — ou atualizar, se o identificador
        // já existir lá — com `uniqueId` = `tracking_id` ou, sem ele, o
        // IMEI; `name` = placa do veículo; `model`; `phone` = número do
        // chip; `attributes.iccid`; `groupId` = grupo da empresa (criado na
        // primeira vez). Para modelos que pedem, enviar por SMS o comando de
        // troca de servidor (ver `TraccarService::SMSChangeServer` no
        // logiv-server).

        return $installation;
    }

    public function remove(Device $device): void
    {
        $installation = $device->installation()->first();

        if ($installation === null) {
            return;
        }

        $installation->update(['removed_at' => now()]);

        // TODO(traccar): tirar a placa do nome do device (voltar para o
        // identificador) e desativá-lo, para posições que chegarem depois
        // não serem atribuídas ao veículo de onde ele saiu.
    }

    /**
     * Deixa o veículo com exatamente estes rastreadores, nestas funções.
     * Quem troca de função continua na mesma instalação; rebaixar vem
     * antes de promover, para o principal nunca ficar duplicado.
     *
     * @param  array<int, array{device: string, role: string}>  $items
     */
    public function sync(Vehicle $vehicle, array $items): void
    {
        $roles = collect($items)->mapWithKeys(fn (array $item) => [$item['device'] => InstallationRole::from($item['role'])]);
        $devices = Device::query()->whereIn('uuid', $roles->keys())->get()->keyBy('uuid');

        foreach ($vehicle->installations()->with('device')->get() as $installation) {
            $role = $roles->get($installation->device->uuid);

            if ($role === null) {
                $this->remove($installation->device);
            } elseif ($role === InstallationRole::Backup && $installation->role !== $role) {
                $installation->update(['role' => $role]);
            }
        }

        foreach ($devices as $uuid => $device) {
            $this->install($device, $vehicle, $roles[$uuid]);
        }
    }

    public function removeAll(Vehicle $vehicle): void
    {
        $this->sync($vehicle, []);
    }

    private function assertPrimaryIsFree(Vehicle $vehicle, InstallationRole $role, ?DeviceInstallation $except = null): void
    {
        if ($role !== InstallationRole::Primary) {
            return;
        }

        $primary = $vehicle->installations()
            ->where('role', InstallationRole::Primary)
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->with('device')
            ->first();

        if ($primary !== null) {
            throw new DomainException(sprintf(
                '%s já tem o rastreador principal %s. Instale este como reserva ou retire o principal.',
                $vehicle->plate,
                $primary->device->imei
            ));
        }
    }
}
