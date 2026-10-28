<?php

namespace Tests\Feature\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\DriverDetails;
use App\Models\Registry\Entity;
use App\Models\Registry\EntityRoleAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * O cadastro de entidades inteiro, num arquivo só.
 *
 * Substitui os sete `Store*Test`, que eram o mesmo teste com um substantivo
 * trocado — espelhando os sete controllers e serviços que viraram um. O que
 * variava de verdade entre eles está aqui como data provider.
 */
class EntityTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    private const CPF = '52998224725';

    private const CNPJ = '11444777000161';

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsMemberOf();
    }

    /** @return array<string, array{EntityRole}> */
    public static function roles(): array
    {
        return collect(EntityRole::cases())
            ->mapWithKeys(fn (EntityRole $role) => [$role->value => [$role]])
            ->all();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_cria_e_le_de_volta(EntityRole $role): void
    {
        $this->postJson($this->url($role), $this->payload($role))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Fulano de Tal')
            ->assertJsonPath('data.document', self::CPF);

        $entity = Entity::query()->firstOrFail();
        $this->assertSame($this->organization->getKey(), $entity->company_id);

        $this->getJson($this->url($role).'/'.$entity->uuid)
            ->assertOk()
            ->assertJsonPath('data.name', 'Fulano de Tal');

        $this->getJson($this->url($role))
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $entity->uuid);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('roles')]
    public function test_edita_de_verdade(EntityRole $role): void
    {
        $uuid = $this->postJson($this->url($role), $this->payload($role))
            ->assertCreated()
            ->json('data.uuid');

        $this->putJson($this->url($role).'/'.$uuid, [
            ...$this->payload($role),
            'name' => 'Nome Corrigido',
            'trade_name' => 'Apelido Novo',
        ])->assertOk();

        $entity = Entity::query()->firstOrFail();
        $this->assertSame('Nome Corrigido', $entity->name);
        $this->assertSame('Apelido Novo', $entity->trade_name);
    }

    public function test_o_mesmo_documento_acumula_papeis_numa_entidade_so(): void
    {
        $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver))
            ->assertCreated();

        $this->postJson($this->url(EntityRole::Employee), $this->payload(EntityRole::Employee))
            ->assertCreated();

        $this->assertSame(1, Entity::query()->count());
        $this->assertSame(2, EntityRoleAssignment::query()->count());

        // E cada tela só enxerga o próprio papel.
        $this->getJson($this->url(EntityRole::Customer))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_so_o_motorista_guarda_cnh(): void
    {
        $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver))
            ->assertCreated();

        $details = DriverDetails::query()->firstOrFail();
        $this->assertSame('12345678900', $details->license_number);
        $this->assertSame('AB', $details->license_category);
        $this->assertSame('SP', $details->license_issued_state);

        $this->postJson($this->url(EntityRole::Supplier), $this->payload(EntityRole::Supplier))
            ->assertCreated();

        $this->assertSame(1, DriverDetails::query()->count());
    }

    public function test_cnh_e_obrigatoria_so_para_motorista(): void
    {
        $semCnh = collect($this->payload(EntityRole::Driver))
            ->except(['license_number', 'license_category'])
            ->all();

        $this->postJson($this->url(EntityRole::Driver), $semCnh)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_number', 'license_category']);

        $this->postJson($this->url(EntityRole::Employee), $semCnh)
            ->assertCreated();
    }

    public function test_documento_invalido_e_recusado(): void
    {
        $this->postJson($this->url(EntityRole::Customer), [
            ...$this->payload(EntityRole::Customer),
            'document' => '11111111111',
        ])->assertUnprocessable()->assertJsonValidationErrors('document');
    }

    public function test_cnpj_exige_pessoa_juridica(): void
    {
        $this->postJson($this->url(EntityRole::Carrier), [
            ...$this->payload(EntityRole::Carrier),
            'kind' => Entity::ORGANIZATION,
            'document' => self::CNPJ,
            'name' => 'Transportadora Exemplo LTDA',
        ])->assertCreated();

        $this->assertSame(self::CNPJ, Entity::query()->value('document'));
    }

    public function test_desativa_e_reativa(): void
    {
        $uuid = $this->postJson($this->url(EntityRole::Supplier), $this->payload(EntityRole::Supplier))
            ->json('data.uuid');

        $this->postJson($this->url(EntityRole::Supplier)."/{$uuid}/deactivate")->assertOk();
        $this->assertSame('inactive', EntityRoleAssignment::query()->value('status'));

        $this->postJson($this->url(EntityRole::Supplier)."/{$uuid}/activate")->assertOk();
        $this->assertSame('active', EntityRoleAssignment::query()->value('status'));
    }

    public function test_cada_empresa_so_ve_o_proprio_cadastro(): void
    {
        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->assertCreated();

        $uuid = Entity::query()->value('uuid');

        // Outra empresa, outro usuário: o mesmo uuid não existe para ela.
        $this->actingAsMemberOf($this->otherCompany());

        $this->getJson($this->url(EntityRole::Customer))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson($this->url(EntityRole::Customer).'/'.$uuid)->assertNotFound();
    }

    public function test_o_mesmo_documento_pode_existir_em_duas_empresas(): void
    {
        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->assertCreated();

        $this->actingAsMemberOf($this->otherCompany());

        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->assertCreated();

        // Sem tirar o escopo, a contagem enxergaria só a empresa ativa — e
        // passaria mesmo se o cadastro fosse um só, compartilhado.
        $this->assertSame(2, Entity::query()->withoutGlobalScope('company')->count());
    }

    /**
     * Cadastrar o mesmo documento no mesmo papel é recusado, e com o nome de
     * quem já está lá. Sobrescrever em silêncio trocaria o nome de um
     * cliente existente por engano de digitação, sem ninguém ver.
     */
    public function test_documento_repetido_no_mesmo_papel_e_recusado(): void
    {
        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->assertCreated();

        $this->postJson($this->url(EntityRole::Customer), [
            ...$this->payload(EntityRole::Customer),
            'name' => 'Outro Nome',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Fulano de Tal já está cadastrado como cliente');

        $this->assertSame(1, Entity::query()->count());
        $this->assertSame('Fulano de Tal', Entity::query()->value('name'));
    }

    /**
     * O mesmo documento em papel diferente é a mesma pessoa: o cadastro é
     * reaproveitado e os dados enviados atualizam o que já havia.
     */
    public function test_documento_repetido_em_outro_papel_reaproveita_o_cadastro(): void
    {
        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->assertCreated();

        $this->postJson($this->url(EntityRole::Supplier), [
            ...$this->payload(EntityRole::Supplier),
            'name' => 'Nome Corrigido',
        ])->assertCreated();

        $this->assertSame(1, Entity::query()->count());
        $this->assertSame('Nome Corrigido', Entity::query()->value('name'));
    }

    public function test_papel_desconhecido_na_rota_e_404(): void
    {
        $this->getJson('/api/registry/entities/motoqueiros')->assertNotFound();
    }

    public function test_sem_organizacao_selecionada_nao_passa(): void
    {
        $this->actingAsSystemAdmin();

        $this->getJson($this->url(EntityRole::Customer))
            ->assertStatus(409)
            ->assertJsonPath('code', 'organization_not_selected');
    }

    // ================================ HELPERS ================================

    /**
     * A API recebe o valor do enum; o slug em português é da rota do client.
     * O binding implícito do Laravel resolve `EntityRole` por `tryFrom`, que
     * só conhece o valor — um slug aqui devolveria 404, não o recurso.
     */
    private function url(EntityRole $role): string
    {
        return '/api/registry/entities/'.$role->value;
    }

    /** @return array<string, mixed> */
    private function payload(EntityRole $role): array
    {
        $base = [
            'kind' => Entity::INDIVIDUAL,
            'document' => self::CPF,
            'name' => 'Fulano de Tal',
            'email' => 'fulano@example.com',
            'phone' => '11988887777',
        ];

        if ($role !== EntityRole::Driver) {
            return $base;
        }

        return [
            ...$base,
            'license_number' => '12345678900',
            'license_category' => 'AB',
            'license_expires_on' => '2030-01-15',
            'license_issued_state' => 'sp',
        ];
    }
}
