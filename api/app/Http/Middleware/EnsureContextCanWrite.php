<?php

namespace App\Http\Middleware;

use App\Support\CurrentCompany;
use App\Support\CurrentFuelStation;
use App\Support\Membership;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureContextCanWrite
{
    /**
     * Membro só consulta o contexto ativo. Proprietário e administrador escrevem.
     * As rotas de Sistema não entram aqui: o administrador da Logiv grava a
     * empresa mesmo quando o contexto ativo é de membro, ou quando não há contexto.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $role = null;

        if (app()->bound(CurrentCompany::class)) {
            $role = app(CurrentCompany::class)->role;
        } elseif (app()->bound(CurrentFuelStation::class)) {
            $role = app(CurrentFuelStation::class)->role;
        }

        if (! Membership::manages($role)) {
            abort(403, 'Seu papel neste contexto só permite consulta.');
        }

        return $next($request);
    }
}
