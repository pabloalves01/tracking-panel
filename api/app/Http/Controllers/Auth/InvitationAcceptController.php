<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInvitationRequest;
use App\Models\User;
use App\Services\Access\InvitationService;
use Illuminate\Http\Request;

/**
 * Tela do convite: quem tem o link vê o convite, cria a conta ou aceita logado.
 */
class InvitationAcceptController extends Controller
{
    public function __construct(private InvitationService $invitationService) {}

    /**
     * Rota pública. Se vier um token de sessão, diz também se a pessoa já é membro.
     */
    public function show(string $token)
    {
        $invitation = $this->invitationService->findByToken($token);
        $user = auth('sanctum')->user();

        return response()->json([
            'data' => [
                'type' => $invitation->company_id ? 'company' : 'fuel_station',
                'name' => $this->invitationService->placeName($invitation),
                'role' => $invitation->role,
                'email' => $invitation->email,
                'has_account' => User::query()->where('email', $invitation->email)->exists(),
                'expired' => $invitation->isExpired(),
                'expires_at' => $invitation->expires_at,
                'is_member' => $user
                    && $this->invitationService->contextOf($invitation)->members()
                        ->where('user_id', $user->id)
                        ->exists(),
            ],
        ]);
    }

    public function register(RegisterInvitationRequest $request, string $token)
    {
        [$user, $context] = $this->invitationService->register(
            $token,
            $request->validated('name'),
            $request->validated('password'),
        );

        $newToken = $user->createToken('web');
        $newToken->accessToken->forceFill($context->tokenColumns())->save();

        return response()->json([
            'token' => $newToken->plainTextToken,
            'type' => $context->type,
        ], 201);
    }

    public function accept(Request $request, string $token)
    {
        [$context, $alreadyMember] = $this->invitationService->accept($token, $request->user());

        $request->user()->currentAccessToken()->forceFill($context->tokenColumns())->save();

        return response()->json([
            'type' => $context->type,
            'already_member' => $alreadyMember,
        ]);
    }
}
