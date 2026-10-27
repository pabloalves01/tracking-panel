<?php

namespace App\Services\Access;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Membership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompanyOwnershipRepair
{
    public function __construct(private AuditLogger $audit) {}

    /**
     * Tira o administrador da Logiv dos vínculos legados e promove um proprietário
     * só entre vínculos ativos. Suspenso ou removido não volta. Sem candidato, a
     * empresa fica sem proprietário e o suporte vê o registro.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $systemAdminIds = User::query()->where('is_system_admin', true)->pluck('id');

            if ($systemAdminIds->isNotEmpty()) {
                CompanyUser::withTrashed()->whereIn('user_id', $systemAdminIds)->forceDelete();
            }

            $companyIds = DB::table('companies')->orderBy('id')->pluck('id');

            foreach ($companyIds as $companyId) {
                $hasOwner = CompanyUser::query()
                    ->where('company_id', $companyId)
                    ->where('status', Membership::ACTIVE)
                    ->where('role', Membership::OWNER)
                    ->exists();

                if ($hasOwner) {
                    continue;
                }

                $candidate = $this->activeCandidate($companyId);

                if (! $candidate) {
                    $this->recordMissingOwner($companyId);

                    continue;
                }

                $candidate->forceFill([
                    'role' => Membership::OWNER,
                    'status' => Membership::ACTIVE,
                ])->save();
            }
        });
    }

    private function activeCandidate(int $companyId): ?CompanyUser
    {
        $admin = CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('role', Membership::ADMIN)
            ->where('status', Membership::ACTIVE)
            ->orderBy('id')
            ->first();

        if ($admin) {
            return $admin;
        }

        return CompanyUser::query()
            ->where('company_id', $companyId)
            ->where('status', Membership::ACTIVE)
            ->orderBy('id')
            ->first();
    }

    private function recordMissingOwner(int $companyId): void
    {
        Log::warning('Empresa sem proprietário ativo. O suporte precisa definir um.', [
            'company_id' => $companyId,
        ]);

        $company = Company::query()->find($companyId);

        if (! $company) {
            return;
        }

        $this->audit->log('company.owner_missing', $company, [
            'reason' => 'nenhum vínculo ativo elegível',
        ], $companyId);
    }
}
