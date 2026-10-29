<?php

namespace Tests\Feature\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Carrier;
use App\Models\Registry\Customer;
use App\Models\Registry\Driver;
use App\Models\Registry\Employee;
use App\Models\Registry\Entity;
use App\Models\Registry\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ActsAsMember;
use Tests\TestCase;

/**
 * O cadastro de entidades: a pessoa em `entities`, cada papel na sua tabela.
 *
 * O que vale para todo papel roda para os sete pelo data provider; o que é
 * de um papel só (a CNH do motorista) tem teste próprio.
 */
class EntityTest extends TestCase
{
    use ActsAsMember;
    use RefreshDatabase;

    private const CPF = '52998224725';

    private const OTHER_CPF = '11144477735';

    private const CNPJ = '11444777000161';

    private const PATHS = [
        'driver' => 'drivers',
        'vehicle_owner' => 'vehicle-owners',
        'vehicle_responsible' => 'vehicle-responsibles',
        'employee' => 'employees',
        'supplier' => 'suppliers',
        'customer' => 'customers',
        'carrier' => 'carriers',
    ];

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

    // ============================ TODO PAPEL ============================

    #[DataProvider('roles')]
    public function test_cadastra_a_pessoa_e_o_papel_na_tabela_dele(EntityRole $role): void
    {
        $uuid = $this->postJson($this->url($role), $this->payload($role))
            ->assertCreated()
            ->assertJsonPath('data.role', $role->value)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.entity.name', 'Fulano de Tal')
            ->assertJsonPath('data.entity.document', self::CPF)
            ->json('data.uuid');

        $entity = Entity::query()->sole();
        $record = $role->model()::query()->sole();

        $this->assertSame($uuid, $record->uuid);
        $this->assertSame($entity->getKey(), $record->entity_id);
        $this->assertSame($this->organization->getKey(), $record->company_id);
        $this->assertSame($this->organization->getKey(), $entity->company_id);

        $this->getJson($this->url($role, $uuid))
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuid)
            ->assertJsonPath('data.entity.name', 'Fulano de Tal')
            ->assertJsonPath('data.entity.roles.0.uuid', $uuid);

