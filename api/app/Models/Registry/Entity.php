<?php

namespace App\Models\Registry;

use App\Enums\EntityRole;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Location\City;
use App\Models\Location\State;
use App\Models\Organization;
use App\Support\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Override;

/**
 * Uma pessoa física ou jurídica cadastrada pela empresa. Cada papel que ela
 * exerce é uma linha na tabela do papel (`drivers`, `customers`…).
 */
class Entity extends Model
{
    use BelongsToCompany;
    use HasUuids;
    use SoftDeletes;

    public const INDIVIDUAL = 'individual';

    public const ORGANIZATION = 'organization';

    protected $fillable = [
        'company_id',
        'kind',
        'document',
        'name',
        'trade_name',
        'email',
        'phone',
        'emergency_phone',
        'emergency_phone_type',
        'national_id',
        'state_registration',
        'pix_key_type',
        'pix_key',
        'postal_code',
        'street',
        'street_number',
        'complement',
        'neighborhood',
        'state_id',
        'city_id',
    ];

    protected function casts(): array
    {
        return [
            'state_id' => 'integer',
            'city_id' => 'integer',
        ];
    }

    /**
     * O documento é sempre guardado só com dígitos. O cast vale para qualquer
     * caminho de escrita — formulário, seeder, comando — e não só para a
     * FormRequest.
     */
    protected function document(): Attribute
    {
        return Attribute::set(fn (mixed $value) => Text::digits($value));
    }

    protected function emergencyPhone(): Attribute
    {
        return Attribute::set(fn (mixed $value) => filled($value) ? Text::digits($value) : null);
    }

    // ================================ RELATIONS ================================

    public function company()
    {
        return $this->belongsTo(Organization::class, 'company_id');
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    // Um papel por relação. Os nomes saem de `EntityRole::relation()`.

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function vehicleOwner(): HasOne
    {
        return $this->hasOne(VehicleOwner::class);
    }

    public function vehicleResponsible(): HasOne
    {
        return $this->hasOne(VehicleResponsible::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function supplier(): HasOne
    {
        return $this->hasOne(Supplier::class);
    }

    public function customer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    public function carrier(): HasOne
    {
        return $this->hasOne(Carrier::class);
    }

    // ================================ SCOPES ================================

    public function scopeWithRole(Builder $query, EntityRole $role): Builder
    {
        return $query->whereHas($role->relation());
    }

    public function scopeWithAllRoles(Builder $query): Builder
    {
        return $query->with(EntityRole::relations());
    }

    /**
     * Busca por nome, nome fantasia ou documento. O termo é comparado contra
     * o documento já sem máscara, para que "123.456" encontre "123456".
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = Text::plain($term);

        if ($term === '') {
            return $query;
        }

        $digits = Text::digits($term);

        return $query->where(function (Builder $scoped) use ($term, $digits) {
            $scoped->whereLike('name', "%{$term}%")
                ->orWhereLike('trade_name', "%{$term}%");

            if ($digits !== '') {
                $scoped->orWhereLike('document', "%{$digits}%");
            }
        });
    }

    // ================================ HELPERS ================================

    /**
     * Os papéis que a pessoa exerce, já carregados — os excluídos não vêm.
     *
     * @return Collection<int, Model>
     */
    public function roleRecords(): Collection
    {
        $this->loadMissing(EntityRole::relations());

        return collect(EntityRole::cases())
            ->map(fn (EntityRole $role) => $this->getRelation($role->relation()))
            ->filter()
            ->values();
    }

    public function isOrganization(): bool
    {
        return $this->kind === self::ORGANIZATION;
    }

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
