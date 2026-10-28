<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInvitationRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\OrganizationResource;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Access\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * O lado de quem recebeu o convite: ver, aceitar, recusar e criar conta.
 */
class InvitationAcceptController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    /** O que a tela pública do convite mostra. */
    public function show(string $token): JsonResponse
    {
        return response()->json(['data' => $this->invitations->preview($token)]);
    }

    /** Sem conta: cria a conta, entra na organização e já devolve a sessão. */
    public function register(RegisterInvitationRequest $request, string $token): JsonResponse
    {
        $user = $this->invitations->registerAndAccept(
            $token,
            $request->validated('name'),
            $request->validated('password'),
            $request->validated('code'),
        );

        return $this->session($user, $this->acceptedOrganizationFor($user, $token));
    }

    /** Com conta: aceita e já troca a sessão para a organização nova. */
    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = $this->invitations->accept($token, $request->user());
        $organization = $invitation->organization;

        $request->user()->currentAccessToken()->forceFill([
            'organization_id' => $organization->getKey(),
        ])->save();

        return response()->json([
            'message' => 'Convite aceito',
            'organization' => OrganizationResource::make($organization),
            'redirect_to' => $organization->type->homePath(),
        ]);
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
        $organization = $invitation->organization;

        $request->user()->currentAccessToken()->forceFill([
            'organization_id' => $organization->getKey(),
        ])->save();

        return response()->json([
            'message' => 'Convite aceito',
            'organization' => OrganizationResource::make($organization),
            'redirect_to' => $organization->type->homePath(),
        ]);
    }

    public function declineMine(Request $request, string $uuid): JsonResponse
    {
        $this->invitations->declineForUser($request->user(), $uuid);

        return response()->json(['message' => 'Convite recusado']);
    }

    // ================================ INTERNALS ================================

    /**
     * A organização do convite que acabou de ser aceito.
     *
     * A versão anterior escolhia pelo último convite aceito daquele e-mail,
     * o que punha na organização errada quem aceitava dois em sequência.
     * Aqui a busca é pelo token desta requisição.
     */
    private function acceptedOrganizationFor(User $user, string $token)
    {
        return Invitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->with('organization')
            ->firstOrFail()
            ->organization;
    }

    private function session(User $user, $organization): JsonResponse
    {
        $token = $user->createToken('web');

        $token->accessToken->forceFill([
            'organization_id' => $organization->getKey(),
        ])->save();

        $user->withAccessToken($token->accessToken);

        return response()->json([
            'message' => 'Conta criada',
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user),
            'organization' => OrganizationResource::make($organization),
            'redirect_to' => $organization->type->homePath(),
        ], 201);
    }
}
