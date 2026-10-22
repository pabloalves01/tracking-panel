<?php

namespace App\Http\Controllers\FuelStation;

use App\Http\Controllers\Controller;
use App\Http\Resources\FuelStation\CompanyFuelStationResource;
use App\Services\FuelStation\CompanyFuelStationService;

class StationCompanyController extends Controller
{
    public function __construct(private CompanyFuelStationService $companyFuelStationService) {}

    public function index()
    {
        return CompanyFuelStationResource::collection(
            $this->companyFuelStationService->listForStation(),
        );
    }

    public function accept(string $uuid)
    {
        $this->companyFuelStationService->accept($uuid);

        return response()->json([
            'message' => 'Vínculo aceito',
        ]);
    }

    public function reject(string $uuid)
    {
        $this->companyFuelStationService->reject($uuid);

        return response()->json([
            'message' => 'Pedido recusado',
        ]);
    }

    public function disconnect(string $uuid)
    {
        $this->companyFuelStationService->disconnect($uuid);

        return response()->json([
            'message' => 'Conexão encerrada',
        ]);
    }
}
