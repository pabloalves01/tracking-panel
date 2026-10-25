<?php

namespace Tests\Feature\System;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Services\System\CompanyService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_vinculo_nasce_como_admin(): void
    {
        $company = $this->company();
        $user = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        $this->assertSame('admin', CompanyUser::query()->first()->fresh()->role);
    }

    public function test_banco_nao_aceita_vinculo_duplicado(): void
    {
        $company = $this->company();
        $user = User::factory()->create();

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
        ]);

        try {
            DB::transaction(function () use ($company, $user) {
                CompanyUser::query()->create([
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                    'role' => 'admin',
                ]);
            });
            $this->fail('O banco aceitou o vínculo duplicado.');
        } catch (UniqueConstraintViolationException) {
            $this->assertSame(1, CompanyUser::withTrashed()->count());
        }
    }

    public function test_vincular_de_novo_restaura_o_vinculo_removido(): void
    {
        $company = $this->company();
        $user = User::factory()->create();
        $service = app(CompanyService::class);

        $link = $service->linkUser($company, $user);
        $link->forceFill(['role' => 'member'])->save();
        $link->delete();

        $this->assertSame(0, $user->companies()->count());

        $restored = $service->linkUser($company, $user);

        $this->assertSame($link->id, $restored->id);
        $this->assertSame(1, CompanyUser::withTrashed()->count());
        $this->assertNull($restored->fresh()->deleted_at);
        $this->assertSame('admin', $restored->fresh()->role);
        $this->assertSame(1, $user->companies()->count());
    }

    public function test_vincular_quem_ja_esta_vinculado_nao_duplica(): void
    {
        $company = $this->company();
        $user = User::factory()->create();
        $service = app(CompanyService::class);

        $service->linkUser($company, $user);
        $service->linkUser($company, $user);

        $this->assertSame(1, CompanyUser::withTrashed()->count());
    }

    private function company(): Company
    {
        return Company::query()->create([
            'legal_name' => 'Empresa Teste LTDA',
            'trade_name' => 'Empresa Teste',
            'cnpj' => '00000000000191',
        ]);
    }
}
