<?php

namespace App\Services\Auth;

use App\Jobs\DeliverVerificationCode;
use App\Mail\StationSignupExistingAccountMail;
use App\Mail\VerificationCodeMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Text;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class VerificationCodeService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const COOLDOWN_SECONDS = 60;

    public const HOURLY_CAP = 5;

    public const HOURLY_ATTEMPTS = 10;

    public const PUBLIC_REJECTION = 'Código inválido';

    /**
     * Cadastro por convite com token válido envia o código nesta requisição.
     * Os outros fluxos respondem antes da entrega, para o tempo não dizer se a conta existe.
     *
     * @return array{sent: bool, retry_after: int, account_exists: bool}
     */
    public function issue(string $email, string $purpose, ?string $token = null): array
    {
        $email = Text::email($email);

        if ($purpose === VerificationCode::INVITATION_REGISTER && $this->inviteCanReceiveCode($email, $token)) {
            return $this->issueInvitation($email);
        }

        return $this->issueOpaque($email, $purpose, $token);
    }

    public function waitMessage(int $seconds): string
    {
        if ($seconds >= 120) {
            $minutes = max(1, (int) ceil($seconds / 60));

            return "Aguarde {$minutes} min para reenviar o código.";
        }

        return 'Aguarde '.max(1, $seconds).' segundos para reenviar o código.';
    }

    /**
     * Token do convite confere. Conta existente não finge envio.
     * Cooldown e teto contam só códigos desta finalidade que foram enviados.
     *
     * @return array{sent: bool, retry_after: int, account_exists: bool}
     */
    private function issueInvitation(string $email): array
    {
        if (User::query()->where('email', $email)->exists()) {
            return [
                'sent' => false,
                'retry_after' => 0,
                'account_exists' => true,
            ];
        }

        $retryAfter = $this->blockedFor($email, VerificationCode::INVITATION_REGISTER, true);

        if ($retryAfter !== null) {
            return [
                'sent' => false,
                'retry_after' => $retryAfter,
                'account_exists' => false,
            ];
        }

        $plain = $this->plainCode();

        VerificationCode::query()->create([
            'email' => $email,
            'purpose' => VerificationCode::INVITATION_REGISTER,
            'code_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ]);

        Mail::to($email)->queue(new VerificationCodeMail($plain, self::TTL_MINUTES));

        return [
            'sent' => true,
            'retry_after' => self::COOLDOWN_SECONDS,
            'account_exists' => false,
        ];
    }

    /**
     * A linha entra no cooldown mesmo quando a entrega depois não manda código,
     * para conta existente e e-mail desconhecido terem a mesma resposta.
     *
     * @return array{sent: bool, retry_after: int, account_exists: bool}
     */
    private function issueOpaque(string $email, string $purpose, ?string $token): array
    {
        $retryAfter = $this->blockedFor($email, $purpose, false);

        if ($retryAfter !== null) {
            return [
                'sent' => false,
                'retry_after' => $retryAfter,
                'account_exists' => false,
            ];
        }

        $plain = $this->plainCode();
        $record = VerificationCode::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => null,
            'expires_at' => null,
        ]);

        DeliverVerificationCode::dispatch($record->id, $plain, $token)->afterResponse();

        return [
            'sent' => true,
            'retry_after' => self::COOLDOWN_SECONDS,
            'account_exists' => false,
        ];
    }

    private function plainCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Segundos até o próximo envio desta finalidade, ou null se pode enviar agora.
     * No convite, só código entregue conta: outra finalidade e tentativa sem e-mail não bloqueiam.
     */
    private function blockedFor(string $email, string $purpose, bool $deliveredOnly): ?int
    {
        $query = VerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose);

        if ($deliveredOnly) {
            $query->whereNotNull('code_hash');
        }

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

    /**
     * Decide o e-mail depois da resposta. Conta existente no cadastro de posto
     * recebe o aviso para entrar, sem código.
     */
    public function deliver(int $codeId, string $plain, ?string $token): void
    {
        $code = VerificationCode::query()->find($codeId);

        if (! $code || $code->code_hash) {
            return;
        }

        if (! $this->shouldDeliver($code->email, $code->purpose, $token)) {
            if ($code->purpose === VerificationCode::STATION_SIGNUP && User::query()->where('email', $code->email)->exists()) {
                Mail::to($code->email)->queue(new StationSignupExistingAccountMail);
            }

            return;
        }

        $code->forceFill([
            'code_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
        ])->save();

        Mail::to($code->email)->queue(new VerificationCodeMail($plain, self::TTL_MINUTES));
    }

    public function consume(string $email, string $purpose, mixed $plain): void
    {
        $email = Text::email($email);
        $digits = is_scalar($plain) ? preg_replace('/\D/', '', (string) $plain) ?? '' : '';

        if ($digits === '') {
            abort(422, 'O código é obrigatório');
        }

        $this->spendGuessTime($digits);

        $key = $this->attemptKey($email, $purpose);
        $lock = Cache::lock('lock:'.$key, 20);
        $acquired = false;
        $denied = null;
        $rejected = null;

        try {
            $lock->block(15);
            $acquired = true;

            if (RateLimiter::tooManyAttempts($key, self::HOURLY_ATTEMPTS)) {
                $denied = $this->budgetResponse($key);
            } else {
                DB::transaction(function () use ($email, $purpose, $digits, &$rejected) {
                    $code = VerificationCode::query()
                        ->where('email', $email)
                        ->where('purpose', $purpose)
                        ->whereNotNull('code_hash')
                        ->latest('id')
                        ->lockForUpdate()
                        ->first();

                    if (! $code || $code->invalidated_at || $code->attempts >= self::MAX_ATTEMPTS || $code->consumed_at || ! $code->expires_at || $code->expires_at->isPast()) {
                        $this->spendGuessTime($digits);
                        $rejected = self::PUBLIC_REJECTION;

                        return;
                    }

                    if (! hash_equals($code->code_hash, hash('sha256', $digits))) {
                        $code->attempts++;

                        if ($code->attempts >= self::MAX_ATTEMPTS) {
                            $code->invalidated_at = now();
                        }

                        $code->save();
                        $rejected = self::PUBLIC_REJECTION;

                        return;
                    }

                    $code->forceFill([
                        'consumed_at' => now(),
                    ])->save();
                });

                if ($rejected) {
                    $hits = RateLimiter::hit($key, 3600);

                    if ($hits > self::HOURLY_ATTEMPTS) {
                        $denied = $this->budgetResponse($key);
                        $rejected = null;
                    }
                }
            }
        } catch (LockTimeoutException) {
            $denied = $this->budgetResponse($key);
        } finally {
            if ($acquired) {
                $lock->release();
            }
        }

        if ($denied) {
            abort($denied);
        }

        if ($rejected) {
            abort(422, $rejected);
        }
    }

    /**
     * Dez palpites errados por e-mail e finalidade, na mesma hora.
     * O acerto não entra na conta. A trava é consultada antes de avaliar o código,
     * e o incremento fica fora da transação do banco.
     */
    private function attemptKey(string $email, string $purpose): string
    {
        return 'verification-attempts:'.hash('sha256', $email.'|'.$purpose);
    }

    private function budgetResponse(string $key): JsonResponse
    {
        $seconds = max(1, RateLimiter::availableIn($key));
        $minutes = max(1, (int) ceil($seconds / 60));

        return response()->json([
            'message' => "Muitas tentativas. Tente novamente em {$minutes} min.",
            'retry_after' => $seconds,
        ], 429, [
            'Retry-After' => $seconds,
        ]);
    }

    private function spendGuessTime(string $digits): void
    {
        hash_equals(hash('sha256', '000000'), hash('sha256', $digits));
    }

    private function shouldDeliver(string $email, string $purpose, ?string $token): bool
    {
        $account = User::query()->where('email', $email)->exists();

        return match ($purpose) {
            VerificationCode::STATION_SIGNUP => ! $account,
            VerificationCode::PASSWORD_RESET => $account,
            VerificationCode::INVITATION_REGISTER => $this->inviteCanReceiveCode($email, $token) && ! $account,
            default => false,
        };
    }

    private function inviteCanReceiveCode(string $email, ?string $token): bool
    {
        if (! is_string($token) || $token === '') {
            return false;
        }

        $invitation = Invitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        return $invitation
            && $invitation->status === Invitation::PENDING
            && $invitation->expires_at->isFuture()
            && strtolower($invitation->email) === $email;
    }
}
