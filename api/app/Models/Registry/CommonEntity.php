<?php

namespace App\Models\Registry;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
class CommonEntity extends Model
{
    use HasUuids;
    use SoftDeletes;
    use BelongsToCompany;

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

    /**
     * The fuel station that the entity belongs to.
     */
    public function fuelStation()
    {
        return $this->hasOne(FuelStation::class, 'entity_id');
    }

    /**
     * The supplier that the entity belongs to.
     */
    public function supplier()
    {
        return $this->hasOne(Supplier::class);
    }

    /**
     * The employee that the entity belongs to.
     */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }

    /**
     * The driver that the entity belongs to.
     */
    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * The vehicle owner that the entity belongs to.
     */
    public function vehicleOwner()
    {
        return $this->hasOne(VehicleOwner::class);
    }

    /**
     * The vehicle responsible that the entity belongs to.
     */
    public function vehicleResponsible()
    {
        return $this->hasOne(VehicleResponsible::class);
    }

    // ================================ OVERRIDE METHODS ================================

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
