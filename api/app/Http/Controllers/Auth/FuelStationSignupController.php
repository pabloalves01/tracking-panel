<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreFuelStationSignupRequest;
use App\Http\Requests\Auth\StoreStationRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\FuelStation\FuelStationResource;
use App\Services\FuelStation\FuelStationSignupService;

class FuelStationSignupController extends Controller
{
    public function __construct(private FuelStationSignupService $fuelStationSignupService) {}

    public function cnpj(string $cnpj)
    {
        return response()->json($this->fuelStationSignupService->lookup($cnpj));
    }

    public function store(StoreFuelStationSignupRequest $request)
    {
        $result = $this->fuelStationSignupService->store($request->validated());

        return response()->json([
            'message' => $result['message'],
            'token' => $result['token'],
            'user' => UserResource::make($result['user']),
            'fuel_station' => FuelStationResource::make($result['fuel_station']),
        ], 201);
    }

    public function storeForUser(StoreStationRequest $request)
    {
        $result = $this->fuelStationSignupService->storeForUser(
            $request->user(),
            $request->validated('cnpj'),
        );

        return response()->json([
            'message' => $result['message'],
            'fuel_station' => FuelStationResource::make($result['fuel_station']),
        ], 201);
    }
}
