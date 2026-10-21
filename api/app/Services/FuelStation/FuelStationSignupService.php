<?php

namespace App\Services\FuelStation;

use App\Exceptions\FuelStationSignupException;
use App\Mail\FuelStationSignupCodeMail;
use App\Models\FuelStation;
use App\Models\FuelStationSignup;
use App\Models\FuelStationUser;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\User;
use App\Rules\Cnpj;
use App\Services\External\BrasilApiService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class FuelStationSignupService
{
    private const CODE_MINUTES = 10;

    private const RESEND_SECONDS = 60;

    private const MAX_ATTEMPTS = 5;

    private const PASSWORD_DECAY_SECONDS = 15 * 60;

    public function __construct(private BrasilApiService $brasilApi) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $cnpj): array
    {
        return $this->present($this->consultAvailable($cnpj));
    }

    /**
     * @return array{exists: bool}
     */
    public function checkEmail(string $cnpj, string $email): array
    {
        $this->assertCnpjAvailable($cnpj);

        return [
            'exists' => $this->findUser($email) !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function start(array $data, string $ip): array
    {
        $consult = $this->consultAvailable((string) $data['cnpj']);
        $email = strtolower((string) $data['email']);
        $user = $this->findUser($email);

        if ($user) {
            $this->assertCurrentPassword($user, (string) $data['password'], $email, $ip);
            $this->createStation($consult, $user);

            return [
                'message' => 'Posto cadastrado com sucesso',
            ];
        }

        $code = $this->makeCode();
        $expiresAt = now()->addMinutes(self::CODE_MINUTES);

        $signup = FuelStationSignup::query()->create([
            'cnpj' => $consult['cnpj'],
            'email' => $email,
            'name' => (string) $data['name'],
            'password' => (string) $data['password'],
            'receita_payload' => [
                'checked_at' => $consult['checked_at'],
                'payload' => $consult['payload'],
            ],
            'code_hash' => $code,
            'code_expires_at' => $expiresAt,
            'attempts' => 0,
            'last_sent_at' => now(),
        ]);

        Mail::to($email)->send(new FuelStationSignupCodeMail($code));

        return [
            'signup_uuid' => $signup->uuid,
            'email_masked' => $this->maskEmail($email),
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * @return array{expires_at: string}
     */
    public function resend(FuelStationSignup $signup): array
    {
        $availableAt = $signup->last_sent_at->copy()->addSeconds(self::RESEND_SECONDS);

        if (now()->lt($availableAt)) {
            $seconds = max(1, $availableAt->getTimestamp() - now()->getTimestamp());

            throw new FuelStationSignupException(
                'Aguarde '.$seconds.' segundos para reenviar o código',
                429,
            );
        }

        $code = $this->makeCode();
        $expiresAt = now()->addMinutes(self::CODE_MINUTES);

        $signup->forceFill([
            'code_hash' => $code,
            'code_expires_at' => $expiresAt,
            'attempts' => 0,
            'last_sent_at' => now(),
        ])->save();

        Mail::to($signup->email)->send(new FuelStationSignupCodeMail($code));

        return [
            'expires_at' => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * @return array{message: string}
     */
    public function verify(FuelStationSignup $signup, string $code): array
    {
        if ($signup->code_expires_at->isPast()) {
            throw new FuelStationSignupException('O código expirou. Peça um novo código');
        }

        if (! Hash::check($code, $signup->code_hash)) {
            $signup->increment('attempts');
            $signup->refresh();

            if ($signup->attempts >= self::MAX_ATTEMPTS) {
                $signup->delete();

                throw new FuelStationSignupException('Número de tentativas esgotado. Comece o cadastro novamente');
            }

            throw new FuelStationSignupException('Código inválido');
        }

        $stored = $signup->receita_payload;
        $payload = is_array($stored['payload'] ?? null) ? $stored['payload'] : $stored;

        if (! $this->brasilApi->isActive($payload)) {
            throw new FuelStationSignupException('O CNPJ não está com situação cadastral ATIVA');
        }

        $this->createStation([
            'cnpj' => $signup->cnpj,
            'payload' => $payload,
            'checked_at' => (string) ($stored['checked_at'] ?? now()->toIso8601String()),
        ], null, $signup);

        return [
            'message' => 'Posto cadastrado com sucesso',
        ];
    }

    /**
     * @return array{cnpj: string, payload: array<string, mixed>, checked_at: string}
     */
    private function consultAvailable(string $cnpj): array
    {
        $digits = $this->assertCnpjAvailable($cnpj);
        $consult = $this->brasilApi->cnpj($digits);

        return [
            'cnpj' => $digits,
            'payload' => $consult['payload'],
            'checked_at' => $consult['checked_at'],
        ];
    }

    private function assertCnpjAvailable(string $cnpj): string
    {
        $digits = Cnpj::digits($cnpj);

        if (! Cnpj::isValid($digits)) {
            throw new FuelStationSignupException('Informe um CNPJ válido');
        }

        if (FuelStation::withTrashed()->where('cnpj', $digits)->exists()) {
            throw new FuelStationSignupException('Este posto já está cadastrado. Fale com o responsável');
        }

        return $digits;
    }

    /**
     * @param  array{cnpj: string, payload: array<string, mixed>, checked_at: string}  $consult
     * @return array<string, mixed>
     */
    private function present(array $consult): array
    {
        $attributes = $this->stationAttributes($consult);

        return [
            'cnpj' => $consult['cnpj'],
            'corporate_name' => $attributes['corporate_name'],
            'trade_name' => $attributes['trade_name'],
            'receita_status' => $attributes['receita_status'],
            'zip_code' => $attributes['zip_code'],
            'street' => $attributes['street'],
            'number' => $attributes['number'],
            'complement' => $attributes['complement'],
            'district' => $attributes['district'],
            'state_id' => $attributes['state_id'],
            'city_id' => $attributes['city_id'],
            'state_name' => $attributes['state_name'],
            'city_name' => $attributes['city_name'],
            'uf' => $attributes['uf'],
        ];
    }

    private function assertCurrentPassword(User $user, string $password, string $email, string $ip): void
    {
        $key = 'fuel-station-signup-password:'.$email.'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw new FuelStationSignupException(
                'Muitas tentativas de senha. Tente novamente em alguns minutos',
                429,
            );
        }

        if (! Hash::check($password, $user->password)) {
            RateLimiter::hit($key, self::PASSWORD_DECAY_SECONDS);

            throw new FuelStationSignupException('Senha incorreta');
        }

        RateLimiter::clear($key);
    }

    /**
     * @param  array{cnpj: string, payload: array<string, mixed>, checked_at: string}  $consult
     */
    private function createStation(array $consult, ?User $owner, ?FuelStationSignup $signup = null): void
    {
        $attributes = $this->stationAttributes($consult);

        try {
            DB::transaction(function () use ($consult, $attributes, $owner, $signup) {
                if (FuelStation::withTrashed()->where('cnpj', $consult['cnpj'])->lockForUpdate()->exists()) {
                    throw new FuelStationSignupException('Este posto já está cadastrado. Fale com o responsável');
                }

                if ($owner === null && $signup) {
                    $emailTaken = User::query()
                        ->whereRaw('lower(email) = ?', [$signup->email])
                        ->lockForUpdate()
                        ->exists();

                    if ($emailTaken) {
                        throw new FuelStationSignupException('Este e-mail já possui conta. Comece novamente e informe a senha atual');
                    }
                }

                $station = FuelStation::query()->create([
                    'cnpj' => $consult['cnpj'],
                    'corporate_name' => $attributes['corporate_name'],
                    'trade_name' => $attributes['trade_name'],
                    'receita_status' => $attributes['receita_status'],
                    'receita_checked_at' => $consult['checked_at'],
                    'zip_code' => $attributes['zip_code'],
                    'street' => $attributes['street'],
                    'number' => $attributes['number'],
                    'complement' => $attributes['complement'],
                    'district' => $attributes['district'],
                    'state_id' => $attributes['state_id'],
                    'city_id' => $attributes['city_id'],
                    'status' => 'active',
                ]);

                $user = $owner;

                if ($user === null && $signup) {
                    $user = User::query()->create([
                        'name' => $signup->name,
                        'email' => $signup->email,
                        'password' => $signup->password,
                    ]);

                    $user->forceFill([
                        'email_verified_at' => now(),
                    ])->save();
                }

                if ($user === null) {
                    throw new FuelStationSignupException('Não foi possível identificar o responsável do posto');
                }

                FuelStationUser::query()->create([
                    'fuel_station_id' => $station->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'status' => 'active',
                ]);

                $signup?->delete();
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'fuel_stations')) {
                throw new FuelStationSignupException('Este posto já está cadastrado. Fale com o responsável');
            }

            if (str_contains($exception->getMessage(), 'users')) {
                throw new FuelStationSignupException('Este e-mail já possui conta. Comece novamente e informe a senha atual');
            }

            throw $exception;
        }
    }

    /**
     * @param  array{cnpj: string, payload: array<string, mixed>, checked_at: string}  $consult
     * @return array<string, mixed>
     */
    private function stationAttributes(array $consult): array
    {
        $payload = $consult['payload'];
        $corporateName = $this->blankToNull($payload['razao_social'] ?? null);

        if ($corporateName === null) {
            throw new FuelStationSignupException('A Receita Federal não retornou a razão social deste CNPJ');
        }

        $city = $this->findCity($payload);
        $state = $city?->state;

        if (! $state) {
            $uf = $this->blankToNull($payload['uf'] ?? null);
            $state = $uf ? State::query()->where('uf', mb_strtoupper($uf))->first() : null;
        }

        return [
            'corporate_name' => $corporateName,
            'trade_name' => $this->blankToNull($payload['nome_fantasia'] ?? null),
            'receita_status' => $this->blankToNull($payload['descricao_situacao_cadastral'] ?? null) ?? 'ATIVA',
            'zip_code' => $this->zipCode($payload['cep'] ?? null),
            'street' => $this->street($payload),
            'number' => $this->blankToNull($payload['numero'] ?? null),
            'complement' => $this->blankToNull($payload['complemento'] ?? null),
            'district' => $this->blankToNull($payload['bairro'] ?? null),
            'state_id' => $state?->id,
            'city_id' => $city?->id,
            'state_name' => $state?->name,
            'city_name' => $city?->name ?? $this->blankToNull($payload['municipio'] ?? null),
            'uf' => $state?->uf ?? $this->blankToNull($payload['uf'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function findCity(array $payload): ?City
    {
        $ibge = $payload['codigo_municipio_ibge'] ?? null;

        if ($ibge === null || $ibge === '') {
            return null;
        }

        return City::query()->where('ibge_code', $ibge)->first();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function street(array $payload): ?string
    {
        $type = $this->blankToNull($payload['descricao_tipo_de_logradouro'] ?? null);
        $street = $this->blankToNull($payload['logradouro'] ?? null);

        if ($type && $street && str_starts_with(mb_strtoupper($street), mb_strtoupper($type))) {
            return $street;
        }

        $full = trim(($type ?? '').' '.($street ?? ''));

        return $full === '' ? null : $full;
    }

    private function zipCode(mixed $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($digits === '') {
            return null;
        }

        return substr($digits, 0, 8);
    }

    private function findUser(string $email): ?User
    {
        return User::query()
            ->whereRaw('lower(email) = ?', [strtolower($email)])
            ->first();
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, 1);
        $hidden = str_repeat('*', max(mb_strlen($local) - 1, 3));

        return $visible.$hidden.'@'.$domain;
    }

    private function makeCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
