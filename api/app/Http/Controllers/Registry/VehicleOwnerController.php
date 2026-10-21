<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\VehicleOwner\StoreVehicleOwnerRequest;
use App\Http\Requests\Registry\VehicleOwner\UpdateVehicleOwnerRequest;
use App\Http\Resources\Registry\VehicleOwner\VehicleOwnerResource;
use App\Services\Registry\VehicleOwnerService;
use DomainException;
use Exception;
use Illuminate\Support\Facades\Log;

class VehicleOwnerController extends Controller
{
    public function __construct(private VehicleOwnerService $vehicleOwnerService) {}

    public function index()
    {
        try {
            $vehicleOwners = $this->vehicleOwnerService->list();

            return VehicleOwnerResource::collection($vehicleOwners);
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

    public function store(StoreVehicleOwnerRequest $request)
    {
        try {
            $this->vehicleOwnerService->store($request->validated());

            return response()->json([
                'message' => 'Proprietário criado com sucesso',
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
            $vehicleOwner = $this->vehicleOwnerService->get($uuid);

            return new VehicleOwnerResource($vehicleOwner);
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

    public function update(UpdateVehicleOwnerRequest $request, string $uuid)
    {
        try {
            $this->vehicleOwnerService->update($uuid, $request->validated());

            return response()->json([
                'message' => 'Proprietário atualizado com sucesso',
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
            $this->vehicleOwnerService->deactivate($uuid);

            return response()->json([
                'message' => 'Proprietário desativado com sucesso',
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
            $this->vehicleOwnerService->activate($uuid);

            return response()->json([
                'message' => 'Proprietário ativado com sucesso',
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
