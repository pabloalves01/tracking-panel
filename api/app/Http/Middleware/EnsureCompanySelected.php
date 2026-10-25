<?php

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanySelected
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()->currentAccessToken();

        if ($token->fuel_station_id) {
            abort(409, 'Selecione uma empresa');
        }

        $companyId = $token->company_id;

        if (! $companyId) {
            abort(409, 'Selecione uma empresa');
        }

        $belongs = $request->user()->companies()->whereKey($companyId)->exists();

        // Vínculo removido ou empresa inativa: perde o acesso na hora.
        if (! $belongs) {
            abort(403, 'Você não tem acesso a esta empresa');
        }

        app()->instance(CurrentCompany::class, new CurrentCompany($companyId));

        return $next($request);
    }
}
