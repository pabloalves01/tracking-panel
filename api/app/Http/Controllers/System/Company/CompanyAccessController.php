<?php

namespace App\Http\Controllers\System\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\Company\StoreCompanyInvitationRequest;
use App\Http\Resources\Access\InvitationResource;
use App\Http\Resources\Access\MemberResource;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Services\Access\InvitationService;
use App\Support\Membership;
use Illuminate\Support\Str;

class CompanyAccessController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function users(string $uuid)
    {
        $company = $this->company($uuid);

        $members = CompanyUser::query()
            ->where('company_id', $company->id)
            ->where('status', Membership::ACTIVE)
            ->with('user')
            ->orderBy('id')
            ->get();

        return MemberResource::collection($members);
    }

    public function index(string $uuid)
    {
        return InvitationResource::collection($this->invitations->listForCompany($this->company($uuid)));
    }

    public function store(StoreCompanyInvitationRequest $request, string $uuid)
    {
        if (is_array($request->input('emails'))) {
            return response()->json([
                'results' => $this->invitations->createManyForCompany(
                    $this->company($uuid),
                    $request->validated('emails'),
                    $request->validated('role'),
                    $request->user(),
                    true,
                ),
            ]);
        }

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
            $this->invitations->resend($this->invitations->findForCompany($this->company($uuid), $invitation)),
        );
    }

    public function destroy(string $uuid, string $invitation)
    {
        $this->invitations->revoke($this->invitations->findForCompany($this->company($uuid), $invitation));

        return response()->json([
            'message' => 'Convite cancelado',
        ]);
    }

    private function company(string $uuid): Company
    {
        if (! Str::isUuid($uuid)) {
            abort(404, 'Empresa não encontrada');
        }

        return Company::query()->where('uuid', $uuid)->firstOrFail();
    }
}
