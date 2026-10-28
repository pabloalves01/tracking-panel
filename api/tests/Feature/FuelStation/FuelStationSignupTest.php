<?php

namespace Tests\Feature\FuelStation;

use App\Enums\OrganizationType;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * O cadastro de um posto.
 *
 * A consulta do CNPJ é pública; criar o posto exige estar logado. O posto
 * nasce como `Organization` do tipo `fuel_station` e quem cadastrou vira o
 * primeiro membro. A conta, quando não existe, nasce antes — ver
 * `AccountRegistrationTest`.
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

        $this->getJson('/api/auth/fuel-station-signup/cnpj/'.self::CNPJ)
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
        $this->getJson('/api/auth/fuel-station-signup/cnpj/'.self::CNPJ)
            ->assertOk()
            ->assertJsonPath('data.city', 'SAO PAULO')
            ->assertJsonPath('data.state', 'SP')
            ->assertJsonPath('data.city_id', null);
    }

    public function test_cnpj_invalido_nao_chega_a_consultar_a_receita(): void
    {
        $this->getJson('/api/auth/fuel-station-signup/cnpj/123')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Informe um CNPJ válido');

        Http::assertNothingSent();
    }

    public function test_cnpj_ja_cadastrado_nao_consulta(): void
    {
        Organization::factory()->fuelStation()->create(['document' => self::CNPJ]);

        $this->getJson('/api/auth/fuel-station-signup/cnpj/'.self::CNPJ)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe um cadastro com este CNPJ');
    }

    public function test_brasilapi_nao_encontra_o_cnpj(): void
    {
        $this->fakeReceita([], 404);

        $this->getJson('/api/auth/fuel-station-signup/cnpj/'.self::CNPJ)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'CNPJ não encontrado na Receita Federal');
    }

    public function test_brasilapi_fora_do_ar_responde_503(): void
    {
        $this->resetHttp();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->getJson('/api/auth/fuel-station-signup/cnpj/'.self::CNPJ)
            ->assertStatus(503)
            ->assertJsonPath('message', 'A consulta de CNPJ está indisponível. Tente novamente em instantes');
    }

    // ================================ CADASTRO ================================

    public function test_cadastro_cria_o_posto_e_o_vinculo_para_quem_esta_logado(): void
    {
        [$state, $city] = $this->seedSaoPaulo();
        $this->actingAsMemberOf();
        $users = User::query()->count();

        $this->postJson('/api/auth/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $fuelStation = Organization::query()->fuelStations()->sole();

        $this->assertSame(OrganizationType::FuelStation, $fuelStation->type);
        $this->assertSame(self::CNPJ, $fuelStation->document);
        $this->assertSame('POSTO EXEMPLO LTDA', $fuelStation->legal_name);
        $this->assertSame($city->id, $fuelStation->city_id);
        $this->assertSame($state->id, $fuelStation->state_id);
        $this->assertNotNull($fuelStation->receita_checked_at);

        // Nenhuma conta nova: o posto entrou na conta de quem estava logado.
        $this->assertSame($users, User::query()->count());
        $this->assertDatabaseHas('memberships', [
            'organization_id' => $fuelStation->getKey(),
            'user_id' => $this->member->getKey(),
            'status' => Membership::ACTIVE,
        ]);
    }

    public function test_sem_login_nao_cria_posto(): void
    {
        $this->postJson('/api/auth/fuel-stations', ['cnpj' => self::CNPJ])->assertUnauthorized();

        $this->assertSame(0, Organization::query()->count());
    }

    /**
     * O endpoint que criava conta e posto juntos — e por isso pedia senha
     * antes de saber se a pessoa já tinha conta — não existe mais.
     */
    public function test_cadastro_publico_de_conta_e_posto_juntos_saiu(): void
    {
        $this->postJson('/api/auth/fuel-station-signup', [
            'cnpj' => self::CNPJ,
            'name' => 'Dona do Posto',
            'email' => 'nova@posto.com',
            'password' => 'senha1234',
            'password_confirmation' => 'senha1234',
            'code' => '123456',
        ])->assertNotFound();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Organization::query()->count());
    }

    public function test_segundo_cadastro_do_mesmo_cnpj_e_recusado(): void
    {
        $this->actingAsMemberOf();
        $this->postJson('/api/auth/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->actingAsMemberOf($this->otherCompany());
        $this->postJson('/api/auth/fuel-stations', ['cnpj' => self::CNPJ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe um cadastro com este CNPJ');

        $this->assertSame(1, Organization::query()->fuelStations()->count());
    }

    /**
     * Dois cliques passam juntos pela conferência do CNPJ; o índice único
     * barra o segundo, e a resposta é a mesma da conferência — não um 500.
     */
    public function test_cnpj_gravado_por_outro_no_meio_vira_409_e_nao_500(): void
    {
        $this->actingAsMemberOf();

        Organization::creating(function (Organization $organization) {
            if ($organization->document === self::CNPJ && ! Organization::withTrashed()->where('document', self::CNPJ)->exists()) {
                Organization::withoutEvents(fn () => Organization::factory()->fuelStation()->create(['document' => self::CNPJ]));
            }
        });

        $response = $this->postJson('/api/auth/fuel-stations', ['cnpj' => self::CNPJ]);

        $response->assertStatus(409)->assertJsonPath('message', 'Já existe um cadastro com este CNPJ');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_receita_fora_do_ar_nao_cria_nada(): void
    {
        $this->actingAsMemberOf();
        $memberships = Membership::query()->count();

        $this->resetHttp();
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->postJson('/api/auth/fuel-stations', ['cnpj' => self::CNPJ])->assertStatus(503);

        $this->assertSame(0, Organization::query()->fuelStations()->count());
        $this->assertSame($memberships, Membership::query()->count());
    }

    // ============================ QUEM JÁ TEM CONTA ============================

    public function test_conta_logada_cadastra_o_posto_e_entra_nele(): void
    {
        $this->seedSaoPaulo();
        $this->actingAsMemberOf();

        $this->postJson('/api/auth/fuel-stations', ['cnpj' => self::CNPJ])
            ->assertCreated()
            ->assertJsonPath('message', 'Posto cadastrado')
            ->assertJsonPath('redirect_to', '/posto');

        $fuelStation = Organization::query()
            ->where('document', self::CNPJ)
            ->sole();

        $this->assertDatabaseHas('memberships', [
            'organization_id' => $fuelStation->getKey(),
            'user_id' => $this->member->getKey(),
            'status' => Membership::ACTIVE,
        ]);

        // A sessão já está no posto: a área da empresa fecha.
        $this->getJson('/api/fuel-station/companies')->assertOk();
        $this->getJson('/api/registry/entities')->assertForbidden();
    }

    /**
     * O caso que motivou a mudança: quem administra uma empresa cadastra o
     * posto na mesma conta, com a mesma senha, e passa a ver as duas.
     */
    public function test_quem_administra_empresa_cadastra_o_posto_na_mesma_conta(): void
    {
        $user = User::factory()->create(['email' => 'pablo@melo.test', 'password' => 'senha-de-sempre']);
        $hash = $user->password;
        $this->actingAsMemberOf(user: $user);

        $this->postJson('/api/auth/fuel-stations', ['cnpj' => self::CNPJ])->assertCreated();

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame(1, User::query()->where('email', 'pablo@melo.test')->count());

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->postJson('/api/auth/login', [
            'email' => 'pablo@melo.test',
            'password' => 'senha-de-sempre',
        ])->assertOk()
            ->assertJsonCount(2, 'organizations');
    }

    // ================================ HELPERS ================================

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
