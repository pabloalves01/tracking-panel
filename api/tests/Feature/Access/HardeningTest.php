<?php

namespace Tests\Feature\Access;

use App\Models\Company;
use App\Models\CompanyFuelStation;
use App\Models\CompanyUser;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_uma_empresa_nao_alcanca_usuarios_nem_convites_de_outra(): void
    {
        $left = $this->company('00000000000191', 'left@melo.test');
        $right = $this->company('11222333000181', 'right@melo.test');
        $rightMember = User::factory()->create(['email' => 'membro@right.test', 'password' => 'senha1234']);
        $rightMember->companies()->attach($right['company']->id, ['role' => 'member', 'status' => 'active']);

        $invite = $this->actingToken($this->companyToken($right['owner'], $right['company']))
            ->postJson('/api/company/invitations', [
                'email' => 'novo@right.test',
                'role' => 'member',
            ])->assertCreated();

        $this->actingToken($this->companyToken($left['owner'], $left['company']))
            ->patchJson('/api/company/users/'.$rightMember->uuid, ['role' => 'admin'])
            ->assertNotFound();

        $this->actingToken($this->companyToken($left['owner'], $left['company']))
            ->deleteJson('/api/company/users/'.$rightMember->uuid)
            ->assertNotFound();

        $this->actingToken($this->companyToken($left['owner'], $left['company']))
            ->deleteJson('/api/company/invitations/'.$invite->json('data.uuid'))
            ->assertNotFound();

        $this->actingToken($this->companyToken($left['owner'], $left['company']))
            ->getJson('/api/company/users')
            ->assertOk()
            ->assertJsonMissing(['email' => 'membro@right.test']);
    }

    public function test_admin_da_logiv_sem_vinculo_nao_entra_na_empresa(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        $admin = User::factory()->create([
            'email' => 'logiv@logiv.test',
            'password' => 'senha1234',
            'is_system_admin' => true,
        ]);

        $this->actingToken($admin->createToken('web')->plainTextToken)
            ->getJson('/api/company/users')
            ->assertStatus(409);

        $forged = $admin->createToken('web');
        $forged->accessToken->forceFill(['company_id' => $company['company']->id])->save();

        $this->actingToken($forged->plainTextToken)
            ->getJson('/api/company/users')
            ->assertForbidden()
            ->assertJsonPath('code', 'context_access_lost');

        $this->actingToken($admin->createToken('web')->plainTextToken)
            ->getJson('/api/system/companies/'.$company['company']->uuid.'/users')
            ->assertOk();
    }

    public function test_posto_nao_usa_rota_de_empresa_e_membro_nao_escala_papel(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        $station = $this->station('19131243000197', 'dono@posto.test');
        $member = User::factory()->create(['email' => 'membro@melo.test', 'password' => 'senha1234']);
        $member->companies()->attach($company['company']->id, ['role' => 'member', 'status' => 'active']);
        $admin = User::factory()->create(['email' => 'admin@melo.test', 'password' => 'senha1234']);
        $admin->companies()->attach($company['company']->id, ['role' => 'admin', 'status' => 'active']);

        $this->actingToken($this->stationToken($station['owner'], $station['station']))
            ->getJson('/api/company/users')
            ->assertStatus(409);

        $this->actingToken($this->companyToken($member, $company['company']))
            ->patchJson('/api/company/users/'.$company['owner']->uuid, ['role' => 'member'])
            ->assertForbidden();

        $this->actingToken($this->companyToken($admin, $company['company']))
            ->patchJson('/api/company/users/'.$admin->uuid, ['role' => 'owner'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Você não pode alterar o próprio papel');

        $this->actingToken($this->companyToken($admin, $company['company']))
            ->postJson('/api/company/invitations', [
                'email' => 'elevado@melo.test',
                'role' => 'owner',
            ])->assertUnprocessable()
            ->assertJsonPath('message', 'Papel inválido para este convite');
    }

    public function test_proprietario_que_nao_e_o_ultimo_nao_se_remove(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        $other = User::factory()->create(['email' => 'outro-dono@melo.test', 'password' => 'senha1234']);
        $other->companies()->attach($company['company']->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->deleteJson('/api/company/users/'.$company['owner']->uuid)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Você não pode remover a si mesmo');

        $this->assertSame('active', CompanyUser::query()->where('user_id', $company['owner']->id)->value('status'));
    }

    public function test_convite_cancelado_nao_volta_e_token_nao_serve_de_novo(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        $created = $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->postJson('/api/company/invitations', [
                'email' => ' Ana@Melo.TEST ',
                'role' => 'member',
            ])->assertCreated()
            ->assertJsonPath('data.email', 'ana@melo.test');

        $token = basename((string) $created->json('data.invite_url'));
        $uuid = $created->json('data.uuid');

        $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->deleteJson('/api/company/invitations/'.$uuid)
            ->assertOk();

        $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->postJson('/api/company/invitations/'.$uuid.'/resend')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este convite foi cancelado');

        $guest = User::factory()->create(['email' => 'ana@melo.test', 'password' => 'senha1234']);
        $this->actingToken($guest->createToken('web')->plainTextToken)
            ->postJson('/api/auth/invitations/'.$token.'/accept')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este convite foi cancelado');
    }

    public function test_convite_em_lote_separa_o_resultado_de_cada_email(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        User::factory()->create(['email' => 'ja-tem@melo.test', 'password' => 'senha1234']);

        $response = $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->postJson('/api/company/invitations', [
                'emails' => [' novo@melo.test ', 'ja-tem@melo.test', 'nao-e-email', 'dona@melo.test'],
                'role' => 'member',
            ])->assertOk();

        $byEmail = collect($response->json('results'))->keyBy('email');
        $this->assertTrue($byEmail['novo@melo.test']['ok']);
        $this->assertFalse($byEmail['novo@melo.test']['account_exists']);
        $this->assertSame('Convite enviado', $byEmail['novo@melo.test']['message']);
        $this->assertTrue($byEmail['ja-tem@melo.test']['ok']);
        $this->assertTrue($byEmail['ja-tem@melo.test']['account_exists']);
        $this->assertSame('Já tem conta, só vai aceitar', $byEmail['ja-tem@melo.test']['message']);
        $this->assertFalse($byEmail['nao-e-email']['ok']);
        $this->assertSame('Informe um e-mail válido', $byEmail['nao-e-email']['message']);
        $this->assertFalse($byEmail['dona@melo.test']['ok']);
        $this->assertSame('Esta pessoa já faz parte', $byEmail['dona@melo.test']['message']);
        $this->assertSame(2, Invitation::query()->count());
    }

    public function test_convites_pendentes_aparecem_no_login_e_aceitam_pelo_uuid(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        $guest = User::factory()->create(['email' => 'convidada@melo.test', 'password' => 'senha1234']);
        $created = $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->postJson('/api/company/invitations', [
                'email' => 'convidada@melo.test',
                'role' => 'member',
            ])->assertCreated();

        Auth::shouldUse('web');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        $login = $this->postJson('/api/auth/login', [
            'email' => 'convidada@melo.test',
            'password' => 'senha1234',
        ])->assertOk();

        $this->assertSame($created->json('data.uuid'), $login->json('invitations.0.uuid'));
        $this->assertArrayNotHasKey('token', $login->json('invitations.0'));
        $this->assertArrayNotHasKey('invite_url', $login->json('invitations.0'));

        $token = $login->json('token');
        Auth::guard('web')->logout();
        Auth::shouldUse('sanctum');
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $other = User::factory()->create(['email' => 'intrusa@melo.test', 'password' => 'senha1234']);

        $this->actingToken($other->createToken('web')->plainTextToken)
            ->postJson('/api/auth/pending-invitations/'.$created->json('data.uuid').'/accept')
            ->assertNotFound();

        $this->actingToken($token)
            ->postJson('/api/auth/pending-invitations/'.$created->json('data.uuid').'/accept')
            ->assertOk()
            ->assertJsonPath('message', 'Convite aceito');

        $this->actingToken($token)
            ->getJson('/api/company/users')
            ->assertForbidden();

        $this->assertTrue($guest->companies()->whereKey($company['company']->id)->exists());
    }

    public function test_recusa_do_convite_pendente_pelo_uuid(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        $guest = User::factory()->create(['email' => 'convidada@melo.test', 'password' => 'senha1234']);
        $created = $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->postJson('/api/company/invitations', [
                'email' => 'convidada@melo.test',
                'role' => 'admin',
            ])->assertCreated();

        $token = $guest->createToken('web')->plainTextToken;
        $this->actingToken($token)
            ->postJson('/api/auth/pending-invitations/'.$created->json('data.uuid').'/decline')
            ->assertOk();

        $this->assertSame('declined', Invitation::query()->first()->status);
        $this->actingToken($token)
            ->postJson('/api/auth/pending-invitations/'.$created->json('data.uuid').'/accept')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este convite foi recusado');
    }

    public function test_vinculo_recusa_transicao_invalida_e_empresa_excluida(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        $station = $this->station('19131243000197', 'dono@posto.test');

        $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->postJson('/api/registry/fuel-stations', ['cnpj' => '19.131.243/0001-97'])
            ->assertCreated();

        $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->postJson('/api/registry/fuel-stations', ['cnpj' => '19131243000197'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este CNPJ já foi solicitado');

        $this->actingToken($this->companyToken($company['owner'], $company['company']))
            ->postJson('/api/registry/fuel-stations/'.$station['station']->uuid.'/disconnect')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este vínculo não está ativo');

        $company['company']->delete();

        $this->actingToken($this->stationToken($station['owner'], $station['station']))
            ->postJson('/api/station/companies/'.$company['company']->uuid.'/accept')
            ->assertNotFound();

        $this->assertSame('request_sent', CompanyFuelStation::query()->first()->status);
    }

    public function test_encerrar_e_reenviar_so_nas_etapas_certas(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        $station = $this->station('19131243000197', 'dono@posto.test');
        $companyToken = $this->companyToken($company['owner'], $company['company']);
        $stationToken = $this->stationToken($station['owner'], $station['station']);

        $this->actingToken($companyToken)
            ->postJson('/api/registry/fuel-stations', ['cnpj' => '19131243000197'])
            ->assertCreated();

        $this->actingToken($stationToken)
            ->postJson('/api/station/companies/'.$company['company']->uuid.'/accept')
            ->assertOk();

        $this->actingToken($companyToken)
            ->deleteJson('/api/registry/fuel-stations/'.$station['station']->uuid)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Só é possível cancelar um convite pendente');

        $this->actingToken($companyToken)
            ->postJson('/api/registry/fuel-stations/'.$station['station']->uuid.'/disconnect')
            ->assertOk();

        $this->actingToken($companyToken)
            ->postJson('/api/registry/fuel-stations/'.$station['station']->uuid.'/resend-request')
            ->assertOk();

        $this->actingToken($stationToken)
            ->postJson('/api/station/companies/'.$company['company']->uuid.'/reject')
            ->assertOk();

        $station['station']->delete();

        $this->actingToken($companyToken)
            ->postJson('/api/registry/fuel-stations/'.$station['station']->uuid.'/resend-request')
            ->assertUnprocessable()
            ->assertJsonPath('errors.cnpj.0', 'Este posto não possui cadastro no Logiv');
    }

    public function test_lixo_em_cnpj_cpf_e_senha_nao_vira_erro_500(): void
    {
        $company = $this->company('00000000000191', 'dona@melo.test');
        $token = $this->companyToken($company['owner'], $company['company']);
        $huge = str_repeat('a', 400);

        $this->actingToken($token)
            ->getJson('/api/registry/fuel-stations/by-cnpj?cnpj[]=19131243000197')
            ->assertUnprocessable();

        $this->actingToken($token)
            ->postJson('/api/registry/fuel-stations', ['cnpj' => ['19131243000197']])
            ->assertUnprocessable();

        $this->actingToken($token)
            ->postJson('/api/registry/drivers', [
                'entity' => [
                    'name' => 'Motorista',
                    'person_type' => 'pf',
                    'cpf_cnpj' => ['52998224725'],
                ],
                'license_number' => '123',
                'license_category' => 'B',
                'license_issued_state' => ['SP'],
            ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'email' => ['dona@melo.test'],
            'password' => [$huge],
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', [
            'email' => 'dona@melo.test',
            'password' => $huge,
        ])->assertUnprocessable();

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => ['19131243000197'],
            'name' => $huge,
            'email' => ['nao@posto.test'],
            'password' => $huge,
            'password_confirmation' => $huge,
            'code' => ['123456'],
        ])->assertUnprocessable();

        $this->postJson('/api/auth/verification-codes', [
            'email' => ['nao@posto.test'],
            'purpose' => ['station_signup'],
        ])->assertUnprocessable();

        $this->postJson('/api/auth/password/forgot', [
            'email' => ['dona@melo.test'],
        ])->assertUnprocessable();

        $this->postJson('/api/auth/password/reset', [
            'email' => ['dona@melo.test'],
            'code' => ['123456'],
            'password' => $huge,
            'password_confirmation' => $huge,
        ])->assertUnprocessable();

        $this->getJson('/api/auth/invitations/'.str_repeat('x', 80))->assertNotFound();
        $this->postJson('/api/auth/invitations/nao-existe/decline')->assertNotFound();
        $this->getJson('/api/auth/station-signup/cnpj/123')->assertUnprocessable();

        $user = User::factory()->create(['email' => 'livre@melo.test', 'password' => 'senha1234']);
        $plain = $user->createToken('web')->plainTextToken;

        $this->actingToken($plain)
            ->postJson('/api/auth/company', ['uuid' => ['nao-uuid']])
            ->assertUnprocessable();

        $this->actingToken($plain)
            ->postJson('/api/auth/station', ['uuid' => ['nao-uuid']])
            ->assertUnprocessable();

        $this->actingToken($token)
            ->patchJson('/api/company/users/'.$company['owner']->uuid, [
                'role' => ['owner'],
                'is_system_admin' => true,
            ])->assertUnprocessable();

        $this->assertFalse($company['owner']->fresh()->is_system_admin);
    }

    public function test_login_e_consulta_de_convite_avisam_o_tempo_do_limite(): void
    {
        User::factory()->create(['email' => 'dona@melo.test', 'password' => 'senha1234']);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/auth/login', [
                'email' => 'dona@melo.test',
                'password' => 'errada',
            ])->assertUnauthorized();
        }

        $limited = $this->postJson('/api/auth/login', [
            'email' => 'dona@melo.test',
            'password' => 'errada',
        ])->assertStatus(429)
            ->assertJsonStructure(['retry_after']);

        $this->assertStringContainsString('segundos', (string) $limited->json('message'));

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->getJson('/api/auth/invitations/token-inexistente')->assertNotFound();
        }

        $this->getJson('/api/auth/invitations/token-inexistente')
            ->assertStatus(429)
            ->assertJsonStructure(['retry_after']);
    }

    /**
     * @return array{company: Company, owner: User}
     */
    private function company(string $cnpj, string $email): array
    {
        $company = Company::query()->create([
            'legal_name' => 'Empresa '.$cnpj,
            'trade_name' => 'Empresa',
            'cnpj' => $cnpj,
        ]);
        $owner = User::factory()->create([
            'email' => $email,
            'password' => 'senha1234',
        ]);
        $owner->companies()->attach($company->id, ['role' => 'owner', 'status' => 'active']);

        return ['company' => $company, 'owner' => $owner];
    }

    /**
     * @return array{station: FuelStation, owner: User}
     */
    private function station(string $cnpj, string $email): array
    {
        $station = FuelStation::query()->create([
            'cnpj' => $cnpj,
            'corporate_name' => 'Posto LTDA',
            'trade_name' => 'Posto',
            'status' => 'active',
        ]);
        $owner = User::factory()->create([
            'email' => $email,
            'password' => 'senha1234',
        ]);
        FuelStationUser::query()->create([
            'fuel_station_id' => $station->id,
            'user_id' => $owner->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        return ['station' => $station, 'owner' => $owner];
    }

    private function actingToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function companyToken(User $user, Company $company): string
    {
        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'company_id' => $company->id,
            'fuel_station_id' => null,
        ])->save();

        return $token->plainTextToken;
    }

    private function stationToken(User $user, FuelStation $station): string
    {
        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'company_id' => null,
            'fuel_station_id' => $station->id,
        ])->save();

        return $token->plainTextToken;
    }
}
