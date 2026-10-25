<?php

use App\Http\Controllers\Access\InvitationController;
use App\Http\Controllers\Access\MemberController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\FuelStationSignupController;
use App\Http\Controllers\Auth\InvitationAcceptController;
use App\Http\Controllers\FuelStation\StationCompanyController;
use App\Http\Controllers\FuelStation\StationController;
use App\Http\Controllers\Location\LocationController;
use App\Http\Controllers\Operations\ShipOperationPerimeterController;
use App\Http\Controllers\Registry\CarrierController;
use App\Http\Controllers\Registry\CommonEntityController;
use App\Http\Controllers\Registry\CustomerController;
use App\Http\Controllers\Registry\DriverController;
use App\Http\Controllers\Registry\EmployeeController;
use App\Http\Controllers\Registry\FuelStationLinkController;
use App\Http\Controllers\Registry\SupplierController;
use App\Http\Controllers\Registry\VehicleController;
use App\Http\Controllers\Registry\VehicleOwnerController;
use App\Http\Controllers\Registry\VehicleResponsibleController;
use App\Http\Controllers\System\Company\CompanyController;
use App\Http\Controllers\System\Company\CompanyInvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'status' => 'up',
    ]);
});

// todo:: migrar depois para dentro do company routes
Route::get('/ship-operation-perimeters/{shipOperationId}', [ShipOperationPerimeterController::class, 'get']);
Route::post('/ship-operation-perimeters', [ShipOperationPerimeterController::class, 'create']);

// =============================== AUTH ROUTES ===============================

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1,login');

    Route::prefix('station-signup')->group(function () {
        Route::get('cnpj/{cnpj}', [FuelStationSignupController::class, 'cnpj'])
            ->middleware('throttle:20,1,station-signup-cnpj');
        Route::post('check-email', [FuelStationSignupController::class, 'checkEmail'])
            ->middleware('throttle:20,1,station-signup-email');
        Route::post('/', [FuelStationSignupController::class, 'store'])
            ->middleware('throttle:20,1,station-signup');
    });

    // Convite: o link carrega o token. Sem login dá pra ver e criar a conta.
    Route::prefix('invitations/{token}')->where(['token' => '[A-Za-z0-9]+'])->group(function () {
        Route::get('/', [InvitationAcceptController::class, 'show'])
            ->middleware('throttle:20,1,invitation-show');
        Route::post('register', [InvitationAcceptController::class, 'register'])
            ->middleware('throttle:20,1,invitation-register');
        Route::post('accept', [InvitationAcceptController::class, 'accept'])
            ->middleware(['auth:sanctum', 'throttle:20,1,invitation-accept']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('companies', [AuthController::class, 'companies']);
        Route::post('company', [AuthController::class, 'selectCompany']);
        Route::get('contexts', [AuthController::class, 'contexts']);
        Route::post('station', [AuthController::class, 'selectStation']);
    });
});

// =============================== LOCATION ROUTES ===============================

Route::middleware('auth:sanctum')->prefix('locations')->group(function () {
    Route::get('states', [LocationController::class, 'states']);
    Route::get('states/{state}/cities', [LocationController::class, 'cities']);
});

// =============================== API COMPANY ROUTES ===============================

