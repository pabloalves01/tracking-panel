<?php

use App\Http\Controllers\Operations\ShipOperationPerimeterController;
use App\Http\Controllers\System\Company\CompanyController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'up',
    ]);
});




Route::get('/ship-operation-perimeters/{shipOperationId}', [ShipOperationPerimeterController::class, 'get']);
Route::post('/ship-operation-perimeters', [ShipOperationPerimeterController::class, 'create']);

// =============================== SYSTEM ROUTES ===============================

Route::group(['prefix' => '/system'], function () {
    Route::group(['prefix' => '/companies'], function () {
        Route::get('/', [CompanyController::class, 'index']);
        Route::post('/', [CompanyController::class, 'store']);
        Route::get('/{uuid}', [CompanyController::class, 'show']);
        Route::put('/{uuid}', [CompanyController::class, 'update']);
        Route::delete('/{uuid}', [CompanyController::class, 'destroy']);
    });
});
