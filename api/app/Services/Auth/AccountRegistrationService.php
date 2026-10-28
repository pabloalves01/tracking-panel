<?php

namespace App\Services\Auth;

use App\Exceptions\AccountAlreadyExists;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Text;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Criar conta, e só conta.
 *
 * Conta e organização eram criadas juntas: o cadastro de posto pedia nome,
 * e-mail e senha de uma vez, e o convite fazia o mesmo. O formulário de
 * senha aparecia antes de o sistema saber quem era a pessoa — e quem já
 * tinha conta digitava uma senha nova para nada.
 *
 * Agora a identidade vem primeiro, e é o padrão de quem resolve bem o caso
 * de uma conta em várias organizações:
 *
 *   e-mail → código (sempre entregue) → conferir
 *       ├─ conta existe → entrar com a senha atual
 *       └─ conta nova   → nome e senha → conta criada
 *
 * Com a sessão em mãos, a pessoa cria o posto ou aceita o convite como
 * qualquer usuário logado. Nenhum endpoint cria credencial e organização ao
 * mesmo tempo, e nenhum caminho leva uma conta existente a definir senha.
 */
class AccountRegistrationService
{
    public function __construct(private readonly VerificationCodeService $codes) {}

    /**
     * Confere o código e diz se a conta já existe.
     *
     * Revelar aqui é seguro: só chega a esta resposta quem digitou o código
     * que foi para aquele e-mail. Quando a conta existe o código é gasto, já
     * que não há mais nada a fazer com ele; quando não existe, segue valendo
     * para o envio do nome e da senha.
     */
    public function check(string $email, mixed $code): bool
    {
        $email = Text::email($email);
        $verified = $this->codes->verify($email, VerificationCode::ACCOUNT_REGISTER, $code);

        if (! $this->accountExists($email)) {
            return false;
        }

        DB::transaction(fn () => $this->codes->markUsed($verified));

        return true;
    }

    /**
     * Cria a conta.
     *
     * Duas guardas contra sobrescrever uma conta existente, e as duas
     * respondem a mesma coisa: a conferência dentro da transação pega o caso
     * comum — a conta passou a existir enquanto o formulário estava aberto —
     * e o índice único de `users.email` pega as duas requisições simultâneas
     * que passam juntas pela conferência. Nenhuma das duas toca na senha de
     * quem já tem conta: no pior caso, nada é gravado.
     */
    public function register(string $email, mixed $code, string $name, string $password): User
    {
        $email = Text::email($email);

        // Fora da transação, para o palpite errado ficar contado.
        $verified = $this->codes->verify($email, VerificationCode::ACCOUNT_REGISTER, $code);

        try {
            return DB::transaction(function () use ($email, $name, $password, $verified) {
                if ($this->accountExists($email)) {
                    throw new AccountAlreadyExists;
                }

                $this->codes->markUsed($verified);

                $user = User::query()->create([
                    'name' => $name,
                    'email' => $email,
                    'password' => $password,
                ]);

                $user->forceFill(['email_verified_at' => now()])->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw new AccountAlreadyExists;
        }
    }

    private function accountExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }
}
