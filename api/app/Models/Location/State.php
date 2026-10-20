<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class State extends Model
{
    protected $table = 'states';

    protected $fillable = [
        'ibge_code',
        'name',
        'uf',
        'region',
    ];

    // ================================ HAS MANY RELATIONS ================================

    /**
     * The cities that belong to the state.
     */
    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }
}
