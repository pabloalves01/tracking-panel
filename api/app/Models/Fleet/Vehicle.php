<?php

namespace App\Models\Fleet;

use App\Enums\Fleet\FleetType;
use App\Enums\Fleet\VehicleKind;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Registry\Carrier;
use App\Models\Registry\VehicleOwner;
use App\Models\Registry\VehicleResponsible;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * Veículo da empresa: automotor ou implemento, conforme o tipo.
 */
class Vehicle extends Model
{
    use BelongsToCompany;
    use HasUuids;
    use SoftDeletes;

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    protected $fillable = [
        'vehicle_type_id',
        'body_type_id',
        'fleet_type',
        'plate',
        'plate_state',
        'renavam',
        'chassis',
        'registration_expires_on',
        'vehicle_brand_id',
        'vehicle_model_id',
        'color',
        'manufacture_year',
        'model_year',
        'axle_count',
        'tare_weight_kg',
        'load_capacity_kg',
        'gross_weight_kg',
        'max_traction_weight_kg',
        'volume_capacity_m3',
        'owner_id',
        'carrier_id',
        'responsible_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_type_id' => 'integer',
            'body_type_id' => 'integer',
            'vehicle_brand_id' => 'integer',
            'vehicle_model_id' => 'integer',
            'fleet_type' => FleetType::class,
            'registration_expires_on' => 'date',
            'manufacture_year' => 'integer',
            'model_year' => 'integer',
            'axle_count' => 'integer',
            'tare_weight_kg' => 'integer',
            'load_capacity_kg' => 'integer',
            'gross_weight_kg' => 'integer',
            'max_traction_weight_kg' => 'integer',
            'volume_capacity_m3' => 'decimal:2',
        ];
    }

    /** "abc-1d23" e "ABC1D23" são a mesma placa. */
    protected function plate(): Attribute
    {
        return Attribute::set(fn (mixed $value) => self::normalizePlate($value));
    }

    protected function plateState(): Attribute
    {
        return Attribute::set(fn (mixed $value) => mb_strtoupper(Text::plain($value)));
    }

    protected function chassis(): Attribute
    {
        return Attribute::set(fn (mixed $value) => mb_strtoupper(Text::plain($value)));
    }

    /** O RENAVAM antigo tinha 9 dígitos; com zeros à esquerda vira o de 11. */
    protected function renavam(): Attribute
    {
        return Attribute::set(fn (mixed $value) => str_pad(Text::digits($value), 11, '0', STR_PAD_LEFT));
    }

    public static function normalizePlate(mixed $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(Text::plain($value))) ?? '';
    }

    // ================================ RELATIONS ================================

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function bodyType(): BelongsTo
    {
        return $this->belongsTo(BodyType::class);
    }

    public function vehicleBrand(): BelongsTo
    {
        return $this->belongsTo(VehicleBrand::class);
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(VehicleOwner::class)->withTrashed();
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(VehicleResponsible::class)->withTrashed();
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(Carrier::class)->withTrashed();
    }

    /** Os implementos engatados agora, na ordem. */
    public function trailerCouplings(): HasMany
    {
        return $this->hasMany(VehicleCoupling::class, 'traction_vehicle_id')
            ->whereNull('uncoupled_at')
            ->orderBy('position');
    }

    /** A tração em que este implemento está engatado agora. */
    public function tractionCoupling(): HasOne
    {
        return $this->hasOne(VehicleCoupling::class, 'trailer_vehicle_id')->whereNull('uncoupled_at');
    }

    /** Os rastreadores instalados agora. */
    public function installations(): HasMany
    {
        return $this->hasMany(DeviceInstallation::class)->whereNull('removed_at');
    }

    // ================================ SCOPES ================================

    /**
     * Placa, RENAVAM, chassi, marca, modelo ou o proprietário. Letras com
     * números ("abc1d") são placa ou chassi: não procuram no documento do
     * proprietário, onde o "1" acharia todo mundo.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = Text::plain($term);

        if ($term === '') {
            return $query;
        }

        $code = self::normalizePlate($term);
        $mixed = preg_match('/\d/', $term) === 1 && preg_match('/\pL/u', $term) === 1;

        return $query->where(function (Builder $scoped) use ($term, $code, $mixed) {
            $scoped->whereHas('vehicleBrand', fn (Builder $brand) => $brand->whereLike('name', "%{$term}%"))
                ->orWhereHas('vehicleModel', fn (Builder $model) => $model->whereLike('name', "%{$term}%"));

            if ($code !== '') {
                $scoped->orWhereLike('plate', "%{$code}%")
                    ->orWhereLike('chassis', "%{$code}%")
                    ->orWhereLike('renavam', "%{$code}%");
            }

            if (! $mixed) {
                $scoped->orWhereHas('owner.entity', fn (Builder $entity) => $entity->search($term));
            }
        });
    }

    public function scopeOfKind(Builder $query, ?string $kind): Builder
    {
        return $kind === null ? $query : $query->whereHas('vehicleType', fn (Builder $type) => $type->where('kind', $kind));
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return $status === null ? $query : $query->where('status', $status);
    }

    // ================================ HELPERS ================================

    public function kind(): VehicleKind
    {
        return $this->vehicleType->kind;
    }

    public function canTow(): bool
    {
        return $this->vehicleType->can_tow;
    }

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
