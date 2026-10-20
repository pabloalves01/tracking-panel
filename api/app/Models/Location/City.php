<?php

namespace App\Models\Location;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class City extends Model
{
    protected $table = 'cities';

    protected $fillable = [
        'ibge_code',
        'state_id',
        'name',
    ];

    // ================================ BELONGS TO RELATIONS ================================

    /**
     * The state that the city belongs to.
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }
}
