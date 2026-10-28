<?php

use App\Http\Controllers\Access\InvitationController;
use App\Http\Controllers\Access\MemberController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\FuelStationSignupController;
use App\Http\Controllers\Auth\InvitationAcceptController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerificationCodeController;
use App\Http\Controllers\FuelStation\StationCompanyController;
use App\Http\Controllers\FuelStation\StationController;
use App\Http\Controllers\Location\LocationController;
use App\Http\Controllers\Operations\ShipOperationPerimeterController;
use App\Http\Controllers\Registry\EntityController;
use App\Http\Controllers\Registry\FuelStationLinkController;
use App\Http\Controllers\Registry\VehicleController;
use App\Http\Controllers\System\Company\CompanyAccessController;
use App\Http\Controllers\System\Company\CompanyController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'up',
    ]);
});

// =============================== AUTH ROUTES ===============================

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1,login');
    Route::post('verification-codes', [VerificationCodeController::class, 'store'])
        ->middleware('throttle:10,1,verification-send');
    Route::post('password/forgot', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:5,1,password-forgot');
    Route::post('password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1,password-reset');

    Route::prefix('station-signup')->group(function () {
        Route::get('cnpj/{cnpj}', [FuelStationSignupController::class, 'cnpj'])
            ->middleware('throttle:20,1,station-signup-cnpj');
        Route::post('/', [FuelStationSignupController::class, 'store'])
            ->middleware('throttle:20,1,station-signup');
    });

    Route::prefix('invitations/{token}')->group(function () {
        Route::get('/', [InvitationAcceptController::class, 'show'])
            ->middleware('throttle:20,1,invitation-show');
        Route::post('register', [InvitationAcceptController::class, 'register'])
            ->middleware('throttle:20,1,invitation-register');
        Route::post('decline', [InvitationAcceptController::class, 'decline'])
            ->middleware('throttle:20,1,invitation-decline');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('companies', [AuthController::class, 'companies']);
        Route::post('company', [AuthController::class, 'selectCompany']);
        Route::get('contexts', [AuthController::class, 'contexts']);
        Route::post('station', [AuthController::class, 'selectStation']);
        Route::post('stations', [FuelStationSignupController::class, 'storeForUser'])
            ->middleware('throttle:20,1,station-create');
        Route::post('invitations/{token}/accept', [InvitationAcceptController::class, 'accept'])
            ->middleware('throttle:20,1,invitation-accept');
        Route::get('pending-invitations', [InvitationAcceptController::class, 'mine']);
        Route::post('pending-invitations/{uuid}/accept', [InvitationAcceptController::class, 'acceptMine']);
        Route::post('pending-invitations/{uuid}/decline', [InvitationAcceptController::class, 'declineMine']);
    });
});

// =============================== LOCATION ROUTES ===============================

Route::middleware('auth:sanctum')->prefix('locations')->group(function () {
    Route::get('states', [LocationController::class, 'states']);
    Route::get('states/{state}/cities', [LocationController::class, 'cities']);
});

// =============================== API COMPANY ROUTES ===============================

Route::middleware(['auth:sanctum', 'ensure.company'])->group(function () {
    Route::get('/ship-operation-perimeters/{shipOperationId}', [ShipOperationPerimeterController::class, 'get']);
    Route::post('/ship-operation-perimeters', [ShipOperationPerimeterController::class, 'create']);

    Route::prefix('registry')->group(function () {
        // O cadastro de entidades inteiro. O papel é um parâmetro de rota
        // resolvido no enum `EntityRole`: um papel novo não cria rota nova.
        Route::prefix('entities')->group(function () {
            Route::get('/', [EntityController::class, 'all']);
            Route::get('by-document/{document}', [EntityController::class, 'byDocument']);

            Route::prefix('{role}')->group(function () {
                Route::get('/', [EntityController::class, 'index']);
                Route::post('/', [EntityController::class, 'store']);
                Route::get('/{uuid}', [EntityController::class, 'show']);
                Route::put('/{uuid}', [EntityController::class, 'update']);
                Route::post('/{uuid}/activate', [EntityController::class, 'activate']);
                Route::post('/{uuid}/deactivate', [EntityController::class, 'deactivate']);
                Route::delete('/{uuid}', [EntityController::class, 'destroy']);
            });
        });

        Route::prefix('fuel-stations')->group(function () {
            Route::get('/', [FuelStationLinkController::class, 'index']);
            Route::get('/by-cnpj', [FuelStationLinkController::class, 'show'])
                ->middleware('throttle:20,1,company-fuel-station-cnpj');
            Route::post('/', [FuelStationLinkController::class, 'store'])
                ->middleware('throttle:10,1,company-fuel-station-store');
            Route::delete('/{uuid}', [FuelStationLinkController::class, 'destroy']);
            Route::post('/{uuid}/disconnect', [FuelStationLinkController::class, 'disconnect']);
            Route::post('/{uuid}/resend-request', [FuelStationLinkController::class, 'resendRequest'])
                ->middleware('throttle:10,1,company-fuel-station-resend');
        });

        Route::prefix('vehicles')->group(function () {
            Route::get('/', [VehicleController::class, 'index']);
            Route::post('/', [VehicleController::class, 'store']);
            Route::get('/{uuid}', [VehicleController::class, 'show']);
            Route::put('/{uuid}', [VehicleController::class, 'update']);
            Route::delete('/{uuid}', [VehicleController::class, 'destroy']);
        });
    });

    Route::prefix('company')->group(function () {
        Route::get('users', [MemberController::class, 'index']);
        Route::patch('users/{uuid}', [MemberController::class, 'update']);
        Route::post('users/{uuid}/suspend', [MemberController::class, 'suspend']);
        Route::post('users/{uuid}/reactivate', [MemberController::class, 'reactivate']);
        Route::post('users/{uuid}/remove', [MemberController::class, 'remove']);
        Route::delete('users/{uuid}', [MemberController::class, 'destroy']);
        Route::get('invitations/lookup', [InvitationController::class, 'lookup'])
            ->middleware('throttle:30,1,invitation-lookup');
        Route::get('invitations', [InvitationController::class, 'index']);
        Route::post('invitations', [InvitationController::class, 'store'])
            ->middleware('throttle:10,1,invitation-store');
        Route::post('invitations/{uuid}/resend', [InvitationController::class, 'resend'])
            ->middleware('throttle:10,1,invitation-resend');
        Route::delete('invitations/{uuid}', [InvitationController::class, 'destroy']);
    });
});

