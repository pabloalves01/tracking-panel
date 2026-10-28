<?php

namespace App\Models\Registry;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class Vehicle extends Model
{
    use BelongsToCompany;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'vehicles';

    protected $fillable = [
        'company_id',
        'vehicle_owner_id',
        'vehicle_responsible_id',
        'plate',
        'status',
    ];

    // ================================ BELONGS TO RELATIONS ================================

    /**
     * The company that the vehicle belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Organization::class, 'company_id');
    }

    /**
     * The owner of the vehicle.
     */
    public function owner()
    {
        return $this->belongsTo(VehicleOwner::class, 'vehicle_owner_id');
    }

    /**
     * The responsible of the vehicle.
     */
    public function responsible()
    {
        return $this->belongsTo(VehicleResponsible::class, 'vehicle_responsible_id');
    }
    // ================================ OVERRIDE METHODS ================================

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
