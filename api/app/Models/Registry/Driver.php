<?php

namespace App\Models\Registry;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Override;
use Illuminate\Database\Eloquent\SoftDeletes;
class Driver extends Model
{
    use HasUuids;
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'drivers';

    protected $fillable = [
        'company_id',
        'entity_id',
        'license_number',
        'license_category',
        'license_expires_on',
        'license_issued_state',
        'residence_proof_path',
        'app_password',
        'status',
    ];

    protected $hidden = [
        'app_password',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'license_expires_on' => 'date:Y-m-d',
            'app_password' => 'hashed',
        ];
    }

    // ================================ BELONGS TO RELATIONS ================================

    /**
     * The company that the driver belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The entity that the driver belongs to.
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
