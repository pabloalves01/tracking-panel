<?php

namespace App\Models\Registry;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Override;

class Driver extends Model
{
    use HasUuids;
    use BelongsToCompany;
    
    protected $table = 'drivers';

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
