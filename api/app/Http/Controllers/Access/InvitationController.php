<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreInvitationRequest;
use App\Http\Resources\Access\InvitationResource;
use App\Services\Access\InvitationService;
use App\Support\MembershipContext;

/**
 * Convites da empresa ou do posto do token. O token do link sai só aqui,
 * na criação e no "novo link".
 */
class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitationService) {}

    public function index()
    {
        return InvitationResource::collection(
            $this->invitationService->list(MembershipContext::current()),
        );
    }

    public function store(StoreInvitationRequest $request)
    {
        [$invitation, $token] = $this->invitationService->create(
            MembershipContext::current(),
            $request->validated('email'),
            $request->validated('role'),
            $request->user(),
        );

        return response()->json([
            'data' => InvitationResource::make($invitation->load('inviter')),
            'token' => $token,
        ], 201);
    }

    public function renew(string $uuid)
    {
        [$invitation, $token] = $this->invitationService->renew(MembershipContext::current(), $uuid);

        return response()->json([
            'data' => InvitationResource::make($invitation),
            'token' => $token,
        ]);
    }

    public function destroy(string $uuid)
    {
        $this->invitationService->cancel(MembershipContext::current(), $uuid);

        return response()->json([
            'message' => 'Convite cancelado',
        ]);
    }
}
