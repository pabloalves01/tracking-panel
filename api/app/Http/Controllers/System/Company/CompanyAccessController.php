<?php

namespace App\Http\Controllers\System\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UpdateMemberRoleRequest;
use App\Http\Requests\System\Company\StoreCompanyInvitationRequest;
use App\Http\Resources\Access\InvitationResource;
use App\Http\Resources\Access\MemberResource;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Services\Access\InvitationService;
use App\Services\Access\MemberService;
use App\Support\Membership;
use App\Support\MembershipScope;
use App\Support\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompanyAccessController extends Controller
{
    public function __construct(private InvitationService $invitations, private MemberService $members) {}

    public function users(string $uuid)
    {
        $company = $this->company($uuid);

        $members = CompanyUser::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [Membership::ACTIVE, Membership::SUSPENDED])
            ->with('user')
            ->orderBy('id')
            ->get();

        return MemberResource::collection($members);
    }

    public function updateUser(UpdateMemberRoleRequest $request, string $uuid, string $user)
    {
        $this->members->updateRole($this->scope($uuid), $request->user(), $user, $request->validated('role'));

        return response()->json([
            'message' => 'Papel atualizado',
        ]);
    }

    public function suspendUser(Request $request, string $uuid, string $user)
    {
        $this->members->suspend($this->scope($uuid), $request->user(), $user);

        return response()->json([
            'message' => 'Pessoa suspensa',
        ]);
    }

    public function reactivateUser(Request $request, string $uuid, string $user)
    {
        $this->members->reactivate($this->scope($uuid), $request->user(), $user);

        return response()->json([
            'message' => 'Pessoa reativada',
        ]);
    }

    public function removeUser(Request $request, string $uuid, string $user)
    {
        $this->members->remove($this->scope($uuid), $request->user(), $user);

        return response()->json([
            'message' => 'Pessoa removida',
        ]);
    }

    public function index(string $uuid)
    {
        return InvitationResource::collection($this->invitations->listForCompany($this->company($uuid)));
    }

    public function lookup(Request $request, string $uuid)
    {
        $this->company($uuid);
        $email = Text::email($request->query('email'));

        return response()->json([
            'account_exists' => filter_var($email, FILTER_VALIDATE_EMAIL)
                && User::query()->where('email', $email)->exists(),
        ]);
    }

    public function store(StoreCompanyInvitationRequest $request, string $uuid)
    {
        $invitation = $this->invitations->createForCompany(
            $this->company($uuid),
            $request->validated('email'),
            $request->validated('role'),
            $request->user(),
            true,
        );

        return (new InvitationResource($invitation))
            ->response()
            ->setStatusCode(201);
    }

    public function resend(string $uuid, string $invitation)
    {
        return new InvitationResource(
            $this->invitations->resend($this->invitations->findForCompany($this->company($uuid), $invitation), true),
        );
    }

    public function destroy(string $uuid, string $invitation)
    {
        $this->invitations->revoke($this->invitations->findForCompany($this->company($uuid), $invitation));

        return response()->json([
            'message' => 'Convite cancelado',
        ]);
    }

    private function scope(string $uuid): MembershipScope
    {
        $company = $this->company($uuid);

        return new MembershipScope(CompanyUser::class, 'company_id', $company->id, Membership::OWNER, $company->id, null);
    }

    private function company(string $uuid): Company
    {
        if (! Str::isUuid($uuid)) {
            abort(404, 'Empresa não encontrada');
        }

        return Company::query()->where('uuid', $uuid)->firstOrFail();
    }
}
