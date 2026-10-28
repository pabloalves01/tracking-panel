<?php

namespace App\Services\Organization;

use App\Enums\OrganizationType;
use App\Exceptions\RejectedInput;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\External\BrasilApiService;
use App\Support\Text;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * O cadastro de um posto.
 *
 * O posto nasce como organização do tipo `fuel_station` e quem cadastrou
 * vira o primeiro membro. Só quem está logado cadastra: a conta é criada
 * antes, em `AccountRegistrationService`. Era aqui que conta e posto nasciam
 * juntos, e por isso a tela pedia senha antes de saber se a pessoa já tinha
 * conta.
 */
class FuelStationSignupService
{
    private const TAKEN = 'Já existe um cadastro com este CNPJ';

    public function __construct(
        private readonly BrasilApiService $brasilApi,
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
     * Cria o posto e põe quem está logado como primeiro membro.
     *
     * A consulta à Receita fica fora da transação: segurar uma transação
     * aberta esperando um serviço externo prende conexão à toa. Dois cliques
     * simultâneos podem passar juntos pela conferência do CNPJ; o índice único
     * de `organizations.document` barra o segundo, e a violação vira a mesma
     * mensagem da conferência em vez de um 500.
     *
     * @return array<string, mixed>
     */
    public function storeForUser(User $user, string $cnpj): array
    {
        $digits = $this->assertAvailable($cnpj);
        $payload = $this->brasilApi->cnpj($digits);

        try {
            return DB::transaction(fn () => $this->createForUser($user, $digits, $payload));
        } catch (UniqueConstraintViolationException) {
            throw new DomainException(self::TAKEN);
        }
    }

    // ================================ INTERNALS ================================

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createForUser(User $user, string $digits, array $payload): array
    {
        $fuelStation = $this->createFuelStation($digits, $payload);

        Membership::query()->create([
            'organization_id' => $fuelStation->getKey(),
            'user_id' => $user->getKey(),
            'status' => Membership::ACTIVE,
        ]);

        $this->audit->log('organization.created', $fuelStation, [
            'type' => OrganizationType::FuelStation->value,
        ], $fuelStation->getKey());

        $user->currentAccessToken()->forceFill([
            'organization_id' => $fuelStation->getKey(),
        ])->save();

        return [
            'message' => 'Posto cadastrado',
            'organization' => $fuelStation->load(['city', 'state']),
        ];
    }

    private function assertAvailable(string $cnpj): string
    {
        $digits = Text::digits($cnpj);

        if (mb_strlen($digits) !== 14) {
            throw new RejectedInput('Informe um CNPJ válido');
        }

        $taken = Organization::withTrashed()->where('document', $digits)->exists();

        if ($taken) {
            throw new DomainException(self::TAKEN);
        }

        return $digits;
    }

    /**
     * @param  array<string, mixed>  $payload  resposta crua da BrasilAPI
     */
    private function createFuelStation(string $cnpj, array $payload): Organization
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
            'state_id' => $data['state_id'],
            'city_id' => $data['city_id'],
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

        [$state, $city] = $this->resolvePlace($payload);

        return [
            'document' => $cnpj,
            'legal_name' => $blank($payload['razao_social'] ?? null) ?? 'Posto '.$cnpj,
            'trade_name' => $blank($payload['nome_fantasia'] ?? null),
            'postal_code' => Text::digits($payload['cep'] ?? '') ?: null,
            'street' => $blank($payload['logradouro'] ?? null),
            'street_number' => $blank($payload['numero'] ?? null),
            'complement' => $blank($payload['complemento'] ?? null),
            'neighborhood' => $blank($payload['bairro'] ?? null),
            'state_id' => $state?->getKey(),
            'city_id' => $city?->getKey(),
            // O nome da nossa tabela quando existe; o da Receita é um
            // fallback, e vem em caixa alta e sem acento ("SAO PAULO").
            'city' => $city?->name ?? $blank($payload['municipio'] ?? null),
            'state' => $state?->uf ?? $blank($payload['uf'] ?? null),
        ];
    }

    /**
     * Liga o endereço da Receita às nossas tabelas de estado e cidade.
     *
     * O código do IBGE é o que identifica o município sem ambiguidade —
     * casar por nome erraria nos homônimos e em qualquer diferença de
     * acento. Quando a cidade não está carregada, segue sem o vínculo: o
     * cadastro não pode travar por causa de uma tabela incompleta.
     *
     * @param  array<string, mixed>  $payload
     * @return array{0: ?State, 1: ?City}
     */
    private function resolvePlace(array $payload): array
    {
        $uf = filled($payload['uf'] ?? null) ? mb_strtoupper(trim((string) $payload['uf'])) : null;
        $ibge = $payload['codigo_municipio_ibge'] ?? null;

        $city = $ibge ? City::query()->where('ibge_code', $ibge)->first() : null;
        $state = $city?->state ?? ($uf ? State::query()->where('uf', $uf)->first() : null);

        return [$state, $city];
    }
}
