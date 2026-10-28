<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\SelectOrganizationRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\Access\InvitationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrar, sair e escolher a organização da sessão.
 *
 * Eram dois caminhos quase iguais — `selectCompany` e `selectFuelStation`, duas
 * colunas no token, duas rotas de listagem. Agora é um só: a organização
 * tem tipo, e o tipo decide para onde o client navega.
 */
class AuthController extends Controller
{
    public function __construct(private readonly InvitationService $invitations) {}

    public function login(LoginRequest $request): JsonResponse
    {
        // `validate` confere a senha sem abrir sessão. `attempt` abriria, e
        // numa API de token isso cria um segundo jeito de estar autenticado:
        // o guard do Sanctum passa a resolver pelo cookie, `currentAccessToken`
        // devolve um `TransientToken` e todo `forceFill` adiante quebra.
        if (! Auth::validate($request->only('email', 'password'))) {
            abort(401, 'E-mail ou senha inválidos');
        }

        $user = Auth::getLastAttempted();
        $token = $user->createToken('web');

        $organizations = $user->organizations()->with(['city', 'state'])->get();
        $pending = $this->invitations->pendingFor($user);

        // Com uma organização só e nenhum convite pendente, não há o que
        // escolher: a sessão já entra nela.
        if ($organizations->count() === 1 && $pending === []) {
            $token->accessToken->forceFill([
                'organization_id' => $organizations->first()->getKey(),
            ])->save();
        }

        $user->withAccessToken($token->accessToken);

        return response()->json([
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user),
            'organizations' => OrganizationResource::collection($organizations),
            'invitations' => $pending,
        ]);
    }

    /**
     * Troca a organização da sessão.
     *
     * Devolve a organização escolhida com o caminho para onde ir, para o
     * client navegar sem recarregar a página inteira — antes era um
     * `location.assign`, que piscava a tela e recarregava o bundle.
     */
    public function selectOrganization(SelectOrganizationRequest $request): JsonResponse
    {
        $organization = $request->user()->organizations()
            ->where('organizations.uuid', $request->validated('uuid'))
            ->with(['city', 'state'])
            ->first();

        if (! $organization) {
            abort(403, 'Você não tem acesso a esta organização');
        }

        $request->user()->currentAccessToken()->forceFill([
            'organization_id' => $organization->getKey(),
        ])->save();

        return response()->json([
            'data' => OrganizationResource::make($organization),
            'redirect_to' => $organization->type->homePath(),
        ]);
    }

    /**
     * Quem está logado, onde está e onde mais pode entrar.
     *
     * O client lia isso de três rotas diferentes e remontava o estado; agora
     * vem resolvido de uma vez.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentId = $request->user()->currentAccessToken()->organization_id;

        $organizations = $user->organizations()->with(['city', 'state'])->get();
        $current = $currentId
            ? $organizations->firstWhere((fn (Organization $o) => $o->getKey() === $currentId))
            : null;

        return response()->json([
            'user' => UserResource::make($user),
            'current_organization' => $current ? OrganizationResource::make($current) : null,
            'organizations' => OrganizationResource::collection($organizations),
            'invitations' => $this->invitations->pendingFor($user),
        ]);
    }

    public function organizations(Request $request): JsonResponse
    {
        return response()->json([
            'organizations' => OrganizationResource::collection(
                $request->user()->organizations()->with(['city', 'state'])->get()
            ),
            'invitations' => $this->invitations->pendingFor($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada']);
    }
}
