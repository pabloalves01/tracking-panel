<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\StoreFuelStationAgreementRequest;
use App\Http\Resources\Organization\FuelStationAgreementResource;
use App\Http\Resources\OrganizationResource;
use App\Services\Organization\FuelStationAgreementService;
use App\Support\CurrentOrganization;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Os postos conveniados, do lado da empresa.
 */
class FuelStationAgreementController extends Controller
{
    public function __construct(
        private readonly FuelStationAgreementService $links,
        private readonly CurrentOrganization $current,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return FuelStationAgreementResource::collection(
            $this->links->listForCompany($this->current->organization)
        );
    }

    /** Procura um posto pelo CNPJ antes de pedir o convênio. */
    public function byDocument(Request $request): JsonResponse
    {
        // Sem validar, `?document[]=x` chega como array e o cast para string
        // derruba a requisição com 500 em vez de dizer o que está errado.
        $validated = $request->validate([
            'document' => ['required', 'string', 'max:20'],
        ]);

        $fuelStation = $this->links->findFuelStationByDocument(Text::digits($validated['document']));

        return response()->json([
            'data' => $fuelStation ? OrganizationResource::make($fuelStation) : null,
        ]);
    }

    public function store(StoreFuelStationAgreementRequest $request): JsonResponse
    {
        $link = $this->links->request(
            $this->current->organization,
            $request->validated('document'),
        );

        return response()->json([
            'message' => 'Pedido de convênio enviado ao posto',
            'data' => FuelStationAgreementResource::make($link),
        ], 201);
    }

    /** Cancela um pedido que o posto ainda não respondeu. */
    public function destroy(string $uuid): JsonResponse
    {
        $this->links->cancel($this->current->organization, $uuid);

        return response()->json(['message' => 'Pedido cancelado']);
    }

    public function disconnect(string $uuid): JsonResponse
    {
        $this->links->disconnect($this->current->organization, $uuid);

        return response()->json(['message' => 'Convênio encerrado']);
    }
}
