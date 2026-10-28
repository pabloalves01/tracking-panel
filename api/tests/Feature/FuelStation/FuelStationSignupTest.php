<?php

namespace Tests\Feature\FuelStation;

use App\Enums\OrganizationType;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * O cadastro público de um posto.
 *
 * O posto nasce como `Organization` do tipo `fuel_station` e quem cadastrou
 * vira o primeiro membro — não há mais `FuelStation`, `FuelStationUser` nem
 * papel `owner`.
 */
class FuelStationSignupTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    private const CNPJ = '19131243000197';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->fakeReceita();
    }

    // ================================ CONSULTA ================================

    public function test_consulta_traz_os_dados_no_vocabulario_de_organizations(): void
    {
        $this->seedSaoPaulo();

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertOk()
            ->assertJsonPath('data.document', self::CNPJ)
            ->assertJsonPath('data.legal_name', 'POSTO EXEMPLO LTDA')
            ->assertJsonPath('data.trade_name', 'POSTO EXEMPLO')
            ->assertJsonPath('data.street', 'PAULISTA')
            ->assertJsonPath('data.postal_code', '01310930')
            // O nome vem da nossa tabela, não o "SAO PAULO" cru da Receita.
            ->assertJsonPath('data.city', 'São Paulo')
            ->assertJsonPath('data.state', 'SP');
    }

    /**
     * Sem a cidade carregada o cadastro continua: uma tabela de municípios
     * incompleta não pode impedir alguém de se cadastrar.
     */
    public function test_consulta_sem_a_cidade_cadastrada_usa_o_nome_da_receita(): void
    {
        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertOk()
            ->assertJsonPath('data.city', 'SAO PAULO')
            ->assertJsonPath('data.state', 'SP')
            ->assertJsonPath('data.city_id', null);
    }

    public function test_cnpj_invalido_nao_chega_a_consultar_a_receita(): void
    {
        $this->getJson('/api/auth/station-signup/cnpj/123')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Informe um CNPJ válido');

        Http::assertNothingSent();
    }

    public function test_cnpj_ja_cadastrado_nao_consulta(): void
    {
        Organization::factory()->fuelStation()->create(['document' => self::CNPJ]);

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe um cadastro com este CNPJ');
    }

    public function test_brasilapi_nao_encontra_o_cnpj(): void
    {
        $this->fakeReceita([], 404);

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'CNPJ não encontrado na Receita Federal');
    }

    public function test_brasilapi_fora_do_ar_responde_503(): void
    {
        $this->resetHttp();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->getJson('/api/auth/station-signup/cnpj/'.self::CNPJ)
            ->assertStatus(503)
            ->assertJsonPath('message', 'A consulta de CNPJ está indisponível. Tente novamente em instantes');
    }

    // ================================ CADASTRO ================================

    public function test_cadastro_cria_posto_usuario_e_vinculo(): void
    {
        [$state, $city] = $this->seedSaoPaulo();

        $this->registerStation();

        $station = Organization::query()->sole();
        $user = User::query()->sole();

        $this->assertSame(OrganizationType::FuelStation, $station->type);
        $this->assertSame(self::CNPJ, $station->document);
        $this->assertSame('POSTO EXEMPLO LTDA', $station->legal_name);
        $this->assertSame($city->id, $station->city_id);
        $this->assertSame($state->id, $station->state_id);
        $this->assertNotNull($station->receita_checked_at);

        $this->assertSame('Dona do Posto', $user->name);
        $this->assertTrue(Hash::check('senha1234', $user->password));
        $this->assertNotNull($user->email_verified_at);

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $station->getKey(),
            'user_id' => $user->getKey(),
            'status' => Membership::ACTIVE,
        ]);
    }

    public function test_o_codigo_errado_nao_cadastra_nada(): void
    {
        $this->plantCode('nova@posto.com');

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => 'Dona do Posto',
            'email' => 'nova@posto.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => '999999',
        ])->assertUnprocessable();

        $this->assertSame(0, Organization::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_segundo_cadastro_do_mesmo_cnpj_e_recusado(): void
    {
        $this->registerStation();

        $this->plantCode('outra@posto.com');

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => 'Outra Dona',
            'email' => 'outra@posto.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => '123456',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe um cadastro com este CNPJ');

        $this->assertSame(1, Organization::query()->count());
    }

    /**
     * A consulta externa acontece antes de o código ser gasto: se a Receita
     * falhar, a pessoa não precisa pedir um código novo.
     */
    public function test_receita_fora_do_ar_nao_gasta_o_codigo(): void
    {
        $this->plantCode('nova@posto.com');

        $this->resetHttp();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => 'Dona do Posto',
            'email' => 'nova@posto.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => '123456',
        ])->assertStatus(503);

        $this->assertDatabaseHas('verification_codes', [
            'email' => 'nova@posto.com',
            'consumed_at' => null,
        ]);
        $this->assertSame(0, Organization::query()->count());
    }

    // ============================ QUEM JÁ TEM CONTA ============================

    public function test_conta_logada_cadastra_o_posto_e_entra_nele(): void
    {
        $this->seedSaoPaulo();
        $this->actingAsMemberOf();

        $this->postJson('/api/auth/stations', ['cnpj' => self::CNPJ])
            ->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado')
            ->assertJsonPath('redirect_to', '/posto');

        $station = Organization::query()
            ->where('document', self::CNPJ)
            ->sole();

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $station->getKey(),
            'user_id' => $this->member->getKey(),
            'status' => Membership::ACTIVE,
        ]);

        // A sessão já está no posto: a área da empresa fecha.
        $this->getJson('/api/station/companies')->assertOk();
        $this->getJson('/api/registry/entities')->assertForbidden();
    }

    // ================================ HELPERS ================================

    private function registerStation(string $email = 'nova@posto.com', string $name = 'Dona do Posto'): void
    {
        $this->plantCode($email);

        $this->postJson('/api/auth/station-signup', [
            'cnpj' => self::CNPJ,
            'name' => $name,
            'email' => $email,
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => '123456',
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado');
    }

    private function plantCode(string $email): void
    {
        VerificationCode::query()->create([
            'email' => $email,
            'purpose' => VerificationCode::STATION_SIGNUP,
            'code_hash' => hash('sha256', '123456'),
            'expires_at' => now()->addMinutes(10),
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function fakeReceita(array $overrides = [], int $status = 200): void
    {
        $this->resetHttp();

        Http::fake([
            'brasilapi.com.br/api/cnpj/v1/*' => Http::response($this->receitaPayload($overrides), $status),
        ]);
    }

    private function resetHttp(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function receitaPayload(array $overrides = []): array
    {
        return array_replace([
            'cnpj' => self::CNPJ,
            'razao_social' => 'POSTO EXEMPLO LTDA',
            'nome_fantasia' => 'POSTO EXEMPLO',
            'descricao_situacao_cadastral' => 'ATIVA',
            'situacao_cadastral' => 2,
            'descricao_tipo_de_logradouro' => 'AVENIDA',
            'logradouro' => 'PAULISTA',
            'numero' => '1000',
            'complemento' => 'LOJA 1',
            'bairro' => 'BELA VISTA',
            'cep' => '01310-930',
            'uf' => 'SP',
            'municipio' => 'SAO PAULO',
            'codigo_municipio_ibge' => 3550308,
        ], $overrides);
    }

    /** @return array{0: State, 1: City} */
    private function seedSaoPaulo(): array
    {
        $state = State::query()->create([
            'ibge_code' => 35,
            'name' => 'São Paulo',
            'uf' => 'SP',
            'region' => 'Sudeste',
        ]);

        $city = City::query()->create([
            'ibge_code' => 3550308,
            'state_id' => $state->id,
            'name' => 'São Paulo',
        ]);

        return [$state, $city];
    }
}
