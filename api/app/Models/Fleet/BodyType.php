<?php

namespace App\Models\Fleet;

use Illuminate\Database\Eloquent\Model;

/**
 * Carroceria. Catálogo global, mantido pela LogIV.
 */
class BodyType extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'mdfe_code',
    ];
}
