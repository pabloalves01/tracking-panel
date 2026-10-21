<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\BrasilApiException;
use App\Exceptions\FuelStationSignupException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CheckFuelStationSignupEmailRequest;
use App\Http\Requests\Auth\StartFuelStationSignupRequest;
use App\Http\Requests\Auth\VerifyFuelStationSignupRequest;
use App\Models\FuelStationSignup;
use App\Services\FuelStation\FuelStationSignupService;

class FuelStationSignupController extends Controller
{
    public function __construct(private FuelStationSignupService $signups) {}

    public function cnpj(string $cnpj)
    {
        return $this->respond(fn () => response()->json($this->signups->lookup($cnpj)));
    }

    public function checkEmail(CheckFuelStationSignupEmailRequest $request)
    {
        return $this->respond(fn () => response()->json(
            $this->signups->checkEmail($request->validated('cnpj'), $request->validated('email')),
        ));
    }

    public function store(StartFuelStationSignupRequest $request)
    {
        return $this->respond(function () use ($request) {
            $result = $this->signups->start($request->validated(), (string) $request->ip());
            $status = isset($result['signup_uuid']) ? 200 : 201;

            return response()->json($result, $status);
        });
    }

    public function resend(FuelStationSignup $signup)
    {
        return $this->respond(fn () => response()->json($this->signups->resend($signup)));
    }

    public function verify(VerifyFuelStationSignupRequest $request, FuelStationSignup $signup)
    {
        return $this->respond(fn () => response()->json(
            $this->signups->verify($signup, $request->validated('code')),
            201,
        ));
    }

    private function respond(callable $callback)
    {
        try {
            return $callback();
        } catch (BrasilApiException|FuelStationSignupException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], $exception->status);
        }
    }
}
