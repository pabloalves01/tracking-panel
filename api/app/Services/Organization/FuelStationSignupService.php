<?php

namespace App\Services\Organization;

use App\Enums\OrganizationType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Models\VerificationCode;
use App\Services\Auth\VerificationCodeService;
use App\Services\AuditLogger;
use App\Services\External\BrasilApiService;
use App\Support\Text;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * O cadastro público de um posto.
 *
 * O posto nasce como organização do tipo `fuel_station` e quem cadastrou
 * vira o primeiro membro.
 */
class FuelStationSignupService
{
    public function __construct(
        private readonly BrasilApiService $brasilApi,
        private readonly VerificationCodeService $codes,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Consulta o CNPJ para preencher o formulário.
     *
     * Devolve já normalizado: a tela não precisa conhecer os nomes de campo
     * da BrasilAPI, e o vocabulário é o mesmo de `organizations`.
     *
     * @return array<string, mixed>
     */
    public function lookup(string $cnpj): array
    {
        $digits = $this->assertAvailable($cnpj);

        return $this->normalize($digits, $this->brasilApi->cnpj($digits));
    }

    /**
     * Cadastro público: cria a conta e o posto juntos.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function store(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $cnpj = $this->assertAvailable($data['cnpj']);

            // A consulta externa vem antes do consumo do código: se a
            // BrasilAPI falhar, o código não foi gasto.
            $payload = $this->brasilApi->cnpj($cnpj);

            $this->codes->consume(
                Text::email($data['email']),
                VerificationCode::STATION_SIGNUP,
                $data['code'],
            );

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => Text::email($data['email']),
                'password' => $data['password'],
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $station = $this->createStation($cnpj, $payload);

            Membership::query()->create([
                'organization_id' => $station->getKey(),
                'user_id' => $user->getKey(),
                'status' => Membership::ACTIVE,
            ]);

            $this->audit->log('organization.created', $station, [
                'type' => OrganizationType::FuelStation->value,
            ], $station->getKey());

            $token = $user->createToken('web');
            $token->accessToken->forceFill(['organization_id' => $station->getKey()])->save();
            $user->withAccessToken($token->accessToken);

            return [
                'message' => 'Posto cadastrado',
                'token' => $token->plainTextToken,
                'user' => $user,
                'organization' => $station->load(['city', 'state']),
            ];
        });
    }

    /**
     * Cadastro de posto por quem já está logado.
     *
     * @return array<string, mixed>
     */
    public function storeForUser(User $user, string $cnpj): array
    {
        return DB::transaction(function () use ($user, $cnpj) {
            $digits = $this->assertAvailable($cnpj);
            $payload = $this->brasilApi->cnpj($digits);

            $station = $this->createStation($digits, $payload);

            Membership::query()->create([
                'organization_id' => $station->getKey(),
                'user_id' => $user->getKey(),
                'status' => Membership::ACTIVE,
            ]);

            $this->audit->log('organization.created', $station, [
                'type' => OrganizationType::FuelStation->value,
            ], $station->getKey());

            $user->currentAccessToken()->forceFill([
                'organization_id' => $station->getKey(),
            ])->save();

            return [
                'message' => 'Posto cadastrado',
                'organization' => $station->load(['city', 'state']),
            ];
        });
    }

    // ================================ INTERNALS ================================

    private function assertAvailable(string $cnpj): string
    {
        $digits = Text::digits($cnpj);

        if (mb_strlen($digits) !== 14) {
            throw new DomainException('Informe um CNPJ válido');
        }

        $taken = Organization::withTrashed()->where('document', $digits)->exists();

        if ($taken) {
            throw new DomainException('Já existe um cadastro com este CNPJ');
        }

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $payload  resposta crua da BrasilAPI
     */
    private function createStation(string $cnpj, array $payload): Organization
    {
        $data = $this->normalize($cnpj, $payload);

        return Organization::query()->create([
            'type' => OrganizationType::FuelStation,
            'document' => $data['document'],
            'legal_name' => $data['legal_name'],
            'trade_name' => $data['trade_name'],
            'status' => Organization::ACTIVE,
            'receita_checked_at' => now(),
            'postal_code' => $data['postal_code'],
            'street' => $data['street'],
            'street_number' => $data['street_number'],
            'complement' => $data['complement'],
            'neighborhood' => $data['neighborhood'],
        ]);
    }

    /**
     * Traduz a resposta da BrasilAPI para o vocabulário de `organizations`.
     *
     * Só este método conhece os nomes de campo de lá: a tela e o resto do
     * sistema falam `legal_name`, `postal_code` e companhia.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(string $cnpj, array $payload): array
    {
        $blank = fn (mixed $value) => filled($value) ? trim((string) $value) : null;

        return [
            'document' => $cnpj,
            'legal_name' => $blank($payload['razao_social'] ?? null) ?? 'Posto '.$cnpj,
            'trade_name' => $blank($payload['nome_fantasia'] ?? null),
            'postal_code' => Text::digits($payload['cep'] ?? '') ?: null,
            'street' => $blank($payload['logradouro'] ?? null),
            'street_number' => $blank($payload['numero'] ?? null),
            'complement' => $blank($payload['complemento'] ?? null),
            'neighborhood' => $blank($payload['bairro'] ?? null),
            'city' => $blank($payload['municipio'] ?? null),
            'state' => $blank($payload['uf'] ?? null),
        ];
    }
}
