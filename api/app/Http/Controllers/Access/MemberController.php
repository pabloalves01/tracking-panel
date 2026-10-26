<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UpdateMemberRoleRequest;
use App\Http\Resources\Access\MemberResource;
use App\Services\Access\MemberService;
use App\Support\MembershipContext;
use Illuminate\Http\Request;

/**
 * Membros da empresa ou do posto do token. As rotas são as mesmas nos dois painéis.
 */
class MemberController extends Controller
{
    public function __construct(private MemberService $memberService) {}

    public function index()
    {
        return MemberResource::collection(
            $this->memberService->list(MembershipContext::current()),
        );
    }

    public function update(UpdateMemberRoleRequest $request, string $uuid)
    {
        $this->memberService->updateRole(MembershipContext::current(), $uuid, $request->validated('role'));

        return response()->json([
            'message' => 'Papel alterado',
        ]);
    }

    /**
     * Admin remove alguém, ou a própria pessoa sai.
     */
    public function destroy(Request $request, string $uuid)
    {
        $context = MembershipContext::current();
        $this->memberService->remove($context, $uuid, $request->user());

        return response()->json([
            'message' => $uuid === $request->user()->uuid
                ? 'Você saiu '.$context->ofLabel()
                : 'Usuário removido',
        ]);
    }
}
