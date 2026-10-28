<?php

namespace App\Services\Auth;

use App\Exceptions\RejectedInput;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Os códigos de seis dígitos do cadastro de conta e da recuperação de senha.
 *
 * **A regra de entrega é a mais simples que não vaza.** O código de cadastro
 * é sempre entregue, exista a conta ou não: quem pede recebe um código de
 * verdade, e a tela nunca fica esperando algo que não vem. Só depois de o
 * código ser conferido — quando a pessoa já provou que lê aquela caixa de
 * entrada — o sistema diz se a conta existe. A recuperação de senha só
 * entrega para conta existente, com resposta idêntica nos dois casos.
 *
 * A versão anterior decidia a entrega por finalidade, com uma condição
 * diferente em cada uma, e para conta existente mandava um aviso no lugar do
 * código. A tela não tinha como saber, pedia o código, e o código nunca
 * chegava. Foi essa condicional que produziu o defeito.
 *
 * Limites: o código morre com cinco erros, e há teto de envios por e-mail,
 * além do throttle por IP na rota. Não existe teto de palpites por e-mail: ele
 * deixava um terceiro esgotar os palpites da vítima e trancá-la fora de um
 * código que ela mesma recebeu. São 5 códigos/hora × 5 palpites = 25
 * tentativas por hora contra um espaço de um milhão.
 *
 * O código não viaja em claro na fila: `VerificationCodeMail` é
 * `ShouldBeEncrypted`.
 */
class VerificationCodeService
{
    public const TTL_MINUTES = 10;

    /** Erros tolerados por código antes de invalidá-lo. */
    public const MAX_ATTEMPTS = 5;

    public const COOLDOWN_SECONDS = 60;

    /** Envios por hora, por e-mail e finalidade. */
    public const HOURLY_CAP = 5;

    public const PUBLIC_REJECTION = 'Código inválido';

    /**
     * Pede um código.
     *
     * A resposta é a mesma exista ou não a conta.
     *
     * @return array{sent: bool, retry_after: int}
     */
    public function issue(string $email, string $purpose): array
    {
        $email = Text::email($email);

        $retryAfter = $this->blockedFor($email, $purpose);

        if ($retryAfter !== null) {
            return ['sent' => false, 'retry_after' => $retryAfter];
        }

        $this->issueAndDeliver($email, $purpose);

        return ['sent' => true, 'retry_after' => self::COOLDOWN_SECONDS];
    }

    /**
     * Confere o código e devolve a linha, sem gastá-la.
     *
     * Roda na própria transação, curta, e **fora** da transação de quem
     * chama. O palpite errado precisa ficar gravado mesmo quando a operação
     * toda falha logo em seguida: contado dentro da transação de quem chama,
     * o `throw` seguinte levava o incremento junto no rollback, e o limite de
     * cinco tentativas não valia nada.
     */
    public function verify(string $email, string $purpose, mixed $plain): VerificationCode
    {
        $email = Text::email($email);
        $digits = is_scalar($plain) ? (preg_replace('/\D/', '', (string) $plain) ?? '') : '';

        if ($digits === '') {
            throw new RejectedInput('O código é obrigatório');
        }

        $code = DB::transaction(function () use ($email, $purpose, $digits) {
            $code = VerificationCode::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNotNull('code_hash')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $this->isUsable($code)) {
                return null;
            }

            if (! hash_equals($code->code_hash, hash('sha256', $digits))) {
                $code->attempts++;

                if ($code->attempts >= self::MAX_ATTEMPTS) {
                    $code->invalidated_at = now();
                }

                $code->save();

                return null;
            }

            return $code;
        });

        // Fora da transação: o incremento acima já está comitado quando a
        // rejeição sobe.
        if ($code === null) {
            throw new RejectedInput(self::PUBLIC_REJECTION);
        }

        return $code;
    }

    /**
     * Marca o código como gasto.
     *
     * Condicional: só gasta se ainda não foi gasto. Duas requisições com o
     * mesmo código podem passar juntas por `verify`, que não gasta; aqui só
     * uma delas vence, e a outra recebe a mesma recusa de um código errado.
     *
     * Chame de dentro da transação que faz o trabalho: se o passo seguinte
     * falhar, o código não foi gasto e a pessoa não precisa pedir outro.
     */
    public function markUsed(VerificationCode $code): void
    {
        $spent = VerificationCode::query()
            ->whereKey($code->getKey())
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        if ($spent === 0) {
            throw new RejectedInput(self::PUBLIC_REJECTION);
        }
    }

    /**
     * Confere e gasta de uma vez, para quem não tem transação própria.
     */
    public function consume(string $email, string $purpose, mixed $plain): void
    {
        $code = $this->verify($email, $purpose, $plain);

        DB::transaction(fn () => $this->markUsed($code));
    }

    public function waitMessage(int $seconds): string
    {
        if ($seconds >= 120) {
            return 'Aguarde '.max(1, (int) ceil($seconds / 60)).' minutos para pedir outro código.';
        }

        return 'Aguarde '.max(1, $seconds).' segundos para pedir outro código.';
    }

    // ================================ INTERNALS ================================

    /**
     * Grava e entrega, quando for o caso.
     *
     * A linha é criada mesmo quando nada é enviado: é ela que faz o cooldown
     * da recuperação de senha contar igual para e-mail cadastrado e não
     * cadastrado.
     */
    private function issueAndDeliver(string $email, string $purpose): void
    {
        $plain = $this->shouldDeliver($email, $purpose) ? $this->plainCode() : null;

        DB::transaction(function () use ($email, $purpose, $plain) {
            VerificationCode::query()->create([
                'email' => $email,
                'purpose' => $purpose,
                'code_hash' => $plain ? hash('sha256', $plain) : null,
                'expires_at' => $plain ? now()->addMinutes(self::TTL_MINUTES) : null,
            ]);
        });

        if ($plain) {
            Mail::to($email)->queue(new VerificationCodeMail($plain, self::TTL_MINUTES));
        }
    }

    private function shouldDeliver(string $email, string $purpose): bool
    {
        return match ($purpose) {
            VerificationCode::ACCOUNT_REGISTER => true,
            VerificationCode::PASSWORD_RESET => $this->accountExists($email),
            default => false,
        };
    }

    /**
     * Segundos até o próximo envio, ou null se pode pedir agora.
     *
     * Conta qualquer linha desta finalidade, entregue ou não.
     */
    private function blockedFor(string $email, string $purpose): ?int
    {
        $query = VerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose);

        $latest = (clone $query)->latest('id')->first();

        if ($latest) {
            $elapsed = now()->getTimestamp() - $latest->created_at->getTimestamp();

            if ($elapsed < self::COOLDOWN_SECONDS) {
                return max(1, self::COOLDOWN_SECONDS - $elapsed);
            }
        }

        $recent = (clone $query)
            ->where('created_at', '>=', now()->subHour())
            ->orderBy('created_at')
            ->get();

        if ($recent->count() >= self::HOURLY_CAP) {
            $retryAt = $recent->first()->created_at->copy()->addHour();

            return max(1, $retryAt->getTimestamp() - now()->getTimestamp());
        }

        return null;
    }

    private function isUsable(?VerificationCode $code): bool
    {
        return $code
            && ! $code->invalidated_at
            && ! $code->consumed_at
            && $code->attempts < self::MAX_ATTEMPTS
            && $code->expires_at
            && $code->expires_at->isFuture();
    }

    private function accountExists(string $email): bool
    {
        return User::query()->where('email', $email)->exists();
    }

    private function plainCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
