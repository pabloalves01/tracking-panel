<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreFuelStationSignupRequest;
use App\Http\Requests\Auth\StoreStationRequest;
use App\Services\FuelStation\FuelStationSignupService;

class FuelStationSignupController extends Controller
{
    public function __construct(private FuelStationSignupService $fuelStationSignupService) {}

    public function cnpj(string $cnpj)
    {
        return response()->json($this->fuelStationSignupService->lookup($cnpj));
    }

    /**
     * Conta nova e posto. Devolve o token já no posto.
     */
    public function store(StoreFuelStationSignupRequest $request)
    {
        [$station, $user] = $this->fuelStationSignupService->store($request->validated());

        $token = $user->createToken('web');
        $token->accessToken->forceFill(['fuel_station_id' => $station->id])->save();

        return response()->json([
            'message' => 'Posto cadastrado',
            'token' => $token->plainTextToken,
        ], 201);
    }

    /**
     * Posto na conta logada. O token desta sessão passa a ser do posto.
     */
    public function storeForUser(StoreStationRequest $request)
    {
        $station = $this->fuelStationSignupService->storeForUser($request->user(), $request->validated('cnpj'));

        $request->user()->currentAccessToken()->forceFill([
            'company_id' => null,
            'fuel_station_id' => $station->id,
        ])->save();

        return response()->json([
            'message' => 'Posto cadastrado',
        ], 201);
    }
}
