<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationType;
use App\Models\Membership;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exige uma organização selecionada e um vínculo ativo com ela.
 *
 * Substitui `EnsureCompanySelected` e `EnsureFuelStationSelected`, que eram
 * o mesmo arquivo duas vezes. O tipo exigido vem do parâmetro da rota:
 * `organization:company` ou `organization:fuel_station`; sem parâmetro,
 * qualquer tipo serve.
 */
class EnsureOrganization
{
    public function handle(Request $request, Closure $next, ?string $type = null): Response
    {
        $organizationId = $request->user()->currentAccessToken()->organization_id;

        if (! $organizationId) {
            return response()->json([
                'message' => 'Selecione uma organização para continuar',
                'code' => 'organization_not_selected',
            ], 409);
        }

        $organization = Organization::query()->whereKey($organizationId)->first();

        $membership = $organization
            ? Membership::query()
                ->where('organization_id', $organizationId)
                ->where('user_id', $request->user()->getKey())
                ->where('status', Membership::ACTIVE)
                ->first()
            : null;

        if (! $organization || ! $membership) {
            return response()->json([
                'message' => 'Você não tem mais acesso a esta organização',
                'code' => 'organization_access_lost',
            ], 403);
        }

        if ($type !== null && $organization->type !== OrganizationType::from($type)) {
            return response()->json([
                'message' => 'Esta área não pertence à organização selecionada',
                'code' => 'organization_wrong_type',
            ], 403);
        }

        app()->instance(CurrentOrganization::class, new CurrentOrganization($organization));

        return $next($request);
    }
}
