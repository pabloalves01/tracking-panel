<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckFuelStationSignupEmailRequest;
use App\Http\Requests\Auth\StartFuelStationSignupRequest;
use App\Http\Requests\Auth\VerifyFuelStationSignupRequest;
use App\Models\FuelStationSignup;
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
            $request->validated('cnpj'),
            $request->validated('email'),
        ));
    }

    public function store(StartFuelStationSignupRequest $request)
    {
        $result = $this->fuelStationSignupService->start($request->validated(), (string) $request->ip());
        $status = isset($result['signup_uuid']) ? 200 : 201;

        return response()->json($result, $status);
    }

    public function resend(FuelStationSignup $signup)
    {
        return response()->json($this->fuelStationSignupService->resend($signup));
    }

    public function verify(VerifyFuelStationSignupRequest $request, FuelStationSignup $signup)
    {
        return response()->json(
            $this->fuelStationSignupService->verify($signup, $request->validated('code')),
            201,
        );
    }
}
