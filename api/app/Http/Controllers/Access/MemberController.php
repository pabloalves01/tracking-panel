<?php

namespace App\Http\Controllers\Access;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\UpdateMemberRoleRequest;
use App\Http\Resources\Access\MemberResource;
use App\Models\CompanyUser;
use App\Models\FuelStationUser;
use App\Services\Access\MemberService;
use App\Support\CurrentCompany;
use App\Support\CurrentFuelStation;
use App\Support\MembershipScope;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(private MemberService $members) {}

    public function index()
    {
        $scope = $this->scope();
        $this->authorize('viewAny', $scope->membershipClass === CompanyUser::class ? CompanyUser::class : FuelStationUser::class);

        return MemberResource::collection($this->members->list($scope));
    }

    public function update(UpdateMemberRoleRequest $request, string $uuid)
    {
        $scope = $this->scope();
        $this->authorize('manage', $scope->membershipClass === CompanyUser::class ? CompanyUser::class : FuelStationUser::class);
        $this->members->updateRole($scope, $request->user(), $uuid, $request->validated('role'));

        return response()->json([
            'message' => 'Papel atualizado',
        ]);
    }

    public function suspend(Request $request, string $uuid)
    {
        return $this->destroy($request, $uuid);
    }

    public function reactivate(Request $request, string $uuid)
    {
        $scope = $this->scope();
        $this->authorize('manage', $scope->membershipClass === CompanyUser::class ? CompanyUser::class : FuelStationUser::class);
        $this->members->reactivate($scope, $request->user(), $uuid);

        return response()->json([
            'message' => 'Pessoa reativada',
        ]);
    }

    public function remove(Request $request, string $uuid)
    {
        $scope = $this->scope();
        $this->authorize('manage', $scope->membershipClass === CompanyUser::class ? CompanyUser::class : FuelStationUser::class);
        $this->members->remove($scope, $request->user(), $uuid);

        return response()->json([
            'message' => 'Pessoa removida',
        ]);
    }

    public function destroy(Request $request, string $uuid)
    {
        $scope = $this->scope();
        $this->authorize('manage', $scope->membershipClass === CompanyUser::class ? CompanyUser::class : FuelStationUser::class);
        $this->members->suspend($scope, $request->user(), $uuid);

        return response()->json([
            'message' => 'Pessoa suspensa',
        ]);
    }

    private function scope(): MembershipScope
    {
        if (app()->bound(CurrentCompany::class)) {
            $current = app(CurrentCompany::class);

            return new MembershipScope(CompanyUser::class, 'company_id', $current->id, $current->role, $current->id, null);
        }

        $current = app(CurrentFuelStation::class);

        return new MembershipScope(FuelStationUser::class, 'fuel_station_id', $current->id, $current->role, null, $current->id);
    }
}
