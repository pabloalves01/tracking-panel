<?php

namespace App\Http\Controllers\FuelStation;

use App\Http\Controllers\Controller;
use App\Http\Resources\FuelStation\FuelStationResource;
use App\Models\FuelStation;
use App\Support\CurrentFuelStation;

class StationController extends Controller
{
    public function me()
    {
        $station = FuelStation::query()
            ->with(['city', 'state'])
            ->findOrFail(app(CurrentFuelStation::class)->id);

        return FuelStationResource::make($station);
    }
}
