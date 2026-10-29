<?php

namespace App\Http\Controllers\Fleet;

use App\Enums\Fleet\VehicleKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\VehicleRequest;
use App\Http\Resources\Fleet\VehicleCouplingResource;
use App\Http\Resources\Fleet\VehicleResource;
use App\Models\Fleet\Vehicle;
use App\Services\Fleet\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Veículos — automotor e implemento.
 */
class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $vehicles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'kind' => ['nullable', Rule::enum(VehicleKind::class)],
            'status' => ['nullable', Rule::in([Vehicle::ACTIVE, Vehicle::INACTIVE])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return VehicleResource::collection($this->vehicles->list($filters));
    }

    public function show(string $uuid): VehicleResource
    {
        return VehicleResource::make($this->vehicles->get($uuid));
    }

    public function store(VehicleRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Veículo cadastrado com sucesso',
            'data' => VehicleResource::make($this->vehicles->store($request->validated())),
        ], 201);
    }

    public function update(VehicleRequest $request, string $uuid): JsonResponse
    {
        return response()->json([
            'message' => 'Veículo atualizado com sucesso',
            'data' => VehicleResource::make($this->vehicles->update($uuid, $request->validated())),
        ]);
    }

    public function couplings(string $uuid): AnonymousResourceCollection
    {
        return VehicleCouplingResource::collection($this->vehicles->couplingHistory($uuid));
    }

    public function activate(string $uuid): JsonResponse
    {
        $this->vehicles->setStatus($uuid, Vehicle::ACTIVE);

        return response()->json(['message' => 'Veículo ativado com sucesso']);
    }

    public function deactivate(string $uuid): JsonResponse
    {
        $this->vehicles->setStatus($uuid, Vehicle::INACTIVE);

        return response()->json(['message' => 'Veículo desativado com sucesso']);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->vehicles->remove($uuid);

        return response()->json(['message' => 'Veículo excluído com sucesso']);
    }
}
