<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Http\Resources\Access\MemberResource;
use App\Services\Access\MemberService;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Os membros da organização ativa.
 *
 * Um controller em vez de três — empresa, posto e sistema tinham cada um a
 * sua cópia das mesmas rotas.
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $members,
        private readonly CurrentOrganization $current,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        return MemberResource::collection($this->members->list($this->current->organization));
    }

    public function suspend(Request $request, string $uuid): JsonResponse
    {
        $this->members->suspend($this->current->organization, $request->user(), $uuid);

        return response()->json(['message' => 'Acesso suspenso']);
    }

    public function reactivate(string $uuid): JsonResponse
    {
        $this->members->reactivate($this->current->organization, $uuid);

        return response()->json(['message' => 'Acesso reativado']);
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $this->members->remove($this->current->organization, $request->user(), $uuid);

        return response()->json(['message' => 'Membro removido da organização']);
    }
}
