<?php

namespace App\Models\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Concerns\IsEntityRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Responsável por veículo. Os dados da pessoa ficam em `Entity`; as colunas próprias do
 * papel entram aqui conforme ele as ganhar.
 */
class VehicleResponsible extends Model
{
    use IsEntityRole;

    public function role(): EntityRole
    {
        return EntityRole::VehicleResponsible;
    }
}
