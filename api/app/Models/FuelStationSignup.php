<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class FuelStationSignup extends Model
{
    use HasUuids;

    protected $table = 'fuel_station_signups';

    protected $fillable = [
        'cnpj',
        'email',
        'name',
        'password',
        'receita_payload',
        'code_hash',
        'code_expires_at',
    ];

    protected $hidden = [
        'password',
        'code_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receita_payload' => 'array',
            'password' => 'hashed',
            'code_hash' => 'hashed',
            'code_expires_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
