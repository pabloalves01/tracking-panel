<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\FuelStation\StoreFuelStationRequest;
use App\Http\Requests\Registry\FuelStation\UpdateFuelStationRequest;
use App\Http\Resources\Registry\FuelStation\FuelStationResource;
use App\Services\Registry\FuelStationService;
use DomainException;
use Exception;
use Illuminate\Support\Facades\Log;

class FuelStationController extends Controller
{
    public function __construct(private FuelStationService $fuelStationService) {}

    public function index()
    {
        try {
            $fuelStations = $this->fuelStationService->list();

            return FuelStationResource::collection($fuelStations);
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

    public function store(StoreFuelStationRequest $request)
    {
        try {
            $this->fuelStationService->store($request->validated());

            return response()->json([
                'message' => 'Posto criado com sucesso',
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
            $fuelStation = $this->fuelStationService->get($uuid);

            return new FuelStationResource($fuelStation);
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

    public function update(UpdateFuelStationRequest $request, string $uuid)
    {
        try {
            $this->fuelStationService->update($uuid, $request->validated());

            return response()->json([
                'message' => 'Posto atualizado com sucesso',
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
            $this->fuelStationService->deactivate($uuid);

            return response()->json([
                'message' => 'Posto desativado com sucesso',
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
            $this->fuelStationService->activate($uuid);

            return response()->json([
                'message' => 'Posto ativado com sucesso',
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
