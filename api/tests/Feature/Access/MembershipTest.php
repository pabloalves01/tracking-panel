<?php

namespace Tests\Feature\Access;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\FuelStation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_ganha_uuid(): void
    {
        $user = $this->user();

        $this->assertTrue(\Illuminate\Support\Str::isUuid($user->uuid));
    }

    public function test_contextos_mostram_o_papel(): void
    {
        $user = $this->user();
        $company = $this->company();
        $user->companies()->attach($company->id, ['role' => 'member']);
        $station = $this->station();
        $user->fuelStations()->attach($station->id, ['role' => 'admin']);

        $token = $this->login($user)['token'];

        $this->withToken($token)->getJson('/api/auth/contexts')
            ->assertOk()
            ->assertJsonPath('companies.0.role', 'member')
            ->assertJsonPath('fuel_stations.0.role', 'admin');
    }

    public function test_empresa_inativa_some_do_login_e_dos_contextos(): void
    {
        $user = $this->user();
        $company = $this->company();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $company->update(['status' => 'inactive']);

        $response = $this->login($user);

        $this->assertSame([], $response['companies']);
        $this->assertFalse($response['user']['has_company']);

        $this->withToken($response['token'])->getJson('/api/auth/contexts')
            ->assertOk()
            ->assertJsonCount(0, 'companies');

        $this->withToken($response['token'])->postJson('/api/auth/company', ['uuid' => $company->uuid])
            ->assertForbidden();
    }

    public function test_empresa_desativada_bloqueia_o_token_que_ja_estava_nela(): void
    {
        $user = $this->user();
        $company = $this->company();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $token = $this->login($user)['token'];

        $this->withToken($token)->getJson('/api/registry/entities')->assertOk();

        $company->update(['status' => 'inactive']);

        $this->withToken($token)->getJson('/api/registry/entities')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não tem acesso a esta empresa')
            ->assertJsonPath('code', 'context_access_lost');
        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.has_company', false);
    }

    public function test_vinculo_removido_perde_o_acesso_na_hora(): void
    {
        $user = $this->user();
        $company = $this->company();
        $user->companies()->attach($company->id, ['role' => 'admin']);
        $token = $this->login($user)['token'];

        CompanyUser::query()->where('user_id', $user->id)->delete();

        $this->withToken($token)->getJson('/api/registry/entities')
            ->assertForbidden()
            ->assertJsonPath('message', 'Você não tem acesso a esta empresa')
            ->assertJsonPath('code', 'context_access_lost');
    }

    public function test_empresa_nao_repete_o_mesmo_usuario(): void
    {
        $user = $this->user();
        $company = $this->company();
        $user->companies()->attach($company->id, ['role' => 'admin']);

        try {
            DB::transaction(fn () => CompanyUser::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => 'member',
            ]));
            $this->fail('A empresa aceitou o mesmo usuário duas vezes.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('company_user', $exception->getMessage());
        }

        $this->assertSame(1, CompanyUser::query()->count());
    }

    public function test_admin_da_logiv_sem_vinculo_nao_aparece_na_contagem_de_usuarios(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@logiv.com.br',
            'is_system_admin' => true,
        ]);
        $company = $this->company();
        $member = $this->user();
        $member->companies()->attach($company->id, ['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/system/companies')
            ->assertOk()
            ->assertJsonPath('data.0.users_count', 1);

        $this->assertSame(0, CompanyUser::query()->where('user_id', $admin->id)->count());
    }

    public function test_login_limita_a_20_tentativas_por_minuto(): void
    {
        $this->user();

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'pessoa@empresa.com',
                'password' => 'errada',
            ])->assertUnauthorized()
                ->assertJsonPath('message', 'E-mail ou senha inválidos.');
        }

        $this->postJson('/api/auth/login', [
            'email' => 'pessoa@empresa.com',
            'password' => 'senha-atual',
        ])->assertStatus(429)
            ->assertJsonPath('message', 'Muitas tentativas. Aguarde um minuto e tente de novo.');
    }

    public function test_login_com_email_inexistente_tem_a_mesma_mensagem(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'ninguem@empresa.com',
            'password' => 'qualquer',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'E-mail ou senha inválidos.');
    }

    public function test_login_com_email_em_array_nao_quebra(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => ['a@b.com'],
            'password' => ['x'],
        ])->assertUnprocessable();
    }

    private function user(string $email = 'pessoa@empresa.com'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => 'senha-atual',
        ]);
    }

    private function company(string $cnpj = '00000000000191'): Company
    {
        return Company::query()->create([
            'legal_name' => 'Transportes X LTDA',
            'trade_name' => 'Transportes X',
            'cnpj' => $cnpj,
        ]);
    }

    private function station(string $cnpj = '19131243000197'): FuelStation
    {
        return FuelStation::query()->create([
            'cnpj' => $cnpj,
            'corporate_name' => 'POSTO EXEMPLO LTDA',
            'trade_name' => 'Posto Exemplo',
            'status' => 'active',
        ]);
    }

    private function login(User $user): array
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'senha-atual',
        ])->assertOk();

        // O login autentica a sessão de teste. Sem sair, o Sanctum ignora o bearer.
        auth()->logout();
        $this->app['session']->save();
        $this->app['auth']->forgetGuards();

        return $response->json();
    }
}
