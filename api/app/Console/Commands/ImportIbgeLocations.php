<?php

namespace App\Console\Commands;

use App\Models\Location\City;
use App\Models\Location\State;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportIbgeLocations extends Command
{
    protected $signature = 'ibge:import';

    protected $description = 'Importa estados e municípios oficiais do IBGE';

    private const STATES_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados';

    private const CITIES_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/municipios';

    public function handle(): int
    {
        if (! $this->importStates()) {
            return self::FAILURE;
        }

        if (! $this->importCities()) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function importStates(): bool
    {
        $this->info('Baixando estados do IBGE...');

        $response = Http::timeout(60)->get(self::STATES_URL, ['orderBy' => 'nome']);

        if ($response->failed()) {
            $this->error('Não foi possível baixar os estados do IBGE.');

            return false;
        }

        $now = now();
        $rows = [];

        foreach ($response->json() as $state) {
            $rows[] = [
                'ibge_code' => $state['id'],
                'name' => $state['nome'],
                'uf' => $state['sigla'],
                'region' => $state['regiao']['nome'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        State::upsert($rows, ['ibge_code'], ['name', 'uf', 'region', 'updated_at']);

        $this->info(count($rows) . ' estados importados.');

        return true;
    }

    private function importCities(): bool
    {
        $this->info('Baixando municípios do IBGE...');

        $response = Http::timeout(120)->get(self::CITIES_URL, ['orderBy' => 'nome']);

        if ($response->failed()) {
            $this->error('Não foi possível baixar os municípios do IBGE.');

            return false;
        }

        // Mapa código IBGE do estado => id interno, para resolver a FK de cada município.
        $stateIdByIbge = State::query()->pluck('id', 'ibge_code');
        $now = now();
        $rows = [];

        foreach ($response->json() as $city) {
            $ufIbge = $city['microrregiao']['mesorregiao']['UF']['id']
                ?? $city['regiao-imediata']['regiao-intermediaria']['UF']['id']
                ?? null;

            $stateId = $ufIbge ? ($stateIdByIbge[$ufIbge] ?? null) : null;

            if (! $stateId) {
                $this->warn("Município {$city['nome']} sem estado correspondente. Ignorado.");

                continue;
            }

            $rows[] = [
                'ibge_code' => $city['id'],
                'state_id' => $stateId,
                'name' => $city['nome'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            City::upsert($chunk, ['ibge_code'], ['state_id', 'name', 'updated_at']);
        }

        $this->info(count($rows) . ' municípios importados.');

        return true;
    }
}
