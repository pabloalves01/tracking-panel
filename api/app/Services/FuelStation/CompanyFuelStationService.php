<?php

namespace App\Services\FuelStation;

use App\Models\CompanyFuelStation;
use App\Models\FuelStation;
use App\Support\CurrentCompany;
use App\Support\CurrentFuelStation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyFuelStationService
{
    /**
     * @return Collection<int, CompanyFuelStation>
     */
    public function listForCompany(): Collection
    {
        return CompanyFuelStation::query()
            ->where('company_id', app(CurrentCompany::class)->id)
            ->with('fuelStation')
            ->latest()
            ->get();
    }

    /**
     * Posto inativo continua na consulta. Bloquear inativo ainda não foi decidido.
     */
    public function findByCnpj(string $cnpj): FuelStation
    {
        return FuelStation::query()
            ->with(['city', 'state'])
            ->where('cnpj', $cnpj)
            ->firstOrFail();
    }

    /**
     * O FormRequest já garante que o posto existe.
     */
    public function store(string $cnpj): void
    {
        try {
            DB::transaction(function () use ($cnpj) {
                CompanyFuelStation::query()->create([
                    'company_id' => app(CurrentCompany::class)->id,
                    'fuel_station_id' => FuelStation::query()->where('cnpj', $cnpj)->value('id'),
                    'status' => 'request_sent',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(422, 'Este CNPJ já foi solicitado');
        }
    }

    /**
     * Vínculos da empresa:
     * - convite enviado: a empresa cancela (apaga o registro) e o posto aceita ou recusa
     * - ativo: empresa ou posto desconectam
     * - recusado ou desconectado: a empresa só pode reenviar o convite
     */
    public function destroy(string $stationUuid): void
    {
        $link = $this->companyLink($stationUuid);

        if ($link->status !== 'request_sent') {
            abort(422, 'Só é possível cancelar um convite pendente');
        }

        $link->delete();
    }

    public function disconnectForCompany(string $stationUuid): void
    {
        $link = $this->companyLink($stationUuid);

        if ($link->status !== 'active') {
            abort(422, 'Este vínculo não está ativo');
        }

        $link->update(['status' => 'disconnected']);
    }

    /**
     * @return Collection<int, CompanyFuelStation>
     */
    public function listForStation(): Collection
    {
        return CompanyFuelStation::query()
            ->where('fuel_station_id', app(CurrentFuelStation::class)->id)
            ->whereHas('company')
            ->with(['fuelStation', 'company'])
            ->latest()
            ->get();
    }

    public function accept(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if ($link->status !== 'request_sent') {
            abort(422, 'Este convite não está pendente');
        }

        $link->update(['status' => 'active']);
    }

    public function reject(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if ($link->status !== 'request_sent') {
            abort(422, 'Este convite não está pendente');
        }

        $link->update(['status' => 'rejected']);
    }

    public function disconnect(string $companyUuid): void
    {
        $link = $this->stationLink($companyUuid);

        if ($link->status !== 'active') {
            abort(422, 'Este vínculo não está ativo');
        }

        $link->update(['status' => 'disconnected']);
    }

    private function companyLink(string $stationUuid): CompanyFuelStation
    {
        $link = CompanyFuelStation::query()
            ->where('company_id', app(CurrentCompany::class)->id)
            ->whereHas('fuelStation', fn ($query) => $query->where('uuid', $stationUuid))
            ->first();

        if (! $link) {
            abort(404, 'Pedido não encontrado');
        }

        return $link;
    }

    private function stationLink(string $companyUuid): CompanyFuelStation
    {
        $link = CompanyFuelStation::query()
            ->where('fuel_station_id', app(CurrentFuelStation::class)->id)
            ->whereHas('company', fn ($query) => $query->where('uuid', $companyUuid))
            ->first();

        if (! $link) {
            abort(404, 'Pedido não encontrado');
        }

        return $link;
    }

    public function resendRequest(string $stationUuid): void
    {
        $link = $this->companyLink($stationUuid);

        if (! in_array($link->status, ['rejected', 'disconnected'], true)) {
            abort(422, 'Este convite não pode ser reenviado');
        }

        if ($link->fuelStation->trashed()) {
            throw ValidationException::withMessages([
                'cnpj' => 'Este posto não está cadastrado na Logiv.',
            ]);
        }

        $link->update(['status' => 'request_sent']);
    }
}
