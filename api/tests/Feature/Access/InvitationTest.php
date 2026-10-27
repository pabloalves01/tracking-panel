<?php

namespace Tests\Feature\Access;

use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Invitation;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_convite_cria_conta_e_entra_na_empresa(): void
    {
        [$token] = $this->companyInvite('dona@melo.test');
        $this->plantCode('dona@melo.test', VerificationCode::INVITATION_REGISTER);

        $this->postJson('/api/auth/invitations/'.$token.'/register', [
            'name' => 'Dona',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => '123456',
            'is_system_admin' => true,
        ])->assertCreated()
            ->assertJsonPath('user.email', 'dona@melo.test')
            ->assertJsonPath('user.is_system_admin', false)
            ->assertJsonPath('user.current_role', 'owner')
            ->assertJsonPath('user.has_company', true);

        $this->assertNotNull(User::query()->where('email', 'dona@melo.test')->first()->email_verified_at);
        $this->assertSame(1, CompanyUser::query()->where('role', 'owner')->where('status', 'active')->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.accepted']);
    }

    public function test_conta_existente_aceita_e_outro_email_nao(): void
    {
        [$token, $company] = $this->companyInvite('dona@melo.test');
        $owner = User::factory()->create([
            'email' => 'dona@melo.test',
            'password' => 'senha1234',
        ]);
        $other = User::factory()->create([
            'email' => 'outro@melo.test',
            'password' => 'senha1234',
        ]);

        $this->actingToken($other->createToken('web')->plainTextToken)
            ->postJson('/api/auth/invitations/'.$token.'/accept')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este convite é para outro e-mail');

        $this->actingToken($owner->createToken('web')->plainTextToken)
            ->postJson('/api/auth/invitations/'.$token.'/accept')
            ->assertOk()
            ->assertJsonPath('message', 'Convite aceito');

        $this->assertTrue($owner->companies()->whereKey($company->id)->exists());

        $this->actingToken($owner->createToken('web')->plainTextToken)
            ->postJson('/api/auth/invitations/'.$token.'/accept')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este convite já foi aceito');
    }

    public function test_recusa_reenvio_e_token_antigo(): void
    {
        [$token, $company] = $this->companyInvite('dona@melo.test');

        $this->postJson('/api/auth/invitations/'.$token.'/decline')
            ->assertOk()
            ->assertJsonPath('message', 'Convite recusado');

        $this->getJson('/api/auth/invitations/'.$token)
            ->assertOk()
            ->assertJsonPath('status', 'declined');

        $admin = User::query()->where('is_system_admin', true)->first();
        $invitation = Invitation::query()->first();
        $resent = $this->actingToken($this->systemToken($admin))
            ->postJson('/api/system/companies/'.$company->uuid.'/invitations/'.$invitation->uuid.'/resend')
            ->assertOk();

        $newToken = basename((string) $resent->json('data.invite_url'));
        $this->assertNotSame($token, $newToken);

        $this->getJson('/api/auth/invitations/'.$token)->assertNotFound();
        $this->getJson('/api/auth/invitations/'.$newToken)
            ->assertOk()
            ->assertJsonPath('status', 'pending');
    }

    public function test_convite_expirado_nao_e_aceito(): void
    {
        [$token] = $this->companyInvite('dona@melo.test');
        Invitation::query()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/auth/invitations/'.$token.'/register', [
            'name' => 'Dona',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Este convite expirou');

        $this->assertSame('expired', Invitation::query()->first()->status);
        $this->assertSame(1, User::query()->count());
    }

    public function test_convite_repetido_e_papel_invalido(): void
    {
        [, $company] = $this->companyInvite('dona@melo.test');
        $admin = User::query()->where('is_system_admin', true)->first();

        $this->actingToken($this->systemToken($admin))
            ->postJson('/api/system/companies/'.$company->uuid.'/invitations', [
                'email' => 'dona@melo.test',
                'role' => 'owner',
            ])->assertUnprocessable()
            ->assertJsonPath('message', 'Já existe um convite pendente para este e-mail');

        $this->actingToken($this->systemToken($admin))
            ->postJson('/api/system/companies/'.$company->uuid.'/invitations', [
                'email' => 'nova@melo.test',
                'role' => 'member',
            ])->assertUnprocessable()
            ->assertJsonPath('errors.role.0', 'A Logiv convida proprietário ou administrador');
    }

    public function test_admin_convida_membro_e_membro_nao_convida(): void
    {
        $company = $this->companyWith('owner@melo.test', 'owner');
        $admin = User::factory()->create(['email' => 'admin@melo.test', 'password' => 'senha1234']);
        $admin->companies()->attach($company->id, ['role' => 'admin', 'status' => 'active']);
        $member = User::factory()->create(['email' => 'membro@melo.test', 'password' => 'senha1234']);
        $member->companies()->attach($company->id, ['role' => 'member', 'status' => 'active']);

        $this->actingToken($this->companyToken($member, $company))
            ->postJson('/api/company/invitations', [
                'email' => 'alguem@melo.test',
                'role' => 'member',
            ])->assertForbidden();

        $this->actingToken($this->companyToken($admin, $company))
            ->postJson('/api/company/invitations', [
                'email' => 'Alguem@melo.test',
                'role' => 'owner',
            ])->assertUnprocessable();

        $created = $this->actingToken($this->companyToken($admin, $company))
            ->postJson('/api/company/invitations', [
                'email' => ' Alguem@melo.test ',
                'role' => 'member',
            ])->assertCreated()
            ->assertJsonPath('data.email', 'alguem@melo.test');

        $this->assertNotEmpty($created->json('data.invite_url'));

        $this->actingToken($this->companyToken($admin, $company))
            ->deleteJson('/api/company/invitations/'.$created->json('data.uuid'))
            ->assertOk();

        $this->assertSame('revoked', Invitation::query()->first()->status);
    }

    public function test_posto_nao_ve_convite_de_outro_posto(): void
    {
        $station = $this->stationWith('dono@posto.test');
        $other = $this->stationWith('outro@posto.test', '34028316000103');
        $owner = User::query()->where('email', 'dono@posto.test')->first();

        $created = $this->actingToken($this->stationToken($owner, $station))
            ->postJson('/api/station/invitations', [
                'email' => 'equipe@posto.test',
                'role' => 'member',
            ])->assertCreated();

        $otherOwner = User::query()->where('email', 'outro@posto.test')->first();
        $this->actingToken($this->stationToken($otherOwner, $other))
            ->getJson('/api/station/invitations')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingToken($this->stationToken($otherOwner, $other))
            ->deleteJson('/api/station/invitations/'.$created->json('data.uuid'))
            ->assertNotFound();
    }

    public function test_aceite_reativa_membro_suspenso(): void
    {
        $company = $this->companyWith('owner@melo.test', 'owner');
        $user = User::factory()->create(['email' => 'volta@melo.test', 'password' => 'senha1234']);
        CompanyUser::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => 'admin',
            'status' => 'suspended',
        ]);
        $owner = User::query()->where('email', 'owner@melo.test')->first();

        $created = $this->actingToken($this->companyToken($owner, $company))
            ->postJson('/api/company/invitations', [
                'email' => 'volta@melo.test',
                'role' => 'member',
            ])->assertCreated();

        $token = basename((string) $created->json('data.invite_url'));
        $this->actingToken($user->createToken('web')->plainTextToken)
            ->postJson('/api/auth/invitations/'.$token.'/accept')
            ->assertOk();

        $this->assertSame(1, CompanyUser::query()->where('user_id', $user->id)->count());
        $this->assertSame('member', CompanyUser::query()->where('user_id', $user->id)->value('role'));
        $this->assertSame('active', CompanyUser::query()->where('user_id', $user->id)->value('status'));
    }

    public function test_banco_exige_empresa_ou_posto_no_convite(): void
    {
        $this->expectException(QueryException::class);

        Invitation::query()->create([
            'email' => 'sem-contexto@logiv.test',
            'role' => 'member',
            'status' => 'pending',
            'token_hash' => hash('sha256', 'sem-contexto'),
            'expires_at' => now()->addDay(),
        ]);
    }

    public function test_quem_ja_e_membro_nao_recebe_outro_convite(): void
    {
        $company = $this->companyWith('owner@melo.test', 'owner');
        $owner = User::query()->where('email', 'owner@melo.test')->first();

        $this->actingToken($this->companyToken($owner, $company))
            ->postJson('/api/company/invitations', [
                'email' => 'owner@melo.test',
                'role' => 'admin',
            ])->assertUnprocessable()
            ->assertJsonPath('message', 'Esta pessoa já faz parte');
    }

    /**
     * @return array{0: string, 1: Company}
     */
    private function companyInvite(string $email): array
    {
        $admin = User::factory()->create([
            'email' => 'logiv-'.Str::uuid().'@logiv.test',
            'is_system_admin' => true,
        ]);

        $response = $this->actingToken($this->systemToken($admin))
            ->postJson('/api/system/companies', [
                'legal_name' => 'Melo LTDA',
                'trade_name' => 'Melo',
                'cnpj' => '00000000000191',
                'admin_email' => $email,
            ])->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return [
            basename((string) $response->json('invitation.invite_url')),
            Company::query()->first(),
        ];
    }

    private function companyWith(string $email, string $role): Company
    {
        $company = Company::query()->create([
            'legal_name' => 'Melo LTDA',
            'trade_name' => 'Melo',
            'cnpj' => '00000000000191',
        ]);
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'senha1234',
        ]);
        $user->companies()->attach($company->id, ['role' => $role, 'status' => 'active']);

        return $company;
    }

    private function stationWith(string $email, string $cnpj = '19131243000197'): FuelStation
    {
        $station = FuelStation::query()->create([
            'cnpj' => $cnpj,
            'corporate_name' => 'Posto LTDA',
            'trade_name' => 'Posto',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'senha1234',
        ]);
        FuelStationUser::query()->create([
            'fuel_station_id' => $station->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'status' => 'active',
        ]);

        return $station;
    }

    private function plantCode(string $email, string $purpose, string $code = '123456'): void
    {
        VerificationCode::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    private function actingToken(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function systemToken(User $user): string
    {
        return $user->createToken('web')->plainTextToken;
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
