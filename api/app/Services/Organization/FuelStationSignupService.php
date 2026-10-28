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
     * @return array<string, mixed>
     */
    public function lookup(string $cnpj): array
    {
        $digits = $this->assertAvailable($cnpj);

        return $this->brasilApi->cnpj($digits);
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
     * @param  array<string, mixed>  $payload
     */
    private function createStation(string $cnpj, array $payload): Organization
    {
        return Organization::query()->create([
            'type' => OrganizationType::FuelStation,
            'document' => $cnpj,
            'legal_name' => $payload['razao_social'] ?? $payload['legal_name'] ?? 'Posto '.$cnpj,
            'trade_name' => $payload['nome_fantasia'] ?? $payload['trade_name'] ?? null,
            'status' => Organization::ACTIVE,
            'receita_checked_at' => now(),
            ...$this->address($payload),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function address(array $payload): array
    {
        return [
            'postal_code' => Text::digits($payload['cep'] ?? '') ?: null,
            'street' => $payload['logradouro'] ?? null,
            'street_number' => $payload['numero'] ?? null,
            'complement' => $payload['complemento'] ?? null,
            'neighborhood' => $payload['bairro'] ?? null,
        ];
    }
}
