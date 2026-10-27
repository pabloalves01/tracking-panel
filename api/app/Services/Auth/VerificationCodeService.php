<?php

namespace App\Services\Auth;

use App\Jobs\DeliverVerificationCode;
use App\Mail\StationSignupExistingAccountMail;
use App\Mail\VerificationCodeMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class VerificationCodeService
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const COOLDOWN_SECONDS = 60;

    public const HOURLY_CAP = 5;

    /**
     * Sempre devolve os segundos até o próximo envio.
     * A resposta pública não diz se o e-mail existe.
     */
    public function issue(string $email, string $purpose, ?string $token = null): int
    {
        $email = Text::email($email);
        $latest = $this->latest($email, $purpose);

        if ($latest) {
            $elapsed = now()->getTimestamp() - $latest->created_at->getTimestamp();

            if ($elapsed < self::COOLDOWN_SECONDS) {
                return max(1, self::COOLDOWN_SECONDS - $elapsed);
            }
        }

        $recent = VerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subHour())
            ->orderBy('created_at')
            ->get();

        if ($recent->count() >= self::HOURLY_CAP) {
            $retryAt = $recent->first()->created_at->copy()->addHour();

            return max(1, $retryAt->getTimestamp() - now()->getTimestamp());
        }

        $record = VerificationCode::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => null,
            'expires_at' => null,
        ]);

        $plain = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        DeliverVerificationCode::dispatch($record->id, $plain, $token)->afterResponse();

        return self::COOLDOWN_SECONDS;
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

        $this->throttleConsume($email, $purpose);

        $rejected = null;

        DB::transaction(function () use ($email, $purpose, $digits, &$rejected) {
            $code = VerificationCode::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNotNull('code_hash')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $code) {
                $rejected = 'Código inválido';

                return;
            }

            if ($code->invalidated_at || $code->attempts >= self::MAX_ATTEMPTS) {
                $rejected = 'Tentativas esgotadas';

                return;
            }

            if ($code->consumed_at) {
                $rejected = 'Este código já foi usado';

                return;
            }

            if (! $code->expires_at || $code->expires_at->isPast()) {
                $rejected = 'Este código expirou';

                return;
            }

            if (! hash_equals($code->code_hash, hash('sha256', $digits))) {
                $code->attempts++;

                if ($code->attempts >= self::MAX_ATTEMPTS) {
                    $code->invalidated_at = now();
                }

                $code->save();
                $rejected = $code->attempts >= self::MAX_ATTEMPTS ? 'Tentativas esgotadas' : 'Código inválido';

                return;
            }

            $code->forceFill([
                'consumed_at' => now(),
            ])->save();
        });

        if ($rejected) {
            abort(422, $rejected);
        }
    }

    private function throttleConsume(string $email, string $purpose): void
    {
        $key = 'verification-consume:'.hash('sha256', $email.'|'.$purpose);

        if (RateLimiter::tooManyAttempts($key, 30)) {
            abort(429, 'Muitas tentativas. Tente novamente em instantes');
        }

        RateLimiter::hit($key, 60);
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

    private function latest(string $email, string $purpose): ?VerificationCode
    {
        return VerificationCode::query()
            ->where('email', $email)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();
    }
}
