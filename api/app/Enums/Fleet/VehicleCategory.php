<?php

namespace App\Enums\Fleet;

/**
 * A categoria usada no pedágio e na Repom.
 */
enum VehicleCategory: string
{
    case Passenger = 'passenger';
    case LightCommercial = 'light_commercial';
    case HeavyCommercial = 'heavy_commercial';
}
