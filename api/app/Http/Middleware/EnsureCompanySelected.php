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
        $companyId = $request->user()->currentAccessToken()->company_id;

        if (!$companyId) {
            abort(409, 'Selecione uma empresa');
        }

        $belongs = $request->user()->companies()->whereKey($companyId)->exists();

        if (!$belongs) {
            abort(409, 'Você não tem permissão para acessar esta empresa');
        }

        app()->instance(CurrentCompany::class, new CurrentCompany($companyId));

        return $next($request);
    }
}
