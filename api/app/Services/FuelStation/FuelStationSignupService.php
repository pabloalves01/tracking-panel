<?php

namespace App\Services\FuelStation;

use App\Mail\FuelStationSignupCodeMail;
use App\Models\FuelStation;
use App\Models\FuelStationSignup;
use App\Models\FuelStationUser;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\User;
use App\Rules\Cnpj;
use App\Services\External\BrasilApiService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class FuelStationSignupService
{
    public function __construct(private BrasilApiService $brasilApi) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $cnpj): array
    {
        $digits = $this->availableCnpj($cnpj);
        $payload = $this->brasilApi->cnpj($digits);

        return array_merge(['cnpj' => $digits], $this->address($payload));
    }

    /**
     * @return array{exists: bool}
     */
    public function checkEmail(string $cnpj, string $email): array
    {
        $this->availableCnpj($cnpj);

        return [
            'exists' => User::where('email', strtolower($email))->exists(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function start(array $data): array
    {
        $digits = $this->availableCnpj((string) $data['cnpj']);
        $payload = $this->brasilApi->cnpj($digits);
        $email = strtolower((string) $data['email']);
        $user = User::where('email', $email)->first();

        if ($user) {
            $this->checkPassword($user, (string) $data['password']);
            $this->createStation($digits, $payload, $user);

            return [
                'message' => 'Posto cadastrado com sucesso',
            ];
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        $signup = FuelStationSignup::create([
            'cnpj' => $digits,
            'email' => $email,
            'name' => (string) $data['name'],
            'password' => (string) $data['password'],
            'receita_payload' => $payload,
            'code_hash' => $code,
            'code_expires_at' => $expiresAt,
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
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresAt = now()->addMinutes(10);

        $signup->forceFill([
            'code_hash' => $code,
            'code_expires_at' => $expiresAt,
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
            abort(422, 'O código expirou. Peça um novo código');
        }

        if (! Hash::check($code, $signup->code_hash)) {
            abort(422, 'Código inválido');
        }

        $this->createStation($signup->cnpj, $signup->receita_payload, null, $signup);

        return [
            'message' => 'Posto cadastrado com sucesso',
        ];
    }

    private function availableCnpj(string $cnpj): string
    {
        $digits = Cnpj::digits($cnpj);

        if (! Cnpj::isValid($digits)) {
            abort(422, 'Informe um CNPJ válido');
        }

        if (FuelStation::withTrashed()->where('cnpj', $digits)->exists()) {
            abort(422, 'Este posto já está cadastrado. Fale com o responsável');
        }

        return $digits;
    }

    private function checkPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            abort(422, 'Senha incorreta');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createStation(string $cnpj, array $payload, ?User $owner, ?FuelStationSignup $signup = null): void
    {
        $address = $this->address($payload);

        try {
            DB::transaction(function () use ($cnpj, $address, $owner, $signup) {
                $station = FuelStation::create([
                    'cnpj' => $cnpj,
                    'corporate_name' => $address['corporate_name'],
                    'trade_name' => $address['trade_name'],
                    'receita_status' => $address['receita_status'],
                    'receita_checked_at' => now(),
                    'zip_code' => $address['zip_code'],
                    'street' => $address['street'],
                    'number' => $address['number'],
                    'complement' => $address['complement'],
                    'district' => $address['district'],
                    'state_id' => $address['state_id'],
                    'city_id' => $address['city_id'],
                    'status' => 'active',
                ]);

                if ($owner) {
                    $user = $owner;
                } else {
                    $user = new User;
                    $user->forceFill([
                        'name' => $signup->name,
                        'email' => $signup->email,
                        'password' => $signup->password,
                        'email_verified_at' => now(),
                    ])->save();
                }

                FuelStationUser::create([
                    'fuel_station_id' => $station->id,
                    'user_id' => $user->id,
                    'role' => 'owner',
                    'status' => 'active',
                ]);

                $signup?->delete();
            });
        } catch (UniqueConstraintViolationException) {
            abort(422, 'Este posto já está cadastrado. Fale com o responsável');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function address(array $payload): array
    {
        $corporateName = $this->text($payload['razao_social'] ?? null);

        if ($corporateName === null) {
            abort(422, 'A Receita Federal não retornou a razão social deste CNPJ');
        }

        $ibge = $payload['codigo_municipio_ibge'] ?? null;
        $city = ($ibge === null || $ibge === '') ? null : City::where('ibge_code', $ibge)->first();
        $state = $city?->state;

        if (! $state && $this->text($payload['uf'] ?? null)) {
            $state = State::where('uf', mb_strtoupper($this->text($payload['uf'])))->first();
        }

        $type = $this->text($payload['descricao_tipo_de_logradouro'] ?? null);
        $street = $this->text($payload['logradouro'] ?? null);
        $fullStreet = trim(($type ?? '').' '.($street ?? ''));
        $cep = preg_replace('/\D/', '', (string) ($payload['cep'] ?? '')) ?? '';

        return [
            'corporate_name' => $corporateName,
            'trade_name' => $this->text($payload['nome_fantasia'] ?? null),
            'receita_status' => $this->text($payload['descricao_situacao_cadastral'] ?? null) ?? 'ATIVA',
            'zip_code' => $cep === '' ? null : substr($cep, 0, 8),
            'street' => $fullStreet === '' ? null : $fullStreet,
            'number' => $this->text($payload['numero'] ?? null),
            'complement' => $this->text($payload['complemento'] ?? null),
            'district' => $this->text($payload['bairro'] ?? null),
            'state_id' => $state?->id,
            'city_id' => $city?->id,
            'state_name' => $state?->name,
            'city_name' => $city?->name ?? $this->text($payload['municipio'] ?? null),
            'uf' => $state?->uf ?? $this->text($payload['uf'] ?? null),
        ];
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).str_repeat('*', max(mb_strlen($local) - 1, 3)).'@'.$domain;
    }

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
