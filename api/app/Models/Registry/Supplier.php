<?php

namespace App\Models\Registry;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Override;
use Illuminate\Database\Eloquent\SoftDeletes;
class Supplier extends Model
{
    use HasUuids;
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'suppliers';

    protected $fillable = [
        'company_id',
        'entity_id',
        'pix_key_type',
        'pix_key',
        'login',
        'password',
        'status',
    ];

    protected $hidden = [
        'password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // ================================ BELONGS TO RELATIONS ================================
    
    /**
     * The company that the supplier belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The entity that the supplier belongs to.
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
