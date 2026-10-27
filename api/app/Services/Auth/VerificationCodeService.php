<?php

namespace App\Services\Auth;

use App\Mail\VerificationCodeMail;
use App\Models\Invitation;
use App\Models\User;
use App\Models\VerificationCode;
use App\Support\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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

        $plain = $this->shouldDeliver($email, $purpose, $token)
            ? str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)
            : null;

        VerificationCode::query()->create([
            'email' => $email,
            'purpose' => $purpose,
            'code_hash' => $plain ? hash('sha256', $plain) : null,
            'expires_at' => $plain ? now()->addMinutes(self::TTL_MINUTES) : null,
        ]);

        if ($plain) {
            Mail::to($email)->queue(new VerificationCodeMail($plain, self::TTL_MINUTES));
        }

        return self::COOLDOWN_SECONDS;
    }

    public function consume(string $email, string $purpose, mixed $plain): void
    {
        $email = Text::email($email);
        $digits = is_scalar($plain) ? preg_replace('/\D/', '', (string) $plain) ?? '' : '';

        if ($digits === '') {
            abort(422, 'O código é obrigatório');
        }

        DB::transaction(function () use ($email, $purpose, $digits) {
            $code = VerificationCode::query()
                ->where('email', $email)
                ->where('purpose', $purpose)
                ->whereNotNull('code_hash')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $code) {
                abort(422, 'Código inválido');
            }

            if ($code->invalidated_at || $code->attempts >= self::MAX_ATTEMPTS) {
                abort(422, 'Tentativas esgotadas');
            }

            if ($code->consumed_at) {
                abort(422, 'Este código já foi usado');
            }

            if (! $code->expires_at || $code->expires_at->isPast()) {
                abort(422, 'Este código expirou');
            }

            if (! hash_equals($code->code_hash, hash('sha256', $digits))) {
                $code->attempts++;

                if ($code->attempts >= self::MAX_ATTEMPTS) {
                    $code->invalidated_at = now();
                }

                $code->save();

                abort(422, $code->attempts >= self::MAX_ATTEMPTS ? 'Tentativas esgotadas' : 'Código inválido');
            }

            $code->forceFill([
                'consumed_at' => now(),
            ])->save();
        });
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
