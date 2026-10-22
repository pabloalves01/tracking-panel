<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckFuelStationSignupEmailRequest;
use App\Http\Requests\Auth\StoreFuelStationSignupRequest;
use App\Services\FuelStation\FuelStationSignupService;

class FuelStationSignupController extends Controller
{
    public function __construct(private FuelStationSignupService $fuelStationSignupService) {}

    public function cnpj(string $cnpj)
    {
        return response()->json($this->fuelStationSignupService->lookup($cnpj));
    }

    public function checkEmail(CheckFuelStationSignupEmailRequest $request)
    {
        return response()->json($this->fuelStationSignupService->checkEmail(
            $request->validated('email'),
        ));
    }

    public function store(StoreFuelStationSignupRequest $request)
    {
        return response()->json($this->fuelStationSignupService->store($request->validated()), 201);
    }
}