// =============================== API STATION ROUTES ===============================

Route::middleware(['auth:sanctum', 'ensure.station'])->prefix('station')->group(function () {
    Route::get('me', [StationController::class, 'me']);
    Route::get('companies', [StationCompanyController::class, 'index']);
    Route::post('companies/{uuid}/accept', [StationCompanyController::class, 'accept']);
    Route::post('companies/{uuid}/reject', [StationCompanyController::class, 'reject']);
    Route::post('companies/{uuid}/disconnect', [StationCompanyController::class, 'disconnect']);
    Route::get('users', [MemberController::class, 'index']);
    Route::patch('users/{uuid}', [MemberController::class, 'update']);
    Route::post('users/{uuid}/suspend', [MemberController::class, 'suspend']);
    Route::post('users/{uuid}/reactivate', [MemberController::class, 'reactivate']);
    Route::post('users/{uuid}/remove', [MemberController::class, 'remove']);
    Route::delete('users/{uuid}', [MemberController::class, 'destroy']);
    Route::get('invitations/lookup', [InvitationController::class, 'lookup'])
        ->middleware('throttle:30,1,station-invitation-lookup');
    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations', [InvitationController::class, 'store'])
        ->middleware('throttle:10,1,station-invitation-store');
    Route::post('invitations/{uuid}/resend', [InvitationController::class, 'resend'])
        ->middleware('throttle:10,1,station-invitation-resend');
    Route::delete('invitations/{uuid}', [InvitationController::class, 'destroy']);
});

// =============================== ADMIN SYSTEM ROUTES ===============================

Route::middleware(['auth:sanctum', 'ensure.system'])->group(function () {
    Route::group(['prefix' => '/system'], function () {
        Route::group(['prefix' => '/companies'], function () {
            Route::get('/', [CompanyController::class, 'index']);
            Route::post('/', [CompanyController::class, 'store']);
            Route::get('/{uuid}', [CompanyController::class, 'show']);
            Route::put('/{uuid}', [CompanyController::class, 'update']);
            Route::post('/{uuid}/deactivate', [CompanyController::class, 'deactivate']);
            Route::post('/{uuid}/activate', [CompanyController::class, 'activate']);
            Route::get('/{uuid}/users', [CompanyAccessController::class, 'users']);
            Route::patch('/{uuid}/users/{user}', [CompanyAccessController::class, 'updateUser']);
            Route::delete('/{uuid}/users/{user}', [CompanyAccessController::class, 'suspendUser']);
            Route::post('/{uuid}/users/{user}/reactivate', [CompanyAccessController::class, 'reactivateUser']);
            Route::post('/{uuid}/users/{user}/remove', [CompanyAccessController::class, 'removeUser']);
            Route::get('/{uuid}/invitations/lookup', [CompanyAccessController::class, 'lookup'])
                ->middleware('throttle:30,1,system-invitation-lookup');
            Route::get('/{uuid}/invitations', [CompanyAccessController::class, 'index']);
            Route::post('/{uuid}/invitations', [CompanyAccessController::class, 'store'])
                ->middleware('throttle:10,1,system-invitation-store');
            Route::post('/{uuid}/invitations/{invitation}/resend', [CompanyAccessController::class, 'resend'])
                ->middleware('throttle:10,1,system-invitation-resend');
            Route::delete('/{uuid}/invitations/{invitation}', [CompanyAccessController::class, 'destroy']);
        });
    });
});
