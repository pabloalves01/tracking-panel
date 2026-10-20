<?php

namespace App\Models\Registry;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

class FuelStation extends Model
{
    protected $table = 'fuel_stations';

    protected $fillable = [
        'company_id',
        'entity_id',
        'name',
    ];

    // ================================ BELONGS TO RELATIONS ================================
    
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function entity()
    {
        return $this->belongsTo(CommonEntity::class);
    }
}
