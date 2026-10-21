<?php

namespace App\Models\Registry;

use App\Models\Company;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Location\City;
use App\Models\Location\State;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
class CommonEntity extends Model
{
    use HasUuids;
    use SoftDeletes;
    use BelongsToCompany;

    protected $table = 'common_entities';

    protected $fillable = [
        'company_id',
        'name',
        'trade_name',
        'person_type',
        'cpf_cnpj',
        'email',
        'phone',
        'rg_ie',
        'birth_date',
        'emergency_phone',
        'emergency_phone_type',
        'document_file_path',
        'postal_code',
        'street',
        'street_number',
        'complement',
        'neighborhood',
        'city_id',
        'state_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state_id' => 'integer',
            'city_id' => 'integer',
            'birth_date' => 'date:Y-m-d',
        ];
    }

    // ================================ BELONGS TO RELATIONS ================================
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The city that the entity belongs to.
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * The state that the entity belongs to.
     */
    public function state()
    {
        return $this->belongsTo(State::class);
    }

    // ================================ HAS ONE RELATIONS ================================

    /**
     * The fuel station that the entity belongs to.
     */
    public function fuelStation()
    {
        return $this->hasOne(FuelStation::class, 'entity_id');
    }

    /**
     * The supplier that the entity belongs to.
     */
    public function supplier()
    {
        return $this->hasOne(Supplier::class, 'entity_id');
    }

    /**
     * The employee that the entity belongs to.
     */
    public function employee()
    {
        return $this->hasOne(Employee::class, 'entity_id');
    }

    /**
     * The carrier that the entity belongs to.
     */
    public function carrier()
    {
        return $this->hasOne(Carrier::class, 'entity_id');
    }

    /**
     * The customer that the entity belongs to.
     */
    public function customer()
    {
        return $this->hasOne(Customer::class, 'entity_id');
    }

    /**
     * The driver that the entity belongs to.
     */
    public function driver()
    {
        return $this->hasOne(Driver::class, 'entity_id');
    }

    /**
     * The vehicle owner that the entity belongs to.
     */
    public function vehicleOwner()
    {
        return $this->hasOne(VehicleOwner::class, 'entity_id');
    }

    /**
     * The vehicle responsible that the entity belongs to.
     */
    public function vehicleResponsible()
    {
        return $this->hasOne(VehicleResponsible::class, 'entity_id');
    }

    // ================================ OVERRIDE METHODS ================================

    #[Override]
    public function uniqueIds()
    {
        return ['uuid'];
    }
}
