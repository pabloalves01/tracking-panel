<?php

use App\Http\Controllers\Auth\AuthController;
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

// =============================== AUTH ROUTES ===============================
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('companies', [AuthController::class, 'companies']);
        Route::post('company', [AuthController::class, 'selectCompany']);
    });
});

Route::middleware(['auth:sanctum', 'ensure.company'])->group(function () {
    Route::group(['prefix' => '/system'], function () {
        Route::group(['prefix' => '/companies'], function () {
            Route::get('/', [CompanyController::class, 'index']);
            Route::post('/', [CompanyController::class, 'store']);
            Route::get('/{uuid}', [CompanyController::class, 'show']);
            Route::put('/{uuid}', [CompanyController::class, 'update']);
            Route::delete('/{uuid}', [CompanyController::class, 'destroy']);
        });
    });
});

// =============================== SYSTEM ROUTES ===============================
