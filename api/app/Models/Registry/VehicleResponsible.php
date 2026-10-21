<?php

namespace App\Models\Registry;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Override;
use Illuminate\Database\Eloquent\SoftDeletes;
class VehicleResponsible extends Model
{
    use HasUuids;
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'vehicle_responsibles';

    protected $fillable = [
        'company_id',
        'entity_id',
        'pix_key_type',
        'pix_key',
        'status',
    ];

    // ================================ BELONGS TO RELATIONS ================================
    
    /**
     * The company that the vehicle responsible belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The entity that the vehicle responsible belongs to.
     */
    public function entity()
    {
        return $this->belongsTo(CommonEntity::class);
    }

    // ================================ OVERRIDE METHODS ================================

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
