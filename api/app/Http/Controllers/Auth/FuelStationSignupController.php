<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreFuelStationRequest;
use App\Http\Resources\OrganizationResource;
use App\Services\Organization\FuelStationSignupService;
use Illuminate\Http\JsonResponse;

/**
 * O cadastro de um posto.
 *
 * A consulta do CNPJ é pública — a tela mostra os dados da Receita antes de
 * pedir quem é a pessoa. Criar o posto exige estar logado: quem não tem conta
 * cria a conta antes, no mesmo fluxo de tela.
 */
class FuelStationSignupController extends Controller
{
    public function __construct(private readonly FuelStationSignupService $signup) {}

    /** Consulta o CNPJ para preencher o formulário. */
    public function cnpj(string $cnpj): JsonResponse
    {
        return response()->json(['data' => $this->signup->lookup($cnpj)]);
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
