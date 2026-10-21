<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SelectCompanyRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\System\Company\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');
        $credentials['email'] = strtolower(trim((string) $credentials['email']));

        if (! Auth::attempt($credentials)) {
            abort(401, 'E-mail ou senha inválidos.');
        }
        $user = $request->user();
        $token = $user->createToken('web')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => UserResource::make($user),
            'companies' => CompanyResource::collection($user->companies()->get()),
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
        ])->save();
        return CompanyResource::make($company);
    }

    public function me(Request $request)
    {
        return UserResource::make($request->user());
    }

    public function companies(Request $request)
    {
        return CompanyResource::collection($request->user()->companies()->get());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Sessão encerrada.']);
    }
}
