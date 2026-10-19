<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CompanyUser extends Model
{
    use SoftDeletes;

    protected $table = 'company_user';

    protected $fillable = [
        'company_id',
        'user_id',
        'role',
    ];

    // ===================== BELONGS TO RELATIONS =====================

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
