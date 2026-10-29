<?php

namespace App\Models\Fleet;

use App\Enums\Fleet\InstallationRole;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rastreador instalado num veículo. `removed_at` nulo é a instalação de
 * agora; preenchido, é histórico.
 */
class DeviceInstallation extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'device_id',
        'vehicle_id',
        'role',
        'installed_at',
        'removed_at',
    ];

    protected function casts(): array
    {
        return [
            'device_id' => 'integer',
            'vehicle_id' => 'integer',
            'role' => InstallationRole::class,
            'installed_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }
}
