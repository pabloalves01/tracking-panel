<?php

namespace App\Http\Resources\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * O usuário autenticado, sem a senha.
 *
 * A organização ativa saiu daqui: ela vem em `current_organization` na
 * resposta de `/auth/me`, junto com as organizações disponíveis. Antes este
 * resource consultava o banco duas vezes para montar `current_company` e
 * `current_fuel_station`, e o client ainda remontava o estado a partir de
 * outras três rotas.
 *
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'email' => $this->email,
            'is_system_admin' => (bool) $this->is_system_admin,
        ];
    }
}
