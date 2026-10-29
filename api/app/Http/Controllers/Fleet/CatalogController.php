<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Resources\Fleet\BodyTypeResource;
use App\Http\Resources\Fleet\DeviceModelResource;
use App\Http\Resources\Fleet\VehicleTypeResource;
use App\Models\Fleet\BodyType;
use App\Models\Fleet\DeviceModel;
use App\Models\Fleet\VehicleType;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Os catálogos globais da frota, mantidos pela LogIV, para os formulários.
 */
class CatalogController extends Controller
{
    public function vehicleTypes(): AnonymousResourceCollection
    {
        return VehicleTypeResource::collection(VehicleType::query()->orderBy('id')->get());
    }

    public function bodyTypes(): AnonymousResourceCollection
    {
        return BodyTypeResource::collection(BodyType::query()->orderBy('name')->get());
    }

    public function deviceModels(): AnonymousResourceCollection
    {
        return DeviceModelResource::collection(
            DeviceModel::query()->orderBy('manufacturer')->orderBy('name')->get()
        );
    }
}
