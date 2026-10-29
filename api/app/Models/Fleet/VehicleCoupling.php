<?php

namespace App\Models\Fleet;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Implemento engatado numa tração, numa posição. `uncoupled_at` nulo é o
 * engate de agora; preenchido, é histórico.
 */
class VehicleCoupling extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'traction_vehicle_id',
        'trailer_vehicle_id',
        'position',
        'coupled_at',
        'uncoupled_at',
    ];

    protected function casts(): array
    {
        return [
            'traction_vehicle_id' => 'integer',
            'trailer_vehicle_id' => 'integer',
            'position' => 'integer',
            'coupled_at' => 'datetime',
            'uncoupled_at' => 'datetime',
        ];
    }

    public function traction(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'traction_vehicle_id')->withTrashed();
    }

    public function trailer(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'trailer_vehicle_id')->withTrashed();
    }

    public function uncouple(): void
    {
        $this->update(['uncoupled_at' => now()]);
    }
}
