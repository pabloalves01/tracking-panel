<?php

namespace App\Http\Controllers\Location;

use App\Http\Controllers\Controller;
use App\Http\Resources\Location\CityResource;
use App\Http\Resources\Location\StateResource;
use App\Models\Location\State;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LocationController extends Controller
{
    /**
     * Estados oficiais do IBGE, em ordem alfabética.
     *
     * O `id` devolvido é o `state_id` que os cadastros gravam.
     */
    public function states(): AnonymousResourceCollection
    {
        $states = State::query()->orderBy('name')->get();

        return StateResource::collection($states);
    }

    /**
     * Municípios de um estado, em ordem alfabética.
     *
     * A busca por nome acontece no cliente. Um estado cabe inteiro na lista.
     */
    public function cities(State $state): AnonymousResourceCollection
    {
        $cities = $state->cities()->orderBy('name')->get();

        return CityResource::collection($cities);
    }
}
