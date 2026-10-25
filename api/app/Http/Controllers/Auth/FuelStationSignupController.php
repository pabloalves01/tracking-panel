<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreFuelStationSignupRequest;
use App\Http\Resources\FuelStation\FuelStationResource;
use App\Services\FuelStation\FuelStationSignupService;

class FuelStationSignupController extends Controller
{
    public function __construct(private FuelStationSignupService $fuelStationSignupService) {}

    public function cnpj(string $cnpj)
    {
        return response()->json($this->fuelStationSignupService->lookup($cnpj));
    }

    /**
     * Cria o posto, vincula a conta logada como owner e já entra no posto.
     */
    public function store(StoreFuelStationSignupRequest $request)
    {
        $station = $this->fuelStationSignupService->store(
            $request->user(),
            $request->validated('cnpj'),
        );

        $request->user()->currentAccessToken()->forceFill([
            'company_id' => null,
            'fuel_station_id' => $station->id,
        ])->save();

        return response()->json([
            'message' => 'Posto cadastrado com sucesso',
            'fuel_station' => FuelStationResource::make($station->load(['city', 'state'])),
        ], 201);
    }
}
