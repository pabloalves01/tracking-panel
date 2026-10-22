<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\StoreCompanyFuelStationRequest;
use App\Http\Resources\FuelStation\CompanyFuelStationResource;
use App\Rules\Cnpj;
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

    public function store(StoreCompanyFuelStationRequest $request)
    {
        $this->companyFuelStationService->store($request->validated('cnpj'));

        return response()->json([
            'message' => 'Pedido de vínculo enviado',
        ], 201);
    }

    public function destroy(string $cnpj)
    {
        $this->companyFuelStationService->destroy(Cnpj::digits($cnpj));

        return response()->json([
            'message' => 'Pedido removido',
        ]);
    }
}
