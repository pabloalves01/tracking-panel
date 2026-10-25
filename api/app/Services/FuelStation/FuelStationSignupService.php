<?php

namespace App\Services\FuelStation;

use App\Models\FuelStation;
use App\Models\FuelStationUser;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\User;
use App\Rules\Cnpj;
use App\Services\External\BrasilApiService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FuelStationSignupService
{
    public const ALREADY_REGISTERED = 'Este posto já está cadastrado na Logiv. Peça um convite ao administrador do posto.';

    public const EMAIL_TAKEN = 'Este e-mail já tem conta. Entre com ela para cadastrar o posto.';

    public function __construct(private BrasilApiService $brasilApi) {}

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
     * Cadastro público: cria a conta nova, o posto e o vínculo admin numa transação.
     *
     * @param  array<string, mixed>  $data
     * @return array{0: FuelStation, 1: User}
     */
    public function store(array $data): array
    {
        $digits = $this->availableCnpj((string) $data['cnpj']);
        $payload = $this->brasilApi->cnpj($digits);

        return $this->createStation($digits, $payload, function () use ($data) {
            return User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
        });
    }

    /**
     * Cadastro com a conta logada: o posto vai pra ela.
     */
    public function storeForUser(User $user, string $cnpj): FuelStation
    {
        $digits = $this->availableCnpj($cnpj);
        $payload = $this->brasilApi->cnpj($digits);

        [$station] = $this->createStation($digits, $payload, fn () => $user);

        return $station;
    }

    private function availableCnpj(string $cnpj): string
    {
        $digits = Cnpj::digits($cnpj);

        if (! Cnpj::isValid($digits)) {
            abort(422, 'Informe um CNPJ válido.');
        }

        if (FuelStation::withTrashed()->where('cnpj', $digits)->exists()) {
            abort(422, self::ALREADY_REGISTERED);
        }

        return $digits;
    }

    /**
     * Cria o posto e liga o dono como admin. `$owner` devolve a conta, nova ou a logada,
     * dentro da mesma transação.
     *
     * @param  array<string, mixed>  $payload
     * @param  callable(): User  $owner
     * @return array{0: FuelStation, 1: User}
     */
    private function createStation(string $cnpj, array $payload, callable $owner): array
    {
        $address = $this->address($payload);

        try {
            return DB::transaction(function () use ($cnpj, $address, $owner) {
                $station = FuelStation::create(array_merge(
                    Arr::except($address, ['state_name', 'city_name', 'uf']),
                    [
                        'cnpj' => $cnpj,
                        'receita_checked_at' => now(),
                        'status' => 'active',
                    ],
                ));

                $user = $owner();

                FuelStationUser::create([
                    'fuel_station_id' => $station->id,
                    'user_id' => $user->id,
                    'role' => 'admin',
                ]);

                return [$station, $user];
            });
        } catch (UniqueConstraintViolationException) {
            if (FuelStation::withTrashed()->where('cnpj', $cnpj)->exists()) {
                abort(422, self::ALREADY_REGISTERED);
            }

            throw ValidationException::withMessages([
                'email' => self::EMAIL_TAKEN,
            ]);
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
            abort(503, 'Não foi possível consultar a Receita agora. Tente de novo em instantes.');
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
