<?php

namespace App\Services\System;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CompanyService
{

    public function list()
    {
        return Company::withCount('users')->orderBy('created_at', 'desc')->get();
    }

    public function store(array $data): Company
    {
        return DB::transaction(function () use ($data) {
            $company = Company::create([
                'legal_name' => $data['legal_name'],
                'trade_name' => $data['trade_name'],
                'cnpj' => $data['cnpj'],
                'logo_path' => $data['logo_path'] ?? null,
            ]);

            $user = User::where('email', $data['user']['email'])->first();

            if (! $user) {
                $user = User::create([
                    'name' => $data['user']['name'],
                    'email' => $data['user']['email'],
                    'password' => $data['user']['password'],
                ]);
            }

            CompanyUser::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => 'admin',
            ]);

            return $company;
        });
    }

    public function get(string $uuid): Company
    {
        return Company::where('uuid', $uuid)->firstOrFail();
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
            ]);

            return $company;
        });
    }

    public function delete(string $uuid): void
    {
        DB::transaction(function () use ($uuid) {
            $company = Company::where('uuid', $uuid)->firstOrFail();
            $company->delete();
        });
    }
}
