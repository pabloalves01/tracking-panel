<?php

namespace App\Models\Operation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use MatanYadaev\EloquentSpatial\Objects\Polygon;
use MatanYadaev\EloquentSpatial\Traits\HasSpatial;

class ShipOperationPerimeter extends Model
{
    use HasSpatial;
    use SoftDeletes;

    protected $table = 'ship_operations_perimeter';

    protected $fillable = [
        'ship_operation_id',
        'perimeter',
    ];

    protected $casts = [
        'perimeter' => Polygon::class,
    ];
}
