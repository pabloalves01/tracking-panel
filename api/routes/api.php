<?php

use App\Http\Controllers\Operations\ShipOperationPerimeterController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'up',
    ]);
});

Route::get('/ship-operation-perimeters/{shipOperationId}', [ShipOperationPerimeterController::class, 'get']);
Route::post('/ship-operation-perimeters', [ShipOperationPerimeterController::class, 'create']);