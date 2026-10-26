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

        $company = $request->user()->companies()->whereKey($companyId)->first();

        if (! $company) {
            return response()->json([
                'message' => 'Você não tem permissão para acessar esta empresa',
                'code' => 'context_access_lost',
            ], 403);
        }

        app()->instance(CurrentCompany::class, new CurrentCompany($companyId, (string) $company->pivot->role));

        return $next($request);
    }
}
