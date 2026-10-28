<?php

namespace App\Models;

use App\Enums\OrganizationType;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * Aquilo a que uma conta pertence: hoje uma empresa ou um posto.
 *
 * Substitui `Company` e `FuelStation`, que eram a mesma coisa com nomes
 * diferentes para os mesmos campos.
 */
class Organization extends Model
{
    use HasUuids;
    use SoftDeletes;

    public const ACTIVE = 'active';

    public const INACTIVE = 'inactive';

    protected $fillable = [
        'type',
        'document',
        'legal_name',
        'trade_name',
        'status',
        'postal_code',
        'street',
        'street_number',
        'complement',
        'neighborhood',
        'state_id',
        'city_id',
        'logo_path',
        'receita_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'receita_checked_at' => 'datetime',
        ];
    }

    /** O documento é sempre guardado só com dígitos. */
    protected function document(): Attribute
    {
        return Attribute::set(fn (mixed $value) => Text::digits($value));
    }

    // ================================ RELATIONS ================================

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->withPivot(['status'])
            ->withTimestamps();
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class);
    }

    /** Postos conveniados — só faz sentido para uma empresa. */
    public function stationLinks()
    {
        return $this->hasMany(FuelSupplyLink::class, 'company_id');
    }

    /** Empresas atendidas — só faz sentido para um posto. */
    public function companyLinks()
    {
        return $this->hasMany(FuelSupplyLink::class, 'station_id');
    }

    // ================================ SCOPES ================================

    public function scopeCompanies(Builder $query): Builder
    {
        return $query->where('type', OrganizationType::Company);
    }

    public function scopeFuelStations(Builder $query): Builder
    {
        return $query->where('type', OrganizationType::FuelStation);
    }

    // ================================ HELPERS ================================

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    /** O nome que o usuário reconhece. */
    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
