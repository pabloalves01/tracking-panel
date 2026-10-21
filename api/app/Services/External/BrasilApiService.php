<?php

namespace App\Services\External;

use App\Exceptions\BrasilApiException;
use App\Rules\Cnpj;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BrasilApiService
{
    private const TIMEOUT_SECONDS = 5;

    private const CACHE_HOURS = 24;

    /**
     * Consulta o CNPJ na BrasilAPI.
     * Só devolve empresa com situação ATIVA. A resposta fica em cache por 24 horas.
     *
     * @return array{payload: array<string, mixed>, checked_at: string}
     */
    public function cnpj(string $cnpj): array
    {
        $digits = Cnpj::digits($cnpj);

        if (! Cnpj::isValid($digits)) {
            throw new BrasilApiException('Informe um CNPJ válido');
        }

        $cached = Cache::get($this->cacheKey($digits));

        if (! is_array($cached) || ! is_array($cached['payload'] ?? null)) {
            $cached = $this->fetch($digits);
            Cache::put($this->cacheKey($digits), $cached, now()->addHours(self::CACHE_HOURS));
        }

        if (! $this->isActive($cached['payload'])) {
            throw new BrasilApiException('O CNPJ não está com situação cadastral ATIVA');
        }

        return [
            'payload' => $cached['payload'],
            'checked_at' => (string) $cached['checked_at'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function isActive(array $payload): bool
    {
        $description = mb_strtoupper(trim((string) ($payload['descricao_situacao_cadastral'] ?? '')));

        if ($description !== '') {
            return $description === 'ATIVA';
        }

        return (int) ($payload['situacao_cadastral'] ?? 0) === 2;
    }

    /**
     * @return array{payload: array<string, mixed>, checked_at: string}
     */
    private function fetch(string $digits): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->get($this->url($digits));
        } catch (ConnectionException) {
            throw $this->unavailable();
        }

        if (in_array($response->status(), [400, 404], true)) {
            throw new BrasilApiException('CNPJ não encontrado na Receita Federal');
        }

        if (! $response->successful()) {
            throw $this->unavailable();
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw $this->unavailable();
        }

        return [
            'payload' => $payload,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    private function url(string $digits): string
    {
        return 'https://brasilapi.com.br/api/cnpj/v1/'.$digits;
    }

    private function cacheKey(string $digits): string
    {
        return 'brasilapi:cnpj:'.$digits;
    }

    private function unavailable(): BrasilApiException
    {
        return new BrasilApiException(
            'A consulta de CNPJ está indisponível. Tente novamente em instantes',
            503,
        );
    }
}
