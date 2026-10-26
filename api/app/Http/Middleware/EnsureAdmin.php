<?php

namespace App\Http\Middleware;

use App\Support\MembershipContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    /**
     * Só o admin da empresa ou do posto do token passa.
     * Roda depois de `ensure.company` ou `ensure.station`.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isAdmin = MembershipContext::current()->members()
            ->where('user_id', $request->user()->id)
            ->where('role', 'admin')
            ->exists();

        if (! $isAdmin) {
            abort(403, 'Apenas administradores podem gerenciar usuários.');
        }

        return $next($request);
    }
}
