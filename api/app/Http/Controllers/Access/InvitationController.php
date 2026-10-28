<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreInvitationRequest;
use App\Http\Resources\Access\InvitationResource;
use App\Services\Access\InvitationService;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Os convites da organização ativa.
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly CurrentOrganization $current,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return InvitationResource::collection(
            $this->invitations->listFor($this->current->organization)
        );
    }

    public function store(StoreInvitationRequest $request): JsonResponse
    {
        $invitation = $this->invitations->create(
            $this->current->organization,
            $request->validated('email'),
            $request->user(),
        );

        return response()->json([
            'message' => 'Convite enviado para '.$invitation->email,
            'data' => InvitationResource::make($invitation),
        ], 201);
    }

    public function resend(string $uuid): JsonResponse
    {
        $invitation = $this->invitations->resend(
            $this->invitations->findFor($this->current->organization, $uuid)
        );

        return response()->json([
            'message' => 'Convite reenviado para '.$invitation->email,
            'data' => InvitationResource::make($invitation),
        ]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->invitations->revoke(
            $this->invitations->findFor($this->current->organization, $uuid)
        );

        return response()->json(['message' => 'Convite cancelado']);
    }
}
