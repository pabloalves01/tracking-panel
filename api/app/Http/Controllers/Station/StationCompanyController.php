<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Http\Resources\Organization\FuelSupplyLinkResource;
use App\Services\Organization\FuelSupplyLinkService;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * As empresas que pedem ou têm convênio com este posto.
 */
class StationCompanyController extends Controller
{
    public function __construct(
        private readonly FuelSupplyLinkService $links,
        private readonly CurrentOrganization $current,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return FuelSupplyLinkResource::collection(
            $this->links->listForStation($this->current->organization)
        );
    }

    public function accept(string $uuid): JsonResponse
    {
        $this->links->accept($this->current->organization, $uuid);

        return response()->json(['message' => 'Convênio aceito']);
    }

    public function reject(string $uuid): JsonResponse
    {
        $this->links->reject($this->current->organization, $uuid);

        return response()->json(['message' => 'Pedido recusado']);
    }

    public function disconnect(string $uuid): JsonResponse
    {
        $this->links->disconnect($this->current->organization, $uuid);

        return response()->json(['message' => 'Convênio encerrado']);
    }
}
