<?php

namespace App\Models\Registry;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

class CommonEntity extends Model
{
    protected $table = 'common_entities';

    protected $fillable = [
        'company_id',
        'name',
        'trade_name',
        'person_type',
        'cpf_cnpj',
        'email',
        'phone',
    ];

    // ================================ BELONGS TO RELATIONS ================================
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // ================================ HAS ONE RELATIONS ================================

    public function fuelStation()
    {
        return $this->hasOne(FuelStation::class, 'entity_id');
    }

    // public function supplier()
    // {
    //     return $this->hasOne(Supplier::class);
    // }

    // public function employee()
    // {
    //     return $this->hasOne(Employee::class);
    // }
}