Route::middleware(['auth:sanctum', 'ensure.company'])->group(function () {

    Route::prefix('company')->group(function () {
        // Usuários e convites. Sair (DELETE do próprio uuid) não exige admin.
        Route::middleware('ensure.admin')->group(function () {
            Route::get('users', [MemberController::class, 'index']);
            Route::patch('users/{uuid}', [MemberController::class, 'update'])->whereUuid('uuid');
            Route::get('invitations', [InvitationController::class, 'index']);
            Route::post('invitations', [InvitationController::class, 'store']);
            Route::post('invitations/{uuid}/renew', [InvitationController::class, 'renew'])->whereUuid('uuid');
            Route::delete('invitations/{uuid}', [InvitationController::class, 'destroy'])->whereUuid('uuid');
        });
        Route::delete('users/{uuid}', [MemberController::class, 'destroy'])->whereUuid('uuid');
    });

    Route::prefix('registry')->group(function () {
        Route::get('entities/by-document/{document}', [CommonEntityController::class, 'byDocument']);

        // 02
        Route::get('entities', [CommonEntityController::class, 'index']);
        Route::get('entities/{uuid}', [CommonEntityController::class, 'show']);
        Route::put('entities/{uuid}', [CommonEntityController::class, 'update']);

        Route::prefix('employees')->group(function () {
            Route::get('/', [EmployeeController::class, 'index']);
            Route::post('/', [EmployeeController::class, 'store']);
            Route::get('/{uuid}', [EmployeeController::class, 'show']);
            Route::put('/{uuid}', [EmployeeController::class, 'update']);
            Route::post('/{uuid}/deactivate', [EmployeeController::class, 'deactivate']);
            Route::post('/{uuid}/activate', [EmployeeController::class, 'activate']);
        });

        Route::prefix('carriers')->group(function () {
            Route::get('/', [CarrierController::class, 'index']);
            Route::post('/', [CarrierController::class, 'store']);
            Route::get('/{uuid}', [CarrierController::class, 'show']);
            Route::put('/{uuid}', [CarrierController::class, 'update']);
            Route::post('/{uuid}/deactivate', [CarrierController::class, 'deactivate']);
            Route::post('/{uuid}/activate', [CarrierController::class, 'activate']);
        });

        // # 01
        Route::prefix('suppliers')->group(function () {
            Route::get('/', [SupplierController::class, 'index']);
            Route::post('/', [SupplierController::class, 'store']);
            Route::get('/{uuid}', [SupplierController::class, 'show']);
            Route::put('/{uuid}', [SupplierController::class, 'update']);
            Route::post('/{uuid}/deactivate', [SupplierController::class, 'deactivate']);
            Route::post('/{uuid}/activate', [SupplierController::class, 'activate']);
        });

        Route::prefix('customers')->group(function () {
            Route::get('/', [CustomerController::class, 'index']);
            Route::post('/', [CustomerController::class, 'store']);
            Route::get('/{uuid}', [CustomerController::class, 'show']);
            Route::put('/{uuid}', [CustomerController::class, 'update']);
            Route::post('/{uuid}/deactivate', [CustomerController::class, 'deactivate']);
            Route::post('/{uuid}/activate', [CustomerController::class, 'activate']);
        });

        Route::prefix('drivers')->group(function () {
            Route::get('/', [DriverController::class, 'index']);
            Route::post('/', [DriverController::class, 'store']);
            Route::get('/{uuid}', [DriverController::class, 'show']);
            Route::put('/{uuid}', [DriverController::class, 'update']);
            Route::post('/{uuid}/deactivate', [DriverController::class, 'deactivate']);
            Route::post('/{uuid}/activate', [DriverController::class, 'activate']);
        });

        Route::prefix('vehicle-owners')->group(function () {
            Route::get('/', [VehicleOwnerController::class, 'index']);
            Route::post('/', [VehicleOwnerController::class, 'store']);
            Route::get('/{uuid}', [VehicleOwnerController::class, 'show']);
            Route::put('/{uuid}', [VehicleOwnerController::class, 'update']);
            Route::post('/{uuid}/deactivate', [VehicleOwnerController::class, 'deactivate']);
            Route::post('/{uuid}/activate', [VehicleOwnerController::class, 'activate']);
        });

        Route::prefix('vehicle-responsibles')->group(function () {
            Route::get('/', [VehicleResponsibleController::class, 'index']);
            Route::post('/', [VehicleResponsibleController::class, 'store']);
            Route::get('/{uuid}', [VehicleResponsibleController::class, 'show']);
            Route::put('/{uuid}', [VehicleResponsibleController::class, 'update']);
            Route::post('/{uuid}/deactivate', [VehicleResponsibleController::class, 'deactivate']);
            Route::post('/{uuid}/activate', [VehicleResponsibleController::class, 'activate']);
        });

        Route::prefix('fuel-stations')->group(function () {
            Route::get('/', [FuelStationLinkController::class, 'index']);
            Route::get('/by-cnpj', [FuelStationLinkController::class, 'show'])
                ->middleware('throttle:20,1,company-fuel-station-cnpj');
            Route::post('/', [FuelStationLinkController::class, 'store']);
            Route::delete('/{uuid}', [FuelStationLinkController::class, 'destroy'])->whereUuid('uuid');
            Route::post('/{uuid}/disconnect', [FuelStationLinkController::class, 'disconnect'])->whereUuid('uuid');
            Route::post('/{uuid}/resend-request', [FuelStationLinkController::class, 'resendRequest'])->whereUuid('uuid');
        });

        Route::prefix('vehicles')->group(function () {
            Route::get('/', [VehicleController::class, 'index']);
            Route::post('/', [VehicleController::class, 'store']);
            Route::get('/{uuid}', [VehicleController::class, 'show']);
            Route::put('/{uuid}', [VehicleController::class, 'update']);
            Route::delete('/{uuid}', [VehicleController::class, 'destroy']);
        });
    });
});

// =============================== API STATION ROUTES ===============================

Route::middleware(['auth:sanctum', 'ensure.station'])->prefix('station')->group(function () {
    Route::get('me', [StationController::class, 'me']);
    Route::get('companies', [StationCompanyController::class, 'index']);
    Route::post('companies/{uuid}/accept', [StationCompanyController::class, 'accept'])->whereUuid('uuid');
    Route::post('companies/{uuid}/reject', [StationCompanyController::class, 'reject'])->whereUuid('uuid');
    Route::post('companies/{uuid}/disconnect', [StationCompanyController::class, 'disconnect'])->whereUuid('uuid');

    // Usuários e convites. Sair (DELETE do próprio uuid) não exige admin.
    Route::middleware('ensure.admin')->group(function () {
        Route::get('users', [MemberController::class, 'index']);
        Route::patch('users/{uuid}', [MemberController::class, 'update'])->whereUuid('uuid');
        Route::get('invitations', [InvitationController::class, 'index']);
        Route::post('invitations', [InvitationController::class, 'store']);
        Route::post('invitations/{uuid}/renew', [InvitationController::class, 'renew'])->whereUuid('uuid');
        Route::delete('invitations/{uuid}', [InvitationController::class, 'destroy'])->whereUuid('uuid');
    });
    Route::delete('users/{uuid}', [MemberController::class, 'destroy'])->whereUuid('uuid');
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
            Route::get('/{company}/invitations', [CompanyInvitationController::class, 'index'])->whereUuid('company');
            Route::post('/{company}/invitations', [CompanyInvitationController::class, 'store'])->whereUuid('company');
            Route::post('/{company}/invitations/{invitation}/renew', [CompanyInvitationController::class, 'renew'])
                ->whereUuid(['company', 'invitation']);
            Route::delete('/{company}/invitations/{invitation}', [CompanyInvitationController::class, 'destroy'])
                ->whereUuid(['company', 'invitation']);
        });
    });
});
