<?php

namespace App\Services\Auth;

use App\Mail\StationSignupExistingAccountMail;
use App\Mail\VerificationCodeMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Text;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Os códigos de seis dígitos do cadastro por convite, do cadastro de posto
 * e da recuperação de senha.
 *
 * Três coisas mudaram em relação à versão anterior, e todas derivam de um
 * problema real que ela teve:
 *
 * 1. **Um caminho de emissão.** Antes havia dois — um síncrono para o
 *    convite e um "opaco" que gravava linha sem hash, despachava um job
 *    `afterResponse` e decidia a entrega depois. O resultado é que o código
 *    do convite simplesmente não chegava. Agora a decisão de entregar é
 *    tomada uma vez, antes de responder.
 *
 * 2. **Duas camadas de limite, não três.** Saiu o teto de palpites por
 *    e-mail. Ele permitia que um terceiro esgotasse os palpites da vítima e
 *    a impedisse de usar um código que ela já tinha recebido — um bloqueio
 *    dirigido, de até uma hora. Ficam o limite por código (cinco erros
 *    invalidam) e o limite de envios por e-mail, mais o throttle por IP na
 *    rota. São 5 códigos/hora × 5 palpites = 25 tentativas por hora contra
 *    um espaço de um milhão: continua seguro, e limitar envio incomoda sem
 *    trancar quem é alvo.
 *
 * 3. **O código não viaja em claro na fila.** `VerificationCodeMail` é
 *    `ShouldBeEncrypted`, então o payload é cifrado no storage da fila.
 *    Enfileirar mantém a resposta rápida e com tempo constante, o que
 *    importa porque o tempo de resposta não pode dizer se a conta existe.
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
     * A resposta é a mesma exista ou não a conta: quem pergunta pela rota
     * pública não descobre quem está cadastrado.
     *
     * @return array{sent: bool, retry_after: int, account_exists: bool}
     */
    public function issue(string $email, string $purpose, ?string $token = null): array
    {
        $email = Text::email($email);

        // O convite é o único caso em que dizer "você já tem conta" é certo:
        // quem tem o link já sabe que o e-mail foi convidado, e a tela
        // precisa mandar essa pessoa para o login em vez do cadastro.
        $isInvite = $purpose === VerificationCode::INVITATION_REGISTER
            && $this->inviteMatches($email, $token);

        if ($isInvite && $this->accountExists($email)) {
            return ['sent' => false, 'retry_after' => 0, 'account_exists' => true];
        }

        $retryAfter = $this->blockedFor($email, $purpose);

        if ($retryAfter !== null) {
            return ['sent' => false, 'retry_after' => $retryAfter, 'account_exists' => false];
        }

        $this->issueAndDeliver($email, $purpose, $token);

        return [
            'sent' => true,
            'retry_after' => self::COOLDOWN_SECONDS,
            'account_exists' => false,
        ];
    }

    /**
     * Confere e gasta o código.
     *
     * Chame de dentro da transação que faz o trabalho: se o passo seguinte
     * falhar, o código não foi gasto. Antes o consumo ficava fora, e um erro
     * depois obrigava a pedir outro código.
     */
    public function consume(string $email, string $purpose, mixed $plain): void
    {
        $email = Text::email($email);
        $digits = is_scalar($plain) ? (preg_replace('/\D/', '', (string) $plain) ?? '') : '';

        if ($digits === '') {
            throw new DomainException('O código é obrigatório');
        }

        $code = VerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->whereNotNull('code_hash')
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if (! $this->isUsable($code)) {
            throw new DomainException(self::PUBLIC_REJECTION);
        }

        if (! hash_equals($code->code_hash, hash('sha256', $digits))) {
            $code->attempts++;

            if ($code->attempts >= self::MAX_ATTEMPTS) {
                $code->invalidated_at = now();
            }

            $code->save();

            throw new DomainException(self::PUBLIC_REJECTION);
        }

        $code->forceFill(['consumed_at' => now()])->save();
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
     * contar igual para e-mail cadastrado e não cadastrado.
     */
    private function issueAndDeliver(string $email, string $purpose, ?string $token): void
    {
        $deliver = $this->shouldDeliver($email, $purpose, $token);
        $plain = $deliver ? $this->plainCode() : null;

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

            return;
        }

        // Quem tenta cadastrar um posto com um e-mail que já tem conta recebe
        // um aviso para entrar, em vez de silêncio.
        if ($purpose === VerificationCode::STATION_SIGNUP && $this->accountExists($email)) {
            Mail::to($email)->queue(new StationSignupExistingAccountMail);
        }
    }

    private function shouldDeliver(string $email, string $purpose, ?string $token): bool
    {
        $account = $this->accountExists($email);

        return match ($purpose) {
            VerificationCode::STATION_SIGNUP => ! $account,
            VerificationCode::PASSWORD_RESET => $account,
            VerificationCode::INVITATION_REGISTER => ! $account && $this->inviteMatches($email, $token),
            default => false,
        };
    }

    /**
     * Segundos até o próximo envio, ou null se pode pedir agora.
     *
     * Conta qualquer linha desta finalidade, entregue ou não: é o que faz a
     * resposta ser igual para e-mail cadastrado e não cadastrado.
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

    private function inviteMatches(string $email, ?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $invitation
            && $invitation->status === Invitation::PENDING
            && $invitation->expires_at?->isFuture()
            && mb_strtolower($invitation->email) === $email;
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
