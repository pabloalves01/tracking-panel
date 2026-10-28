<?php

namespace App\Models\Registry;

use App\Enums\EntityRole;
use App\Models\Organization;
use App\Models\Concerns\BelongsToCompany;
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
 * Uma pessoa física ou jurídica cadastrada pela empresa. Os papéis que ela
 * exerce ficam em `entity_roles`.
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

    public function roles()
    {
        return $this->hasMany(EntityRoleAssignment::class);
    }

    public function driverDetails()
    {
        return $this->hasOne(DriverDetails::class);
    }

    // ================================ SCOPES ================================

    /**
     * Entidades que exercem um papel, com a situação do papel opcionalmente
     * filtrada.
     */
    public function scopeWithRole(Builder $query, EntityRole $role, ?string $status = null): Builder
    {
        return $query->whereHas('roles', function (Builder $roles) use ($role, $status) {
            $roles->where('role', $role->value);

            if ($status !== null) {
                $roles->where('status', $status);
            }
        });
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
            $scoped->where('name', 'like', "%{$term}%")
                ->orWhere('trade_name', 'like', "%{$term}%");

            if ($digits !== '') {
                $scoped->orWhere('document', 'like', "%{$digits}%");
            }
        });
    }

    // ================================ HELPERS ================================

    public function hasRole(EntityRole $role): bool
    {
        return $this->roles->contains(fn (EntityRoleAssignment $assignment) => $assignment->role === $role);
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
