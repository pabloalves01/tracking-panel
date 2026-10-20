<?php

namespace App\Models\Registry;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Override;

class VehicleOwner extends Model
{
    use HasUuids;
    use BelongsToCompany;

    protected $table = 'vehicle_owners';

    protected $fillable = [
        'company_id',
        'entity_id',
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

    // ================================ OVERRIDE METHODS ================================

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
