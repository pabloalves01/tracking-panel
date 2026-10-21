<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\VehicleResponsible\StoreVehicleResponsibleRequest;
use App\Http\Requests\Registry\VehicleResponsible\UpdateVehicleResponsibleRequest;
use App\Http\Resources\Registry\VehicleResponsible\VehicleResponsibleResource;
use App\Services\Registry\VehicleResponsibleService;
use DomainException;
use Exception;
use Illuminate\Support\Facades\Log;

class VehicleResponsibleController extends Controller
{
    public function __construct(private VehicleResponsibleService $vehicleResponsibleService) {}

    public function index()
    {
        try {
            $vehicleResponsibles = $this->vehicleResponsibleService->list();

            return VehicleResponsibleResource::collection($vehicleResponsibles);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function store(StoreVehicleResponsibleRequest $request)
    {
        try {
            $this->vehicleResponsibleService->store($request->validated());

            return response()->json([
                'message' => 'Responsável criado com sucesso',
            ], 201);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function show(string $uuid)
    {
        try {
            $vehicleResponsible = $this->vehicleResponsibleService->get($uuid);

            return new VehicleResponsibleResource($vehicleResponsible);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function update(UpdateVehicleResponsibleRequest $request, string $uuid)
    {
        try {
            $this->vehicleResponsibleService->update($uuid, $request->validated());

            return response()->json([
                'message' => 'Responsável atualizado com sucesso',
            ], 200);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function deactivate(string $uuid)
    {
        try {
            $this->vehicleResponsibleService->deactivate($uuid);

            return response()->json([
                'message' => 'Responsável desativado com sucesso',
            ]);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function activate(string $uuid)
    {
        try {
            $this->vehicleResponsibleService->activate($uuid);

            return response()->json([
                'message' => 'Responsável ativado com sucesso',
            ]);
        } catch (DomainException $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        } catch (Exception $e) {
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
