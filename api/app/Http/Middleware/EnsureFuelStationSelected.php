<?php

namespace App\Http\Middleware;

use App\Support\CurrentFuelStation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFuelStationSelected
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()->currentAccessToken();

        if ($token->company_id || ! $token->fuel_station_id) {
            abort(409, 'Selecione um posto');
        }

        $belongs = $request->user()->fuelStations()->whereKey($token->fuel_station_id)->exists();

        if (! $belongs) {
            abort(403, 'Você não tem acesso a este posto');
        }

        app()->instance(CurrentFuelStation::class, new CurrentFuelStation($token->fuel_station_id));

        return $next($request);
    }
}
