<?php

namespace Tests\Feature\Access;

use App\Models\Company;
use App\Models\CompanyFuelStation;
use App\Models\CompanyUser;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Invitation;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Access\CompanyOwnershipRepair;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_nao_reenvia_convite_de_proprietario_nem_de_suspenso(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        $owner = User::factory()->create(['email' => 'dona@melo.test', 'password' => 'senha1234']);
        $admin = User::factory()->create(['email' => 'admin@melo.test', 'password' => 'senha1234']);
        $suspended = User::factory()->create(['email' => 'suspenso@melo.test', 'password' => 'senha1234']);
        $owner->companies()->attach($company->id, ['role' => 'owner', 'status' => 'active']);
        $admin->companies()->attach($company->id, ['role' => 'admin', 'status' => 'active']);
        $suspended->companies()->attach($company->id, ['role' => 'member', 'status' => 'suspended']);

        $ownerInvite = Invitation::query()->create([
            'company_id' => $company->id,
            'email' => 'futuro-dono@melo.test',
            'role' => 'owner',
            'status' => 'pending',
            'token_hash' => hash('sha256', 'token-dono'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingToken($this->token($admin, $company))
            ->postJson('/api/company/invitations/'.$ownerInvite->uuid.'/resend')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não pode reenviar o convite de proprietário');

        $this->assertNull($ownerInvite->fresh()->plainToken ?? null);
        $this->assertSame(hash('sha256', 'token-dono'), $ownerInvite->fresh()->token_hash);

        $this->actingToken($this->token($admin, $company))
            ->postJson('/api/company/invitations', [
                'email' => 'suspenso@melo.test',
                'role' => 'member',
            ])->assertUnprocessable()
            ->assertJsonPath('message', 'Esta pessoa já faz parte');

        $system = User::factory()->create([
            'email' => 'logiv@logiv.test',
            'password' => 'senha1234',
            'is_system_admin' => true,
        ]);

        $resent = $this->actingToken($system->createToken('web')->plainTextToken)
            ->postJson('/api/system/companies/'.$company->uuid.'/invitations/'.$ownerInvite->uuid.'/resend')
            ->assertOk();

        $this->assertNotEmpty($resent->json('data.invite_url'));
    }

    public function test_aceite_nao_rebaixa_nem_reativa_quem_ja_faz_parte(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        $owner = User::factory()->create(['email' => 'dona@melo.test', 'password' => 'senha1234']);
        $owner->companies()->attach($company->id, ['role' => 'owner', 'status' => 'active']);

        $old = Invitation::query()->create([
            'company_id' => $company->id,
            'email' => 'dona@melo.test',
            'role' => 'member',
            'status' => 'pending',
            'token_hash' => hash('sha256', 'token-antigo'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingToken($owner->createToken('web')->plainTextToken)
            ->postJson('/api/auth/invitations/token-antigo/accept')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta pessoa já faz parte');

        $this->assertSame('owner', CompanyUser::query()->where('user_id', $owner->id)->value('role'));
        $this->assertSame('pending', $old->fresh()->status);

        $suspended = User::factory()->create(['email' => 'suspenso@melo.test', 'password' => 'senha1234']);
        $suspended->companies()->attach($company->id, ['role' => 'admin', 'status' => 'suspended']);
        Invitation::query()->create([
            'company_id' => $company->id,
            'email' => 'suspenso@melo.test',
            'role' => 'member',
            'status' => 'pending',
            'token_hash' => hash('sha256', 'token-suspenso'),
            'expires_at' => now()->addDay(),
        ]);

        $this->actingToken($suspended->createToken('web')->plainTextToken)
            ->postJson('/api/auth/invitations/token-suspenso/accept')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Esta pessoa já faz parte');

        $this->assertSame('suspended', CompanyUser::query()->where('user_id', $suspended->id)->value('status'));
        $this->assertSame('admin', CompanyUser::query()->where('user_id', $suspended->id)->value('role'));
    }

    public function test_promove_um_proprietario_e_remove_o_admin_da_logiv(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Legado LTDA',
            'trade_name' => 'Legado',
            'cnpj' => '11222333000181',
        ]);
        $kept = Company::query()->create([
            'legal_name' => 'Com Dono LTDA',
            'trade_name' => 'Com Dono',
            'cnpj' => '00000000000191',
        ]);
        $system = User::factory()->create(['email' => 'logiv@logiv.test', 'is_system_admin' => true]);
        $staff = User::factory()->create(['email' => 'staff@legado.test']);
        $owner = User::factory()->create(['email' => 'dona@kept.test']);

        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $system->id,
            'role' => 'admin',
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $staff->id,
            'role' => 'member',
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $kept->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $kept->id,
            'user_id' => $system->id,
            'role' => 'admin',
            'status' => 'active',
        ]);

        $repair = app(CompanyOwnershipRepair::class);
        $repair->run();
        $repair->run();

        $this->assertDatabaseMissing('company_user', ['user_id' => $system->id]);
        $this->assertSame('owner', CompanyUser::query()->where('user_id', $staff->id)->value('role'));
        $this->assertSame('owner', CompanyUser::query()->where('user_id', $owner->id)->value('role'));
        $this->assertSame(1, CompanyUser::query()->where('company_id', $company->id)->where('role', 'owner')->count());
    }

    public function test_reparo_nao_promove_suspenso_removido_nem_empresa_so_da_logiv(): void
    {
        $warnings = [];
        Log::listen(function ($log) use (&$warnings) {
            if ($log->level === 'warning') {
                $warnings[] = $log;
            }
        });

        $stuck = Company::query()->create([
            'legal_name' => 'Parada LTDA',
            'trade_name' => 'Parada',
            'cnpj' => '00000000000272',
        ]);
        $onlyLogiv = Company::query()->create([
            'legal_name' => 'So Logiv LTDA',
            'trade_name' => 'So Logiv',
            'cnpj' => '00000000000353',
        ]);
        $mixed = Company::query()->create([
            'legal_name' => 'Mista LTDA',
            'trade_name' => 'Mista',
            'cnpj' => '00000000000434',
        ]);

        $suspended = User::factory()->create(['email' => 'suspenso@melo.test']);
        $removed = User::factory()->create(['email' => 'removido@melo.test']);
        $system = User::factory()->create(['email' => 'logiv.reparo@logiv.test', 'is_system_admin' => true]);
        $member = User::factory()->create(['email' => 'ativo@melo.test']);

        $suspendedLink = CompanyUser::query()->create([
            'company_id' => $stuck->id,
            'user_id' => $suspended->id,
            'role' => 'admin',
            'status' => 'suspended',
        ]);
        $removedLink = CompanyUser::query()->create([
            'company_id' => $stuck->id,
            'user_id' => $removed->id,
            'role' => 'member',
            'status' => 'active',
        ]);
        $removedLink->delete();

        CompanyUser::query()->create([
            'company_id' => $onlyLogiv->id,
            'user_id' => $system->id,
            'role' => 'admin',
            'status' => 'active',
        ]);
        CompanyUser::query()->create([
            'company_id' => $mixed->id,
            'user_id' => $suspended->id,
            'role' => 'admin',
            'status' => 'suspended',
        ]);
        CompanyUser::query()->create([
            'company_id' => $mixed->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        app(CompanyOwnershipRepair::class)->run();

        $suspendedLink->refresh();
        $this->assertSame('admin', $suspendedLink->role);
        $this->assertSame('suspended', $suspendedLink->status);
        $this->assertNull($suspendedLink->deleted_at);

        $removedLink = CompanyUser::withTrashed()->find($removedLink->id);
        $this->assertNotNull($removedLink->deleted_at);
        $this->assertSame('member', $removedLink->role);
        $this->assertSame(0, CompanyUser::query()->where('company_id', $stuck->id)->where('role', 'owner')->count());

        $this->assertDatabaseMissing('company_user', ['company_id' => $onlyLogiv->id]);
        $this->assertSame('owner', CompanyUser::query()->where('company_id', $mixed->id)->where('user_id', $member->id)->value('role'));
        $this->assertSame('suspended', CompanyUser::query()->where('company_id', $mixed->id)->where('user_id', $suspended->id)->value('status'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'company.owner_missing',
            'company_id' => $stuck->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'company.owner_missing',
            'company_id' => $onlyLogiv->id,
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'company.owner_missing',
            'company_id' => $mixed->id,
        ]);
        $loggedIds = array_map(fn ($log) => $log->context['company_id'] ?? null, $warnings);
        $this->assertContains($stuck->id, $loggedIds);
        $this->assertContains($onlyLogiv->id, $loggedIds);
    }

    public function test_negacao_padrao_responde_em_portugues(): void
    {
        Route::get('/api/negacao-padrao', function () {
            throw new AuthorizationException;
        });

        $this->getJson('/api/negacao-padrao')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não tem permissão para esta ação.');
    }

    public function test_down_do_vinculo_recupera_o_cnpj_do_posto(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        $station = FuelStation::query()->create([
            'cnpj' => '19131243000197',
            'corporate_name' => 'Posto LTDA',
            'status' => 'active',
        ]);
        CompanyFuelStation::query()->create([
            'company_id' => $company->id,
            'fuel_station_id' => $station->id,
            'status' => 'active',
        ]);

        $migration = require database_path('migrations/2026_10_26_120300_use_station_on_company_fuel_station_table.php');

        try {
            $migration->down();
            $this->assertSame('19131243000197', DB::table('company_fuel_station')->value('cnpj'));
        } finally {
            if (! DB::getSchemaBuilder()->hasColumn('company_fuel_station', 'fuel_station_id')) {
                $migration->up();
            } elseif (! DB::getSchemaBuilder()->hasColumn('company_fuel_station', 'uuid')) {
                $migration->up();
            }
        }
    }

    public function test_convite_exige_um_contexto_so(): void
    {
        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        $station = FuelStation::query()->create([
            'cnpj' => '19131243000197',
            'corporate_name' => 'Posto LTDA',
            'status' => 'active',
        ]);

        $this->expectException(QueryException::class);

        Invitation::query()->create([
            'company_id' => $company->id,
            'fuel_station_id' => $station->id,
            'email' => 'dois@melo.test',
            'role' => 'member',
            'status' => 'pending',
            'token_hash' => hash('sha256', 'dois-contextos'),
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_mailer_de_log_nao_sobe_em_producao(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['mail.default' => 'log']);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('MAIL_MAILER=log');
            (new AppServiceProvider($this->app))->boot();
        } finally {
            config(['mail.default' => 'array']);
            $this->app->detectEnvironment(fn () => 'testing');
        }
    }

    public function test_seeder_de_demonstracao_fica_fora_de_producao(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $seeder = new DatabaseSeeder;
        $seeder->setContainer($this->app);

        try {
            $seeder->run();
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }

        $this->assertSame(0, Company::query()->count());
        $this->assertSame(1, User::query()->count());
        $this->assertTrue((bool) User::query()->first()->is_system_admin);
    }

    public function test_rotas_que_enviam_email_tem_limite(): void
    {
        $uris = [
            'api/company/invitations',
            'api/company/invitations/{uuid}/resend',
            'api/station/invitations',
            'api/station/invitations/{uuid}/resend',
            'api/registry/fuel-stations',
            'api/registry/fuel-stations/{uuid}/resend-request',
            'api/system/companies/{uuid}/invitations',
            'api/system/companies/{uuid}/invitations/{invitation}/resend',
        ];

        foreach ($uris as $uri) {
            $route = collect(Route::getRoutes())->first(
                fn ($route) => $route->uri() === $uri && in_array('POST', $route->methods(), true),
            );

            $this->assertNotNull($route, $uri);
            $this->assertTrue(
                collect($route->gatherMiddleware())->contains(fn ($middleware) => str_starts_with((string) $middleware, 'throttle:')),
                $uri,
            );
        }
    }

    private function actingToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function token(User $user, Company $company): string
    {
        $access = $user->createToken('web');
        $access->accessToken->forceFill([
            'company_id' => $company->id,
            'fuel_station_id' => null,
        ])->save();

        return $access->plainTextToken;
    }
}
