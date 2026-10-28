<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreFuelStationSignupRequest;
use App\Http\Requests\Auth\StoreFuelStationRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\OrganizationResource;
use App\Services\Organization\FuelStationSignupService;
use Illuminate\Http\JsonResponse;

/**
 * O cadastro de um posto: público, ou por quem já está logado.
 */
class FuelStationSignupController extends Controller
{
    public function __construct(private readonly FuelStationSignupService $signup) {}

    /** Consulta o CNPJ para preencher o formulário. */
    public function cnpj(string $cnpj): JsonResponse
    {
        return response()->json(['data' => $this->signup->lookup($cnpj)]);
    }

    public function store(StoreFuelStationSignupRequest $request): JsonResponse
    {
        $result = $this->signup->store($request->validated());

        return response()->json([
            'message' => $result['message'],
            'token' => $result['token'],
            'user' => UserResource::make($result['user']),
            'organization' => OrganizationResource::make($result['organization']),
            'redirect_to' => $result['organization']->type->homePath(),
        ], 201);
    }

    public function storeForUser(StoreFuelStationRequest $request): JsonResponse
    {
        $result = $this->signup->storeForUser(
            $request->user(),
            $request->validated('cnpj'),
        );

        return response()->json([
            'message' => $result['message'],
            'organization' => OrganizationResource::make($result['organization']),
            'redirect_to' => $result['organization']->type->homePath(),
        ], 201);
    }
}
