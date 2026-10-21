<?php

namespace App\Models\Registry;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

use Override;

class FuelStation extends Model
{
    use HasUuids;
    use BelongsToCompany;
    use SoftDeletes;

    protected $table = 'fuel_stations';

    protected $fillable = [
        'company_id',
        'entity_id',
        'login',
        'password',
        'contact_name',
        'credit_limit',
        'default_payment_method',
        'pix_key_type',
        'pix_key',
        'diesel_s10_price',
        'diesel_s500_price',
        'gasoline_price',
        'additive_gasoline_price',
        'billing_recurrence',
        'billing_cutoff_day',
        'billing_last_day_of_month',
        'billing_interval_days',
        'billing_cycle_starts_on',
        'billing_due_days',
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
            'credit_limit' => 'decimal:2',
            'diesel_s10_price' => 'decimal:3',
            'diesel_s500_price' => 'decimal:3',
            'gasoline_price' => 'decimal:3',
            'additive_gasoline_price' => 'decimal:3',
            'billing_last_day_of_month' => 'boolean',
            'billing_cycle_starts_on' => 'date:Y-m-d',
        ];
    }

    // ================================ BELONGS TO RELATIONS ================================
    
    /**
     * The company that the fuel station belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The entity that the fuel station belongs to.
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
