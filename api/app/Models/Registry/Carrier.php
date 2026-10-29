<?php

namespace App\Models\Registry;

use App\Enums\AnttCategory;
use App\Enums\EntityRole;
use App\Models\Registry\Concerns\IsEntityRole;
use Illuminate\Database\Eloquent\Model;

/**
 * Transportador — empresa (ETC), cooperativa (CTC) ou autônomo (TAC). Os
 * dados da pessoa ficam em `Entity`; aqui, o registro dele na ANTT.
 *
 * O proprietário autônomo que transporta com o próprio caminhão é
 * proprietário e transportador: a mesma pessoa nos dois papéis.
 */
class Carrier extends Model
{
    use IsEntityRole;

    protected $fillable = [
        'rntrc',
        'antt_category',
        'rntrc_expires_on',
    ];

    protected function casts(): array
    {
        return [
            'antt_category' => AnttCategory::class,
            'rntrc_expires_on' => 'date',
        ];
    }

    public function role(): EntityRole
    {
        return EntityRole::Carrier;
    }
}
