<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * O convênio entre uma empresa e um posto.
 *
 * Ciclo: a empresa pede (request_sent); o posto aceita (active) ou recusa
 * (rejected); qualquer um dos dois encerra (disconnected). De rejected ou
 * disconnected, a empresa pode reenviar.
 */
class FuelStationAgreement extends Model
{
    use HasUuids;

    public const REQUEST_SENT = 'request_sent';

    public const ACTIVE = 'active';

    public const REJECTED = 'rejected';

    public const DISCONNECTED = 'disconnected';

    protected $fillable = [
        'company_id',
        'fuel_station_id',
        'status',
    ];

    public function company()
    {
        return $this->belongsTo(Organization::class, 'company_id');
    }

    public function fuelStation()
    {
        return $this->belongsTo(Organization::class, 'fuel_station_id');
    }

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
