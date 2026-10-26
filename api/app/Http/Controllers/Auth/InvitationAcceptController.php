<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterInvitationRequest;
use App\Http\Resources\Auth\UserResource;
use App\Http\Resources\FuelStation\FuelStationResource;
use App\Http\Resources\System\Company\CompanyResource;
use App\Models\Company;
use App\Models\FuelStation;
use App\Models\Invitation;
use App\Services\Access\InvitationService;
use Illuminate\Http\Request;

class InvitationAcceptController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function show(string $token)
    {
        return response()->json($this->invitations->preview($token));
    }

    public function register(RegisterInvitationRequest $request, string $token)
    {
        $user = $this->invitations->registerAndAccept(
            $token,
            $request->validated('name'),
            $request->validated('password'),
        );

        return response()->json($this->session($user, 'Conta criada e convite aceito'), 201);
    }

    public function accept(Request $request, string $token)
    {
        $this->invitations->accept($token, $request->user());
        $this->enterContext($request->user(), $token);

        return response()->json([
            'message' => 'Convite aceito',
            'user' => UserResource::make($request->user()),
        ]);
    }

    public function decline(string $token)
    {
        // O token é o segredo. Quem tem o link pode recusar sem entrar.
        $this->invitations->decline($token);

        return response()->json([
            'message' => 'Convite recusado',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function session($user, string $message): array
    {
        $invitation = Invitation::query()->where('email', $user->email)->where('status', Invitation::ACCEPTED)->latest('id')->first();
        $token = $user->createToken('web');
        $companyId = $invitation?->company_id;
        $stationId = $invitation?->fuel_station_id;
        $token->accessToken->forceFill([
            'company_id' => $stationId ? null : $companyId,
            'fuel_station_id' => $stationId,
        ])->save();
        $user->withAccessToken($token->accessToken);

        return [
            'message' => $message,
            'token' => $token->plainTextToken,
            'user' => UserResource::make($user),
            'company' => $companyId ? CompanyResource::make(Company::query()->find($companyId)) : null,
            'fuel_station' => $stationId ? FuelStationResource::make(FuelStation::query()->find($stationId)) : null,
        ];
    }

    private function enterContext($user, string $plainToken): void
    {
        $invitation = Invitation::query()->where('token_hash', hash('sha256', $plainToken))->first();
        $access = $user->currentAccessToken();

        if (! $invitation || ! $access) {
            return;
        }

        $access->forceFill([
            'company_id' => $invitation->fuel_station_id ? null : $invitation->company_id,
            'fuel_station_id' => $invitation->fuel_station_id,
        ])->save();
    }
}
