<?php

namespace App\Services\FuelStation;

use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\User;
use App\Models\VerificationCode;
use App\Rules\Cnpj;
use App\Services\AuditLogger;
use App\Services\Auth\VerificationCodeService;
use App\Services\External\BrasilApiService;
use App\Support\Membership;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FuelStationSignupService
{
    public function __construct(
        private BrasilApiService $brasilApi,
        private AuditLogger $audit,
        private VerificationCodeService $codes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function lookup(string $cnpj): array
    {
        $digits = $this->availableCnpj($cnpj);
        $payload = $this->brasilApi->cnpj($digits);

        return array_merge(
            ['cnpj' => $digits],
            Arr::except($this->address($payload), ['state_name', 'state_id', 'city_id']),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{message: string, token: string, user: User, fuel_station: FuelStation}
     */
    public function store(array $data): array
    {
        $email = strtolower(trim((string) $data['email']));

        if (User::query()->where('email', $email)->exists()) {
            abort(422, 'Este e-mail já tem conta. Entre para cadastrar o posto');
        }

        $digits = $this->availableCnpj((string) $data['cnpj']);
        $this->codes->consume($email, VerificationCode::STATION_SIGNUP, $data['code'] ?? null);
        $payload = $this->brasilApi->cnpj($digits);
        $station = $this->createStation($digits, $payload, null, $data);

        return $this->session($station->users()->first()->user, $station, 'Posto cadastrado com sucesso');
    }

    /**
     * @return array{message: string, user: User, fuel_station: FuelStation}
     */
    public function storeForUser(User $user, string $cnpj): array
    {
        $digits = $this->availableCnpj($cnpj);
        $payload = $this->brasilApi->cnpj($digits);
        $station = $this->createStation($digits, $payload, $user, []);
        $this->enterStation($user, $station);

        return [
            'message' => 'Posto vinculado à sua conta',
            'user' => $user,
            'fuel_station' => $station,
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $data
     */
    private function createStation(string $cnpj, array $payload, ?User $owner, array $data): FuelStation
    {
        $address = $this->address($payload);

        try {
            return DB::transaction(function () use ($cnpj, $address, $owner, $data) {
                $station = FuelStation::create(array_merge(
                    Arr::except($address, ['state_name', 'city_name', 'uf']),
                    [
                        'cnpj' => $cnpj,
                        'receita_checked_at' => now(),
                        'status' => 'active',
                    ],
                ));

                if (! $owner) {
                    $owner = User::create([
                        'name' => $data['name'],
                        'email' => $data['email'],
                        'password' => $data['password'],
                    ]);
                    $owner->forceFill(['email_verified_at' => now()])->save();
                }

                $membership = FuelStationUser::create([
                    'fuel_station_id' => $station->id,
                    'user_id' => $owner->id,
                    'role' => Membership::OWNER,
                    'status' => Membership::ACTIVE,
                ]);

                $this->audit->log('membership.activated', $membership, [
                    'role' => Membership::OWNER,
                    'user_uuid' => $owner->uuid,
                    'source' => 'station_signup',
                ], null, $station->id);

                return $station;
            });
        } catch (UniqueConstraintViolationException) {
            if (FuelStation::withTrashed()->where('cnpj', $cnpj)->exists()) {
                abort(422, 'Este posto já está cadastrado. Fale com o responsável');
            }

            abort(422, 'Este e-mail já tem conta. Entre para cadastrar o posto');
        }
    }

    /**
     * @return array{message: string, token: string, user: User, fuel_station: FuelStation}
     */
    private function session(User $user, FuelStation $station, string $message): array
    {
        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'company_id' => null,
            'fuel_station_id' => $station->id,
        ])->save();
        $user->withAccessToken($token->accessToken);

        return [
            'message' => $message,
            'token' => $token->plainTextToken,
            'user' => $user,
            'fuel_station' => $station->load(['city', 'state']),
        ];
    }

    private function enterStation(User $user, FuelStation $station): void
    {
        $token = $user->currentAccessToken();

        if (! $token) {
            return;
        }

        $token->forceFill([
            'company_id' => null,
            'fuel_station_id' => $station->id,
        ])->save();
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

    private function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