        $this->getJson($this->url($role))
            ->assertOk()
            ->assertJsonPath('data.0.uuid', $uuid);
    }

    #[DataProvider('roles')]
    public function test_edita_a_pessoa_pelo_papel(EntityRole $role): void
    {
        $uuid = $this->postJson($this->url($role), $this->payload($role))->json('data.uuid');

        $this->putJson($this->url($role, $uuid), [
            ...$this->payload($role),
            'name' => 'Nome Corrigido',
            'trade_name' => 'Apelido Novo',
        ])
            ->assertOk()
            ->assertJsonPath('data.entity.name', 'Nome Corrigido');

        $entity = Entity::query()->sole();
        $this->assertSame('Nome Corrigido', $entity->name);
        $this->assertSame('Apelido Novo', $entity->trade_name);
    }

    #[DataProvider('roles')]
    public function test_desativa_e_reativa(EntityRole $role): void
    {
        $uuid = $this->postJson($this->url($role), $this->payload($role))->json('data.uuid');

        $this->postJson($this->url($role, $uuid).'/deactivate')->assertOk();
        $this->assertSame('inactive', $role->model()::query()->value('status'));

        // Inativo continua listado — some só de quem filtra por ativo.
        $this->getJson($this->url($role))->assertJsonCount(1, 'data');
        $this->getJson($this->url($role).'?status=active')->assertJsonCount(0, 'data');

        $this->postJson($this->url($role, $uuid).'/activate')->assertOk();
        $this->assertSame('active', $role->model()::query()->value('status'));
    }

    #[DataProvider('roles')]
    public function test_excluir_o_unico_papel_tira_a_pessoa_do_cadastro(EntityRole $role): void
    {
        $uuid = $this->postJson($this->url($role), $this->payload($role))->json('data.uuid');

        $this->deleteJson($this->url($role, $uuid))->assertOk();

        $this->getJson($this->url($role))->assertJsonCount(0, 'data');
        $this->getJson($this->url($role, $uuid))->assertNotFound();
        $this->assertSame(0, Entity::query()->count());

        // A exclusão é lógica: as linhas continuam no banco.
        $this->assertSame(1, $role->model()::withTrashed()->count());
        $this->assertSame(1, Entity::withTrashed()->count());
    }

    // ======================= A MESMA PESSOA, VÁRIOS PAPÉIS =======================

    public function test_o_mesmo_documento_e_uma_pessoa_com_varios_papeis(): void
    {
        $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver))->assertCreated();
        $this->postJson($this->url(EntityRole::Employee), $this->payload(EntityRole::Employee))->assertCreated();

        $this->assertSame(1, Entity::query()->count());
        $this->assertSame(1, Driver::query()->count());
        $this->assertSame(1, Employee::query()->count());

        // Cada lista só enxerga o próprio papel.
        $this->getJson($this->url(EntityRole::Customer))->assertJsonCount(0, 'data');

        // E a pessoa sabe dos dois, cada um com o próprio uuid.
        $this->getJson('/api/registry/entities/by-document/'.self::CPF)
            ->assertOk()
            ->assertJsonCount(2, 'data.roles')
            ->assertJsonPath('data.roles.0.role', 'driver')
            ->assertJsonPath('data.roles.0.uuid', Driver::query()->value('uuid'))
            ->assertJsonPath('data.roles.1.role', 'employee')
            ->assertJsonPath('data.roles.1.uuid', Employee::query()->value('uuid'));
    }

    public function test_cadastrar_em_outro_papel_atualiza_os_dados_da_pessoa(): void
    {
        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))->assertCreated();

        $this->postJson($this->url(EntityRole::Supplier), [
            ...$this->payload(EntityRole::Supplier),
            'name' => 'Nome Corrigido',
        ])->assertCreated();

        $this->assertSame(1, Entity::query()->count());
        $this->assertSame('Nome Corrigido', Entity::query()->value('name'));
    }

    public function test_editar_pelo_cliente_muda_o_nome_tambem_no_fornecedor(): void
    {
        $customer = $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->json('data.uuid');
        $supplier = $this->postJson($this->url(EntityRole::Supplier), $this->payload(EntityRole::Supplier))
            ->json('data.uuid');

        $this->putJson($this->url(EntityRole::Customer, $customer), [
            ...$this->payload(EntityRole::Customer),
            'name' => 'Nome Novo',
        ])->assertOk();

        $this->getJson($this->url(EntityRole::Supplier, $supplier))
            ->assertJsonPath('data.entity.name', 'Nome Novo');
    }

    public function test_o_mesmo_documento_no_mesmo_papel_e_recusado_sem_mexer_nos_dados(): void
    {
        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))->assertCreated();

        $this->postJson($this->url(EntityRole::Customer), [
            ...$this->payload(EntityRole::Customer),
            'name' => 'Outro Nome',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Fulano de Tal já está cadastrado como cliente');

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('Fulano de Tal', Entity::query()->value('name'));
    }

    public function test_excluir_um_papel_mantem_a_pessoa_e_os_outros_papeis(): void
    {
        $driver = $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver))
            ->json('data.uuid');
        $employee = $this->postJson($this->url(EntityRole::Employee), $this->payload(EntityRole::Employee))
            ->json('data.uuid');

        $this->deleteJson($this->url(EntityRole::Driver, $driver))
            ->assertOk()
            ->assertJsonPath('message', 'Motorista excluído com sucesso');

        $this->assertSame(1, Entity::query()->count());
        $this->assertSame(0, Driver::query()->count());

        $this->getJson($this->url(EntityRole::Employee, $employee))
            ->assertOk()
            ->assertJsonCount(1, 'data.entity.roles');
    }

    public function test_desativar_um_papel_nao_mexe_nos_outros(): void
    {
        $driver = $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver))
            ->json('data.uuid');
        $this->postJson($this->url(EntityRole::Employee), $this->payload(EntityRole::Employee));

        $this->postJson($this->url(EntityRole::Driver, $driver).'/deactivate')
            ->assertOk()
            ->assertJsonPath('message', 'Motorista desativado com sucesso');

        $this->assertSame('inactive', Driver::query()->value('status'));
        $this->assertSame('active', Employee::query()->value('status'));
    }

    // ============================ EXCLUIR E VOLTAR ============================

    /**
     * Quem foi excluído e volta é a mesma linha — o que a operação gravar
     * apontando para o `id` do motorista continua ligado a ele.
     */
    public function test_cadastrar_de_novo_quem_foi_excluido_restaura_a_mesma_linha(): void
    {
        $first = $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver))
            ->json('data.uuid');
        $entityUuid = Entity::query()->value('uuid');

        $this->deleteJson($this->url(EntityRole::Driver, $first))->assertOk();

        $second = $this->postJson($this->url(EntityRole::Driver), [
            ...$this->payload(EntityRole::Driver),
            'license_number' => '99999999999',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.license_number', '99999999999')
            ->json('data.uuid');

        $this->assertSame($first, $second);
        $this->assertSame($entityUuid, Entity::query()->value('uuid'));
        $this->assertSame(1, Driver::withTrashed()->count());
        $this->assertSame(1, Entity::withTrashed()->count());
    }

    public function test_quem_volta_inativo_volta_ativo(): void
    {
        $uuid = $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->json('data.uuid');

        $this->postJson($this->url(EntityRole::Customer, $uuid).'/deactivate');
        $this->deleteJson($this->url(EntityRole::Customer, $uuid));

        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->assertCreated()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_voltar_em_outro_papel_restaura_a_pessoa_sem_os_papeis_antigos(): void
    {
        $uuid = $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->json('data.uuid');
        $entityUuid = Entity::query()->value('uuid');

        $this->deleteJson($this->url(EntityRole::Customer, $uuid));

        $this->postJson($this->url(EntityRole::Supplier), $this->payload(EntityRole::Supplier))
            ->assertCreated()
            ->assertJsonPath('data.entity.uuid', $entityUuid)
            ->assertJsonCount(1, 'data.entity.roles')
            ->assertJsonPath('data.entity.roles.0.role', 'supplier');

        $this->assertSame(0, Customer::query()->count());
    }

    public function test_excluir_a_pessoa_tira_todos_os_papeis(): void
    {
        $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver));
        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer));
        $entityUuid = Entity::query()->value('uuid');

        $this->deleteJson('/api/registry/entities/'.$entityUuid)
            ->assertOk()
            ->assertJsonPath('message', 'Cadastro excluído com sucesso');

        $this->assertSame(0, Entity::query()->count());
        $this->assertSame(0, Driver::query()->count());
        $this->assertSame(0, Customer::query()->count());
        $this->getJson('/api/registry/entities')->assertJsonCount(0, 'data');
    }

    // ================================ MOTORISTA ================================

    public function test_a_cnh_fica_na_tabela_do_motorista(): void
    {
        $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver))
            ->assertCreated()
            ->assertJsonPath('data.license_number', '12345678900')
            ->assertJsonPath('data.license_category', 'AB')
            ->assertJsonPath('data.license_issued_state', 'SP')
            ->assertJsonPath('data.license_expires_on', '2030-01-15');

        $driver = Driver::query()->sole();
        $this->assertSame('12345678900', $driver->license_number);
        $this->assertSame('SP', $driver->license_issued_state);
    }

    public function test_cnh_e_obrigatoria_so_para_motorista(): void
    {
        $semCnh = collect($this->payload(EntityRole::Driver))
            ->except(['license_number', 'license_category'])
            ->all();

        $this->postJson($this->url(EntityRole::Driver), $semCnh)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['license_number', 'license_category']);

        $this->assertSame(0, Entity::query()->count());

        $this->postJson($this->url(EntityRole::Employee), $semCnh)->assertCreated();
    }

    public function test_campo_de_motorista_mandado_para_outro_papel_e_ignorado(): void
    {
        $this->postJson($this->url(EntityRole::Supplier), $this->payload(EntityRole::Driver))
            ->assertCreated()
            ->assertJsonMissingPath('data.license_number');

        $this->assertSame(0, Driver::query()->count());
    }

    public function test_edita_a_cnh(): void
    {
        $uuid = $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver))
            ->json('data.uuid');

        $this->putJson($this->url(EntityRole::Driver, $uuid), [
            ...$this->payload(EntityRole::Driver),
            'license_category' => 'e',
        ])->assertOk()->assertJsonPath('data.license_category', 'E');
    }

    // ============================= TRANSPORTADOR =============================

    public function test_o_rntrc_fica_na_tabela_do_transportador(): void
    {
        $this->postJson($this->url(EntityRole::Carrier), [
            ...$this->payload(EntityRole::Carrier),
            'rntrc' => '123.456.78',
            'antt_category' => 'tac',
            'rntrc_expires_on' => '2029-06-30',
        ])
            ->assertCreated()
            ->assertJsonPath('data.rntrc', '12345678')
            ->assertJsonPath('data.antt_category', 'tac')
            ->assertJsonPath('data.rntrc_expires_on', '2029-06-30');

        $this->assertSame('12345678', Carrier::query()->sole()->rntrc);
    }

    public function test_rntrc_pede_a_categoria_antt(): void
    {
        $this->postJson($this->url(EntityRole::Carrier), [
            ...$this->payload(EntityRole::Carrier),
            'rntrc' => '12345678',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['antt_category' => 'Informe a categoria na ANTT']);
    }

    // =============================== VALIDAÇÃO ===============================

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
        ])
            ->assertCreated()
            ->assertJsonPath('message', 'Transportadora cadastrada com sucesso');

        $this->postJson($this->url(EntityRole::Carrier), [
            ...$this->payload(EntityRole::Carrier),
            'document' => self::CNPJ,
        ])->assertUnprocessable()->assertJsonValidationErrors('document');
    }

    public function test_editar_para_o_documento_de_outra_pessoa_e_recusado(): void
    {
        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer));
        $other = $this->postJson($this->url(EntityRole::Customer), [
            ...$this->payload(EntityRole::Customer),
            'document' => self::OTHER_CPF,
            'name' => 'Beltrano',
        ])->json('data.uuid');

        $this->putJson($this->url(EntityRole::Customer, $other), [
            ...$this->payload(EntityRole::Customer),
            'name' => 'Beltrano',
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Já existe outro cadastro com este documento');

        $this->assertSame(self::OTHER_CPF, Entity::query()->where('name', 'Beltrano')->value('document'));
    }

    public function test_filtro_de_situacao_invalido_e_recusado(): void
    {
        $this->getJson($this->url(EntityRole::Customer).'?status=apagado')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        $this->getJson('/api/registry/entities?role=motoqueiro')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }

    // ============================== CONCORRÊNCIA ==============================

    /**
     * Duas telas cadastrando o mesmo cliente ao mesmo tempo. A segunda passa
     * da checagem — a primeira ainda não tinha gravado — e bate no índice
     * único. Tem que sair 409 com mensagem, não 500 com SQL.
     */
    public function test_cadastro_simultaneo_do_mesmo_papel_vira_409(): void
    {
        $companyId = $this->organization->getKey();

        Customer::creating(function (Customer $customer) use ($companyId) {
            DB::table('customers')->insert([
                'uuid' => (string) str()->uuid(),
                'company_id' => $companyId,
                'entity_id' => $customer->entity_id,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->assertStatus(409)
            ->assertJsonPath('message', 'Este documento acabou de ser cadastrado em outra tela. Atualize e tente de novo.');
    }

    // ================================ LISTAGEM ================================

    public function test_lista_em_ordem_de_nome_e_procura_por_nome_ou_documento(): void
    {
        $this->postJson($this->url(EntityRole::Customer), [...$this->payload(EntityRole::Customer), 'name' => 'Zeca']);
        $this->postJson($this->url(EntityRole::Customer), [
            ...$this->payload(EntityRole::Customer),
            'document' => self::OTHER_CPF,
            'name' => 'Ana',
        ]);

        $this->getJson($this->url(EntityRole::Customer))
            ->assertJsonPath('data.0.entity.name', 'Ana')
            ->assertJsonPath('data.1.entity.name', 'Zeca');

        $this->getJson($this->url(EntityRole::Customer).'?search=zec')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity.name', 'Zeca');

        $this->getJson($this->url(EntityRole::Customer).'?search=111.444')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.entity.name', 'Ana');
    }

    public function test_a_lista_geral_mostra_cada_pessoa_uma_vez_com_seus_papeis(): void
    {
        $this->postJson($this->url(EntityRole::Driver), $this->payload(EntityRole::Driver));
        $this->postJson($this->url(EntityRole::Supplier), $this->payload(EntityRole::Supplier));
        $this->postJson($this->url(EntityRole::Customer), [
            ...$this->payload(EntityRole::Customer),
            'document' => self::OTHER_CPF,
            'name' => 'Ana',
        ]);

        $this->getJson('/api/registry/entities')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Ana')
            ->assertJsonCount(2, 'data.1.roles');

        $this->getJson('/api/registry/entities?role=supplier')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fulano de Tal');
    }

    // ============================ ISOLAMENTO ============================

    public function test_cada_empresa_so_ve_e_mexe_no_proprio_cadastro(): void
    {
        $uuid = $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->json('data.uuid');
        $entityUuid = Entity::query()->value('uuid');

        $this->actingAsMemberOf($this->otherCompany());

        $this->getJson($this->url(EntityRole::Customer))->assertJsonCount(0, 'data');
        $this->getJson($this->url(EntityRole::Customer, $uuid))->assertNotFound();
        $this->putJson($this->url(EntityRole::Customer, $uuid), $this->payload(EntityRole::Customer))->assertNotFound();
        $this->postJson($this->url(EntityRole::Customer, $uuid).'/deactivate')->assertNotFound();
        $this->deleteJson($this->url(EntityRole::Customer, $uuid))->assertNotFound();
        $this->deleteJson('/api/registry/entities/'.$entityUuid)->assertNotFound();
        $this->getJson('/api/registry/entities/by-document/'.self::CPF)->assertJsonPath('data', null);

        $this->assertSame(1, Customer::query()->withoutGlobalScope('company')->count());
        $this->assertSame('active', Customer::query()->withoutGlobalScope('company')->value('status'));
    }

    public function test_o_mesmo_documento_pode_existir_em_duas_empresas(): void
    {
        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))->assertCreated();

        $this->actingAsMemberOf($this->otherCompany());

        $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))->assertCreated();

        // Sem tirar o escopo, a contagem enxergaria só a empresa ativa — e
        // passaria mesmo se o cadastro fosse um só, compartilhado.
        $this->assertSame(2, Entity::query()->withoutGlobalScope('company')->count());
        $this->assertSame(2, Customer::query()->withoutGlobalScope('company')->count());
    }

    public function test_o_uuid_de_um_papel_nao_abre_outro(): void
    {
        $customer = $this->postJson($this->url(EntityRole::Customer), $this->payload(EntityRole::Customer))
            ->json('data.uuid');

        $this->getJson($this->url(EntityRole::Supplier, $customer))->assertNotFound();
        $this->deleteJson($this->url(EntityRole::Supplier, $customer))->assertNotFound();
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(0, Supplier::query()->count());
    }

    public function test_rota_de_papel_desconhecido_e_404(): void
    {
        $this->getJson('/api/registry/motoqueiros')->assertNotFound();
    }

    public function test_sem_organizacao_selecionada_nao_passa(): void
    {
        $this->actingAsSystemAdmin();

        $this->getJson($this->url(EntityRole::Customer))
            ->assertStatus(409)
            ->assertJsonPath('code', 'organization_not_selected');
    }

    // ================================ HELPERS ================================

    private function url(EntityRole $role, ?string $uuid = null): string
    {
        return '/api/registry/'.self::PATHS[$role->value].($uuid ? '/'.$uuid : '');
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
