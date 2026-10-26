<?php

namespace Tests\Feature\Access;

use App\Models\Company;
use App\Models\FuelStation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Helpers dos testes de usuários e convites: cria empresa, posto, pessoas
 * e um token já no contexto.
 */
trait ActsInContext
{
    protected function person(string $email, string $name = 'Pessoa'): User
    {
        return User::factory()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'senha-atual',
        ]);
    }

    protected function company(string $tradeName = 'Transportes X', ?string $cnpj = null): Company
    {
        return Company::query()->create([
            'legal_name' => $tradeName.' LTDA',
            'trade_name' => $tradeName,
            'cnpj' => $cnpj ?? substr(str_pad((string) random_int(1, PHP_INT_MAX), 14, '0'), 0, 14),
        ]);
    }

    protected function station(string $tradeName = 'Posto Exemplo', ?string $cnpj = null): FuelStation
    {
        return FuelStation::query()->create([
            'cnpj' => $cnpj ?? substr(str_pad((string) random_int(1, PHP_INT_MAX), 14, '0'), 0, 14),
            'corporate_name' => strtoupper($tradeName).' LTDA',
            'trade_name' => $tradeName,
            'status' => 'active',
        ]);
    }

    /**
     * Token com o contexto já escolhido, usado nas próximas requisições.
     */
    protected function actIn(User $user, Company|FuelStation|null $place = null): string
    {
        $this->app['auth']->forgetGuards();

        $token = $user->createToken('web');
        $token->accessToken->forceFill([
            'company_id' => $place instanceof Company ? $place->id : null,
            'fuel_station_id' => $place instanceof FuelStation ? $place->id : null,
        ])->save();

        $this->withToken($token->plainTextToken);

        return $token->plainTextToken;
    }

    protected function asGuest(): void
    {
        // `auth:sanctum` troca o guard padrão; o login usa o web.
        $this->app['auth']->shouldUse('web');
        $this->app['auth']->forgetGuards();
        $this->withoutToken();
    }

    protected function uuidNotFound(): string
    {
        return (string) Str::uuid();
    }
}
