<?php

namespace App\Http\Controllers\System\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\Company\StoreCompanyInvitationRequest;
use App\Http\Resources\Access\InvitationResource;
use App\Models\Company;
use App\Services\Access\InvitationService;
use App\Support\MembershipContext;
use Illuminate\Http\Request;

/**
 * Convites de administrador de uma empresa, feitos pela Logiv na edição da empresa.
 */
class CompanyInvitationController extends Controller
{
    public function __construct(private InvitationService $invitationService) {}

    public function index(string $company)
    {
        return InvitationResource::collection(
            $this->invitationService->list($this->context($company)),
        );
    }

    public function store(StoreCompanyInvitationRequest $request, string $company)
    {
        [$invitation, $token] = $this->invitationService->create(
            $this->context($company),
            $request->validated('email'),
            'admin',
            $request->user(),
        );

        return response()->json([
            'data' => InvitationResource::make($invitation->load('inviter')),
            'token' => $token,
        ], 201);
    }

    public function renew(string $company, string $invitation)
    {
        [$renewed, $token] = $this->invitationService->renew($this->context($company), $invitation);

        return response()->json([
            'data' => InvitationResource::make($renewed),
            'token' => $token,
        ]);
    }

    public function destroy(string $company, string $invitation)
    {
        $this->invitationService->cancel($this->context($company), $invitation);

        return response()->json([
            'message' => 'Convite cancelado',
        ]);
    }

    private function context(string $uuid): MembershipContext
    {
        $id = Company::query()->where('uuid', $uuid)->value('id');

        if (! $id) {
            abort(404, 'Empresa não encontrada');
        }

        return MembershipContext::company($id);
    }
}
