<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\StoreOrganizationRequest;
use App\Http\Requests\System\UpdateOrganizationRequest;
use App\Http\Resources\Access\InvitationResource;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use App\Services\System\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * As organizações, para o admin do sistema.
 *
 * Ele edita tudo aqui independentemente da organização ativa da sessão —
 * e não opera os dados de uma organização sem ter vínculo com ela.
 */
class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return OrganizationResource::collection(
            $this->organizations->list([
                'search' => $request->query('search'),
                'type' => $request->query('type'),
                'per_page' => $request->query('per_page'),
            ])
        );
    }

    public function show(string $uuid): OrganizationResource
    {
        return OrganizationResource::make($this->organizations->get($uuid));
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = $this->organizations->store($request->validated());
        $invitation = $organization->getRelation('firstInvitation');

        return response()->json([
            'message' => 'Empresa criada. O convite foi enviado para '.$invitation->email,
            'data' => OrganizationResource::make($organization),
            'invitation' => InvitationResource::make($invitation),
        ], 201);
    }

    public function update(UpdateOrganizationRequest $request, string $uuid): JsonResponse
    {
        return response()->json([
            'message' => 'Organização atualizada',
            'data' => OrganizationResource::make(
                $this->organizations->update($uuid, $request->validated())
            ),
        ]);
    }

    public function activate(string $uuid): JsonResponse
    {
        $this->organizations->setStatus($uuid, Organization::ACTIVE);

        return response()->json(['message' => 'Organização ativada']);
    }

    public function deactivate(string $uuid): JsonResponse
    {
        $this->organizations->setStatus($uuid, Organization::INACTIVE);

        return response()->json(['message' => 'Organização desativada']);
    }
}
