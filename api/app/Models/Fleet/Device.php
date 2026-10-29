<?php

namespace App\Models\Fleet;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * Rastreador. Existe sem veículo; a instalação fica em
 * `DeviceInstallation`.
 */
class Device extends Model
{
    use BelongsToCompany;
    use HasUuids;
    use SoftDeletes;

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    protected $fillable = [
        'device_model_id',
        'imei',
        'tracking_id',
        'sim_number',
        'sim_iccid',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'device_model_id' => 'integer',
        ];
    }

    protected function imei(): Attribute
    {
        return Attribute::set(fn (mixed $value) => Text::digits($value));
    }

    protected function trackingId(): Attribute
    {
        return Attribute::set(fn (mixed $value) => filled($value) ? Text::plain($value) : null);
    }

    protected function simNumber(): Attribute
    {
        return Attribute::set(fn (mixed $value) => filled($value) ? Text::digits($value) : null);
    }

    protected function simIccid(): Attribute
    {
        return Attribute::set(fn (mixed $value) => filled($value) ? Text::digits($value) : null);
    }

    // ================================ RELATIONS ================================

    public function deviceModel(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class);
    }

    /** A instalação de agora — um veículo por vez. */
    public function installation(): HasOne
    {
        return $this->hasOne(DeviceInstallation::class)->whereNull('removed_at');
    }

    // ================================ SCOPES ================================

    /**
     * IMEI, identificador, número do chip, ICCID ou a placa do veículo em que está. Com
     * letras, é placa.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = Text::plain($term);

        if ($term === '') {
            return $query;
        }

        if (preg_match('/\pL/u', $term) === 1) {
            $plate = Vehicle::normalizePlate($term);

            return $query->whereHas(
                'installation.vehicle',
                fn (Builder $vehicle) => $vehicle->whereLike('plate', "%{$plate}%")
            );
        }

        $digits = Text::digits($term);

        return $query->where(fn (Builder $scoped) => $scoped
            ->whereLike('imei', "%{$digits}%")
            ->orWhereLike('tracking_id', "%{$digits}%")
            ->orWhereLike('sim_number', "%{$digits}%")
            ->orWhereLike('sim_iccid', "%{$digits}%"));
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status === null ? $query : $query->where('status', $status);
    }

    /** `true`: só os instalados; `false`: só os disponíveis. */
    public function scopeInstalled(Builder $query, ?bool $installed): Builder
    {
        return match ($installed) {
            true => $query->whereHas('installation'),
            false => $query->whereDoesntHave('installation'),
            null => $query,
        };
    }

    // ================================ HELPERS ================================

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
