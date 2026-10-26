<?php

namespace App\Services\System;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Access\InvitationService;
use App\Support\MembershipContext;
use Illuminate\Support\Facades\DB;

class CompanyService
{
    public function __construct(private InvitationService $invitationService) {}

    public function list()
    {
        return Company::withCount('users')->orderBy('created_at', 'desc')->get();
    }

    /**
     * Cria a empresa e o convite de admin. Devolve o token do link, que só aparece agora.
     *
     * @return array{0: Company, 1: Invitation, 2: string}
     */
    public function store(array $data, User $inviter): array
    {
        return DB::transaction(function () use ($data, $inviter) {
            $company = Company::create([
                'legal_name' => $data['legal_name'],
                'trade_name' => $data['trade_name'],
                'cnpj' => $data['cnpj'],
                'logo_path' => $data['logo_path'] ?? null,
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
            ]);

            [$invitation, $token] = $this->invitationService->create(
                MembershipContext::company($company->id),
                $data['admin_email'],
                'admin',
                $inviter,
            );

            return [$company, $invitation, $token];
        });
    }

    /**
     * Empresa com os administradores, para a tela de edição.
     */
    public function get(string $uuid): Company
    {
        return Company::where('uuid', $uuid)->with('admins.user')->firstOrFail();
    }

    public function update(string $uuid, array $data): Company
    {
        return DB::transaction(function () use ($uuid, $data) {
            $company = Company::where('uuid', $uuid)->firstOrFail();

            $company->update([
                'legal_name' => $data['legal_name'],
                'trade_name' => $data['trade_name'],
                'cnpj' => $data['cnpj'],
                'logo_path' => $data['logo_path'] ?? $company->logo_path,
                'state_id' => array_key_exists('state_id', $data) ? $data['state_id'] : $company->state_id,
                'city_id' => array_key_exists('city_id', $data) ? $data['city_id'] : $company->city_id,
            ]);

            return $company;
        });
    }

    public function deactivate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $company = Company::where('uuid', $uuid)->firstOrFail();
            $company->update(['status' => 'inactive']);
        });
    }

    public function activate(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $company = Company::where('uuid', $uuid)->firstOrFail();
            $company->update(['status' => 'active']);
        });
    }
}
