<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\Carrier\StoreCarrierRequest;
use App\Http\Requests\Registry\Carrier\UpdateCarrierRequest;
use App\Http\Resources\Registry\Carrier\CarrierResource;
use App\Services\Registry\CarrierService;
use DomainException;
use Exception;
use Illuminate\Support\Facades\Log;

class CarrierController extends Controller
{
    public function __construct(private CarrierService $carrierService) {}

    public function index()
    {
        try {
            $carriers = $this->carrierService->list();

            return CarrierResource::collection($carriers);
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

    public function store(StoreCarrierRequest $request)
    {
        try {
            $this->carrierService->store($request->validated());

            return response()->json([
                'message' => 'Transportadora criada com sucesso',
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
            $carrier = $this->carrierService->get($uuid);

            return new CarrierResource($carrier);
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

    public function update(UpdateCarrierRequest $request, string $uuid)
    {
        try {
            $this->carrierService->update($uuid, $request->validated());

            return response()->json([
                'message' => 'Transportadora atualizada com sucesso',
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
            $this->carrierService->deactivate($uuid);

            return response()->json([
                'message' => 'Transportadora desativada com sucesso',
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
            $this->carrierService->activate($uuid);

            return response()->json([
                'message' => 'Transportadora ativada com sucesso',
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
