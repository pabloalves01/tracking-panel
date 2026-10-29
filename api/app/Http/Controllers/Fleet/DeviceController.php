<?php

namespace App\Http\Controllers\Fleet;

use App\Enums\Fleet\InstallationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\DeviceRequest;
use App\Http\Resources\Fleet\DeviceInstallationResource;
use App\Http\Resources\Fleet\DeviceResource;
use App\Models\Fleet\Device;
use App\Services\Fleet\DeviceService;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Rastreadores.
 */
class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $devices) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([Device::ACTIVE, Device::INACTIVE])],
            'installed' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (isset($filters['installed'])) {
            $filters['installed'] = $request->boolean('installed');
        }

        return DeviceResource::collection($this->devices->list($filters));
    }

    public function show(string $uuid): DeviceResource
    {
        return DeviceResource::make($this->devices->get($uuid));
    }

    public function store(DeviceRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Rastreador cadastrado com sucesso',
            'data' => DeviceResource::make($this->devices->store($request->validated())),
        ], 201);
    }

    public function update(DeviceRequest $request, string $uuid): JsonResponse
    {
        return response()->json([
            'message' => 'Rastreador atualizado com sucesso',
            'data' => DeviceResource::make($this->devices->update($uuid, $request->validated())),
        ]);
    }

    public function installations(string $uuid): AnonymousResourceCollection
    {
        return DeviceInstallationResource::collection($this->devices->installationHistory($uuid));
    }

    public function activate(string $uuid): JsonResponse
    {
        $this->devices->setStatus($uuid, Device::ACTIVE);

        return response()->json(['message' => 'Rastreador ativado com sucesso']);
    }

    public function deactivate(string $uuid): JsonResponse
    {
        $this->devices->setStatus($uuid, Device::INACTIVE);

        return response()->json(['message' => 'Rastreador desativado com sucesso']);
    }

    public function install(Request $request, string $uuid): JsonResponse
    {
        $data = $request->validate([
            'vehicle' => [
                'required',
                'uuid',
                Rule::exists('vehicles', 'uuid')
                    ->where('company_id', app(CurrentOrganization::class)->id())
                    ->whereNull('deleted_at'),
            ],
            'role' => ['nullable', Rule::enum(InstallationRole::class)],
            'transfer' => ['sometimes', 'boolean'],
        ], [
            'vehicle.required' => 'Selecione o veículo',
            'vehicle.exists' => 'Selecione um veículo cadastrado',
            'role.enum' => 'A função do rastreador é inválida',
        ]);

        $device = $this->devices->install(
            $uuid,
            $data['vehicle'],
            InstallationRole::tryFrom($data['role'] ?? ''),
            $request->boolean('transfer')
        );

        return response()->json([
            'message' => "Rastreador instalado em {$device->installation->vehicle->plate}",
            'data' => DeviceResource::make($device),
        ]);
    }

    public function uninstall(string $uuid): JsonResponse
    {
        return response()->json([
            'message' => 'Rastreador retirado do veículo',
            'data' => DeviceResource::make($this->devices->uninstall($uuid)),
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->devices->remove($uuid);

        return response()->json(['message' => 'Rastreador excluído com sucesso']);
    }
}
