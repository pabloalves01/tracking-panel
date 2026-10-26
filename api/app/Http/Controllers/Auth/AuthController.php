<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SelectCompanyRequest;
use App\Http\Requests\Auth\SelectFuelStationRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\FuelStation\FuelStationResource;
use App\Http\Resources\System\Company\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            abort(401, 'E-mail ou senha inválidos.');
        }
        $user = $request->user();
        $token = $user->createToken('web');
        $companies = $user->companies()->get();
        $stations = $user->fuelStations()->with(['city', 'state'])->get();

        if ($companies->count() + $stations->count() === 1) {
            $token->accessToken->forceFill([
                'company_id' => $companies->first()?->id,
                'fuel_station_id' => $stations->first()?->id,
            ])->save();
        }

        $user->withAccessToken($token->accessToken);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user),
            'companies' => CompanyResource::collection($companies),
            'fuel_stations' => FuelStationResource::collection($stations),
        ]);
    }

    public function selectCompany(SelectCompanyRequest $request)
    {
        $company = Company::where('uuid', $request->validated('uuid'))->firstOrFail();
        $belongs = $request->user()->companies()->whereKey($company->id)->exists();
        if (! $belongs) {
            abort(403, 'Você não tem acesso a esta empresa.');
        }
        $request->user()->currentAccessToken()->forceFill([
            'company_id' => $company->id,
            'fuel_station_id' => null,
        ])->save();

        return CompanyResource::make($company);
    }

    public function selectStation(SelectFuelStationRequest $request)
    {
        $station = $request->user()->fuelStations()
            ->where('fuel_stations.uuid', $request->validated('uuid'))
            ->first();

        if (! $station) {
            abort(403, 'Você não tem acesso a este posto.');
        }

        $request->user()->currentAccessToken()->forceFill([
            'company_id' => null,
            'fuel_station_id' => $station->id,
        ])->save();

        return FuelStationResource::make($station);
    }

    public function me(Request $request)
    {
        return UserResource::make($request->user());
    }

    public function contexts(Request $request)
    {
        return response()->json([
            'companies' => CompanyResource::collection($request->user()->companies()->get()),
            'fuel_stations' => FuelStationResource::collection(
                $request->user()->fuelStations()->with(['city', 'state'])->get()
            ),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
