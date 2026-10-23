<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\StoreCompanyFuelStationRequest;
use App\Http\Resources\FuelStation\CompanyFuelStationResource;
use App\Http\Resources\FuelStation\FuelStationResource;
use App\Rules\Cnpj;
use App\Services\FuelStation\CompanyFuelStationService;
use Illuminate\Http\Request;

class FuelStationLinkController extends Controller
{
    public function __construct(private CompanyFuelStationService $companyFuelStationService) {}

    public function index()
    {
        return CompanyFuelStationResource::collection(
            $this->companyFuelStationService->listForCompany(),
        );
    }

    public function show(Request $request)
    {
        return new FuelStationResource(
            $this->companyFuelStationService->findByCnpj(Cnpj::digits($request->query('cnpj'))),
        );
    }

    public function store(StoreCompanyFuelStationRequest $request)
    {
        $this->companyFuelStationService->store($request->validated('cnpj'));

        return response()->json([
            'message' => 'Pedido de vínculo enviado',
        ], 201);
    }

    public function destroy(string $uuid)
    {
        return response()->json([
            'message' => $this->companyFuelStationService->destroy($uuid),
        ]);
    }

    public function resendRequest(string $uuid)
    {
        $this->companyFuelStationService->resendRequest($uuid);

        return response()->json([
            'message' => 'Pedido reenviado',
        ]);
    }
}
