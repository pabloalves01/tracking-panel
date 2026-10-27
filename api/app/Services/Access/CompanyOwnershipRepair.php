<?php

namespace App\Services\Access;

use App\Models\CompanyUser;
use App\Models\User;
use App\Support\Membership;
use Illuminate\Support\Facades\DB;

class CompanyOwnershipRepair
{
    /**
     * Tira o administrador da Logiv dos vínculos legados e garante um proprietário por empresa.
     * Rodar de novo não muda uma empresa que já tem proprietário ativo.
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

                $candidate = CompanyUser::query()
                    ->where('company_id', $companyId)
                    ->where('role', Membership::ADMIN)
                    ->where('status', Membership::ACTIVE)
                    ->orderBy('id')
                    ->first();

                if (! $candidate) {
                    $candidate = CompanyUser::withTrashed()
                        ->where('company_id', $companyId)
                        ->orderBy('id')
                        ->first();
                }

                if (! $candidate) {
                    continue;
                }

                $candidate->forceFill([
                    'role' => Membership::OWNER,
                    'status' => Membership::ACTIVE,
                    'deleted_at' => null,
                ])->save();
            }
        });
    }
}
