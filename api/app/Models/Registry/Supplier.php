<?php

namespace App\Models\Registry;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Override;
class Supplier extends Model
{
    use HasUuids;
    use BelongsToCompany;
    
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

    // ================================ OVERRIDE METHODS ================================

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
