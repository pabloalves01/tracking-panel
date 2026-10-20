<?php

namespace App\Models\Registry;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

class Carrier extends Model
{
    use HasUuids;
    use BelongsToCompany;
    use SoftDeletes;
    
    protected $table = 'carriers';
    protected $fillable = [
        'company_id',
        'entity_id',
    ];

    // ============================== BELONGS TO RELATIONS ==============================


    /**
     * The company that the carrier belongs to.
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }   

    /**
     * The entity that the carrier belongs to.
     */
    public function entity(): BelongsTo
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
