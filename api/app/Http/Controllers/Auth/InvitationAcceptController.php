<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrganizationResource;
use App\Services\Access\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O lado de quem recebeu o convite: ver, aceitar e recusar.
 *
 * Aceitar exige estar logado na conta do e-mail convidado. Quem não tem conta
 * cria antes, pelo cadastro de conta, e aceita em seguida — o convite não
 * cria credencial. Era isso que fazia a tela pedir nome e senha a quem já
 * tinha conta.
 */
class InvitationAcceptController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    /** O que a tela pública do convite mostra. */
    public function show(string $token): JsonResponse
    {
        return response()->json(['data' => $this->invitations->preview($token)]);
    }

    /** Aceita e já troca a sessão para a organização nova. */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = $this->invitations->accept($token, $request->user());

        return $this->entered($request, $invitation->organization);
    }

    /** Quem tem o link pode recusar sem entrar. */
    public function decline(string $token): JsonResponse
    {
        $this->invitations->decline($token);

        return response()->json(['message' => 'Convite recusado']);
    }

    public function mine(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->invitations->pendingFor($request->user())]);
    }

    public function acceptMine(Request $request, string $uuid): JsonResponse
    {
        $invitation = $this->invitations->acceptForUser($request->user(), $uuid);

        return $this->entered($request, $invitation->organization);
    }

    public function declineMine(Request $request, string $uuid): JsonResponse
    {
        $this->invitations->declineForUser($request->user(), $uuid);

        return response()->json(['message' => 'Convite recusado']);
    }

    // ================================ INTERNALS ================================

    private function entered(Request $request, $organization): JsonResponse
    {
        $request->user()->currentAccessToken()->forceFill([
            'organization_id' => $organization->getKey(),
        ])->save();

        return response()->json([
            'message' => 'Convite aceito',
            'organization' => OrganizationResource::make($organization),
            'redirect_to' => $organization->type->homePath(),
        ]);
    }
}
