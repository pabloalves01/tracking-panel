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
                'state_id' => $data['state_id'] ?? null,
                'city_id' => $data['city_id'] ?? null,
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
