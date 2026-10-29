<?php

namespace App\Models\Registry;

use App\Enums\EntityRole;
use App\Models\Registry\Concerns\IsEntityRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Transportadora. Os dados da pessoa ficam em `Entity`; as colunas próprias do
 * papel entram aqui conforme ele as ganhar.
 */
class Carrier extends Model
{
    use IsEntityRole;

    public function role(): EntityRole
    {
        return EntityRole::Carrier;
    }
}
