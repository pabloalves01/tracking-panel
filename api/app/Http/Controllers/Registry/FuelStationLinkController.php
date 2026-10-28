<?php

namespace App\Http\Controllers\Registry;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registry\StoreFuelSupplyLinkRequest;
use App\Http\Resources\Organization\FuelSupplyLinkResource;
use App\Http\Resources\OrganizationResource;
use App\Services\Organization\FuelSupplyLinkService;
use App\Support\CurrentOrganization;
use App\Support\Text;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Os postos conveniados, do lado da empresa.
 */
class FuelStationLinkController extends Controller
{
    public function __construct(
        private readonly FuelSupplyLinkService $links,
        private readonly CurrentOrganization $current,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return FuelSupplyLinkResource::collection(
            $this->links->listForCompany($this->current->organization)
        );
    }

    /** Procura um posto pelo CNPJ antes de pedir o convênio. */
    public function byDocument(Request $request): JsonResponse
    {
        $station = $this->links->findStationByDocument(
            Text::digits((string) $request->query('document'))
        );

        return response()->json([
            'data' => $station ? OrganizationResource::make($station) : null,
        ]);
    }

    public function store(StoreFuelSupplyLinkRequest $request): JsonResponse
    {
        $link = $this->links->request(
            $this->current->organization,
            $request->validated('document'),
        );

        return response()->json([
            'message' => 'Pedido de convênio enviado ao posto',
            'data' => FuelSupplyLinkResource::make($link),
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
