<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\Driver\StoreDriverRequest;
use App\Http\Requests\Registry\Driver\UpdateDriverRequest;
use App\Http\Resources\Registry\Driver\DriverResource;
use App\Services\Registry\DriverService;
use DomainException;
use Exception;
use Illuminate\Support\Facades\Log;

class DriverController extends Controller
{
    public function __construct(private DriverService $driverService) {}

    public function index()
    {
        try {
            $drivers = $this->driverService->list();

            return DriverResource::collection($drivers);
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

    public function store(StoreDriverRequest $request)
    {
        try {
            $this->driverService->store($request->validated());

            return response()->json([
                'message' => 'Motorista criado com sucesso',
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
            $driver = $this->driverService->get($uuid);

            return new DriverResource($driver);
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

    public function update(UpdateDriverRequest $request, string $uuid)
    {
        try {
            $this->driverService->update($uuid, $request->validated());

            return response()->json([
                'message' => 'Motorista atualizado com sucesso',
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
            $this->driverService->deactivate($uuid);

            return response()->json([
                'message' => 'Motorista desativado com sucesso',
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
            $this->driverService->activate($uuid);

            return response()->json([
                'message' => 'Motorista ativado com sucesso',
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
