<?php

namespace App\Models\Fleet;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de rastreador. Catálogo global, mantido pela LogIV — sem
 * `company_id`.
 */
class DeviceModel extends Model
{
    protected $fillable = [
        'manufacturer',
        'name',
    ];

    public function label(): string
    {
        return "{$this->manufacturer} {$this->name}";
    }
}
