<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\ShowCompanyFuelStationRequest;
use App\Http\Requests\Registry\StoreCompanyFuelStationRequest;
use App\Http\Resources\FuelStation\CompanyFuelStationResource;
use App\Http\Resources\FuelStation\FuelStationResource;
use App\Services\FuelStation\CompanyFuelStationService;

class FuelStationLinkController extends Controller
{
    public function __construct(private CompanyFuelStationService $companyFuelStationService) {}

    public function index()
    {
        return CompanyFuelStationResource::collection(
            $this->companyFuelStationService->listForCompany(),
        );
    }

    public function show(ShowCompanyFuelStationRequest $request)
    {
        return new FuelStationResource(
            $this->companyFuelStationService->findByCnpj($request->validated('cnpj')),
        );
    }

    public function store(StoreCompanyFuelStationRequest $request)
    {
        $this->companyFuelStationService->store($request->validated('cnpj'));

        return response()->json([
            'message' => 'Convite enviado',
        ], 201);
    }

    public function destroy(string $uuid)
    {
        $this->companyFuelStationService->destroy($uuid);

        return response()->json([
            'message' => 'Convite cancelado',
        ]);
    }

    public function disconnect(string $uuid)
    {
        $this->companyFuelStationService->disconnectForCompany($uuid);

        return response()->json([
            'message' => 'Conexão encerrada',
        ]);
    }

    public function resendRequest(string $uuid)
    {
        $this->companyFuelStationService->resendRequest($uuid);

        return response()->json([
            'message' => 'Convite reenviado',
        ]);
    }
}
