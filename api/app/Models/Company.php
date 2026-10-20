<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use SoftDeletes;
    use HasUuids;

    protected $table = 'companies';

    protected $fillable = [
        'legal_name',
        'trade_name',
        'cnpj',
        'logo_path',
        'status',
    ];

    // ===================== HAS MANY RELATIONS =====================

    public function users(): HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    // ===================== UNIQUE IDS =====================

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    // ===================== ROUTE KEY NAME =====================
    
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
