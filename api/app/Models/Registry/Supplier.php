<?php

namespace App\Models\Registry;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = 'suppliers';

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
