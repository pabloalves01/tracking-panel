<?php

namespace App\Services\External;

use App\Rules\Cnpj;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BrasilApiService
{
    /**
     * Consulta o CNPJ na BrasilAPI.
     * Só devolve empresa ATIVA. A resposta fica em cache por 24 horas.
     *
     * @return array<string, mixed>
     */
    public function cnpj(string $cnpj): array
    {
        $digits = Cnpj::digits($cnpj);

        if (! Cnpj::isValid($digits)) {
            abort(422, 'Informe um CNPJ válido');
        }

        $payload = Cache::remember('brasilapi:cnpj:'.$digits, now()->addHours(24), function () use ($digits) {
            return $this->fetch($digits);
        });

        $status = mb_strtoupper(trim((string) ($payload['descricao_situacao_cadastral'] ?? '')));

        if ($status !== 'ATIVA') {
            abort(422, 'O CNPJ não está com situação cadastral ATIVA');
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(string $digits): array
    {
        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->get('https://brasilapi.com.br/api/cnpj/v1/'.$digits);
        } catch (ConnectionException) {
            abort(503, 'A consulta de CNPJ está indisponível. Tente novamente em instantes');
        }

        if (in_array($response->status(), [400, 404], true)) {
            abort(422, 'CNPJ não encontrado na Receita Federal');
        }

        $payload = $response->json();

        if (! $response->successful() || ! is_array($payload)) {
            abort(503, 'A consulta de CNPJ está indisponível. Tente novamente em instantes');
        }

        return $payload;
    }
}
