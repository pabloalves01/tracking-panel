<?php

namespace Tests\Feature\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * O cadastro de conta: e-mail → código → conferir → (senha atual | conta nova).
 *
 * A regra que estes testes guardam acima de tudo: **conta que já existe nunca
 * define senha nova**, e a existência da conta só é dita a quem digitou o
 * código que foi para aquele e-mail.
 */
class AccountRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_codigo_de_cadastro_chega_exista_a_conta_ou_nao(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ja@posto.test']);

        $existing = $this->sendCode(' JA@posto.test ')->assertOk();
        $new = $this->sendCode('nova@posto.test')->assertOk();

        // Mesma resposta, mesmo e-mail com código de verdade: a tela nunca
        // espera um código que não vem.
        $this->assertSame($existing->json(), $new->json());
        $this->assertSame(['message', 'sent', 'retry_after'], array_keys($new->json()));
        Mail::assertQueued(VerificationCodeMail::class, 2);
        Mail::assertQueued(VerificationCodeMail::class, fn ($mail) => $mail->hasTo('ja@posto.test'));
        Mail::assertQueued(VerificationCodeMail::class, fn ($mail) => $mail->hasTo('nova@posto.test'));
    }

    public function test_codigo_errado_nao_revela_se_a_conta_existe(): void
    {
        User::factory()->create(['email' => 'ja@posto.test']);
        $this->plant('ja@posto.test', '111111');
        $this->plant('nova@posto.test', '222222');

        $existing = $this->check('ja@posto.test', '999999')->assertUnprocessable();
        $new = $this->check('nova@posto.test', '999999')->assertUnprocessable();

        $this->assertSame($existing->json(), $new->json());
        $this->assertSame('Código inválido', $new->json('message'));
    }

    public function test_conta_nova_confirma_o_codigo_e_cria_o_acesso(): void
    {
        $this->plant('nova@posto.test', '123456');

        $this->check('nova@posto.test', '123456')
            ->assertOk()
            ->assertJsonPath('account_exists', false);

        $response = $this->register('nova@posto.test', '123456', 'senha-nova-123')
            ->assertCreated()
            ->assertJsonPath('user.email', 'nova@posto.test')
            ->assertJsonPath('organizations', []);

        $user = User::query()->where('email', 'nova@posto.test')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('senha-nova-123', $user->password));

        // A sessão devolvida vale, e entra sem organização.
        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'nova@posto.test')
            ->assertJsonPath('current_organization', null);

        // E o login com a senha escolhida funciona.
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->postJson('/api/auth/login', [
            'email' => 'nova@posto.test',
            'password' => 'senha-nova-123',
        ])->assertOk();
    }

    public function test_conta_existente_so_e_revelada_depois_do_codigo_e_nao_cria_senha(): void
    {
        $user = User::factory()->create(['email' => 'pablo@melo.test', 'password' => 'senha-original-1']);
        $hash = $user->password;
        $this->plant('pablo@melo.test', '123456');

        $this->check('pablo@melo.test', '123456')
            ->assertOk()
            ->assertJsonPath('account_exists', true);

        // O código foi gasto na conferência: não há como usá-lo para chegar
        // ao formulário de senha nova.
        $this->register('pablo@melo.test', '123456', 'senha-de-outro-9')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame(1, User::query()->where('email', 'pablo@melo.test')->count());
    }

    public function test_conta_que_passa_a_existir_no_meio_responde_409_e_nao_toca_na_senha(): void
    {
        $this->plant('pablo@melo.test', '123456');

        // Conferiu quando ainda não havia conta.
        $this->check('pablo@melo.test', '123456')->assertJsonPath('account_exists', false);

        // Enquanto o formulário de nome e senha estava aberto, a conta nasceu
        // por outro caminho — um convite aceito em outra aba.
        $user = User::factory()->create(['email' => 'pablo@melo.test', 'password' => 'senha-original-1']);
        $hash = $user->password;

        $this->register('pablo@melo.test', '123456', 'senha-de-outro-9')
            ->assertStatus(409)
            ->assertJsonPath('code', 'account_exists')
            ->assertJsonPath('message', 'Este e-mail já tem uma conta. Entre com a sua senha.');

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertFalse(Hash::check('senha-de-outro-9', $user->fresh()->password));
        $this->assertSame(1, User::query()->where('email', 'pablo@melo.test')->count());
    }

    /**
     * Duas requisições passam juntas pela conferência dentro da transação; o
     * índice único barra a segunda. A violação tem de virar o mesmo 409, não
     * um 500 com o SQL na mensagem.
     */
    public function test_insercao_simultanea_vira_409_e_nao_500(): void
    {
        $this->plant('pablo@melo.test', '123456');

        // Reproduz a corrida: no instante do insert, outra transação já
        // gravou a mesma conta.
        User::creating(function (User $user) {
            if ($user->email === 'pablo@melo.test' && ! DB::table('users')->where('email', 'pablo@melo.test')->exists()) {
                DB::table('users')->insert([
                    'uuid' => (string) Str::uuid(),
                    'name' => 'Primeira',
                    'email' => 'pablo@melo.test',
                    'password' => Hash::make('senha-original-1'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $response = $this->register('pablo@melo.test', '123456', 'senha-de-outro-9');

        $response->assertStatus(409)->assertJsonPath('code', 'account_exists');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_codigo_errado_para_email_existente_nao_responde_409(): void
    {
        User::factory()->create(['email' => 'pablo@melo.test']);
        $this->plant('pablo@melo.test', '123456');

        // O código é conferido antes de qualquer pergunta sobre a conta: sem
        // o código certo, a resposta é a de qualquer código errado.
        $this->register('pablo@melo.test', '000000', 'senha-nova-123')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');
    }

    public function test_o_mesmo_codigo_nao_cria_duas_contas(): void
    {
        $this->plant('nova@posto.test', '123456');

        $this->register('nova@posto.test', '123456', 'senha-nova-123')->assertCreated();

        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])
            ->register('nova@posto.test', '123456', 'senha-nova-456')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');

        $this->assertTrue(Hash::check('senha-nova-123', User::query()->where('email', 'nova@posto.test')->value('password')));
    }

    public function test_cinco_erros_na_conferencia_matam_o_codigo(): void
    {
        $this->plant('nova@posto.test', '123456');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->check('nova@posto.test', '000000')->assertUnprocessable();
        }

        $this->check('nova@posto.test', '123456')
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Código inválido');
    }

    public function test_senha_curta_ou_sem_confirmacao_e_422_de_campo(): void
    {
        $this->plant('nova@posto.test', '123456');

        $this->postJson('/api/auth/register', [
            'email' => 'nova@posto.test',
            'code' => '123456',
            'name' => 'Nova',
            'password' => 'curta',
            'password_confirmation' => 'curta',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->postJson('/api/auth/register', [
            'email' => 'nova@posto.test',
            'code' => '123456',
            'name' => 'Nova',
            'password' => 'senha-nova-123',
            'password_confirmation' => 'outra-coisa-12',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'nova@posto.test']);
    }

    public function test_conta_criada_ja_ve_o_convite_que_estava_esperando(): void
    {
        $company = Organization::factory()->company()->create();
        Invitation::query()->create([
            'organization_id' => $company->getKey(),
            'email' => 'nova@posto.test',
            'token_hash' => hash('sha256', 'token-qualquer'),
            'status' => Invitation::PENDING,
            'expires_at' => now()->addDays(7),
        ]);
        $this->plant('nova@posto.test', '123456');

        $this->register('nova@posto.test', '123456', 'senha-nova-123')
            ->assertCreated()
            ->assertJsonCount(1, 'invitations');
    }

    // ================================ APOIO ================================

    private function sendCode(string $email)
    {
        return $this->postJson('/api/auth/verification-codes', [
            'email' => $email,
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
        ]);
    }

    private function check(string $email, string $code)
    {
        return $this->postJson('/api/auth/register/check', ['email' => $email, 'code' => $code]);
    }

    private function register(string $email, string $code, string $password)
    {
        return $this->postJson('/api/auth/register', [
            'email' => $email,
            'code' => $code,
            'name' => 'Pessoa de Teste',
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    private function plant(string $email, string $code): void
    {
        VerificationCode::query()->create([
            'email' => $email,
            'purpose' => VerificationCode::ACCOUNT_REGISTER,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
        ]);
    }
}
