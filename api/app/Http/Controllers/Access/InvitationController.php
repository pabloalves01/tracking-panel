<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\StoreInvitationRequest;
use App\Http\Resources\Access\InvitationResource;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Services\Access\InvitationService;
use App\Support\CurrentCompany;
use App\Support\CurrentFuelStation;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function index()
    {
        $this->authorize('viewAny', $this->membershipClass());

        if (app()->bound(CurrentCompany::class)) {
            $company = Company::query()->findOrFail(app(CurrentCompany::class)->id);

            return InvitationResource::collection($this->invitations->listForCompany($company));
        }

        $station = FuelStation::query()->findOrFail(app(CurrentFuelStation::class)->id);

        return InvitationResource::collection($this->invitations->listForStation($station));
    }

    public function store(StoreInvitationRequest $request)
    {
        $this->authorize('manage', $this->membershipClass());

        if (is_array($request->input('emails'))) {
            $results = app()->bound(CurrentCompany::class)
                ? $this->invitations->createManyForCompany(
                    Company::query()->findOrFail(app(CurrentCompany::class)->id),
                    $request->validated('emails'),
                    $request->validated('role'),
                    $request->user(),
                )
                : $this->invitations->createManyForStation(
                    FuelStation::query()->findOrFail(app(CurrentFuelStation::class)->id),
                    $request->validated('emails'),
                    $request->validated('role'),
                    $request->user(),
                );

            return response()->json([
                'results' => $results,
            ]);
        }

        if (app()->bound(CurrentCompany::class)) {
            $company = Company::query()->findOrFail(app(CurrentCompany::class)->id);
            $invitation = $this->invitations->createForCompany(
                $company,
                $request->validated('email'),
                $request->validated('role'),
                $request->user(),
            );
        } else {
            $station = FuelStation::query()->findOrFail(app(CurrentFuelStation::class)->id);
            $invitation = $this->invitations->createForStation(
                $station,
                $request->validated('email'),
                $request->validated('role'),
                $request->user(),
            );
        }

        return (new InvitationResource($invitation))
            ->response()
            ->setStatusCode(201);
    }

    public function resend(string $uuid)
    {
        $this->authorize('manage', $this->membershipClass());

        return new InvitationResource($this->invitations->resend($this->find($uuid)));
    }

    public function destroy(string $uuid)
    {
        $this->authorize('manage', $this->membershipClass());
        $this->invitations->revoke($this->find($uuid));

        return response()->json([
            'message' => 'Convite cancelado',
        ]);
    }

    private function find(string $uuid)
    {
        if (app()->bound(CurrentCompany::class)) {
            $company = Company::query()->findOrFail(app(CurrentCompany::class)->id);

            return $this->invitations->findForCompany($company, $uuid);
        }

        $station = FuelStation::query()->findOrFail(app(CurrentFuelStation::class)->id);

        return $this->invitations->findForStation($station, $uuid);
    }

    private function membershipClass(): string
    {
        return app()->bound(CurrentCompany::class) ? CompanyUser::class : FuelStationUser::class;
    }
}
