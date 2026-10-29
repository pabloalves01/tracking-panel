<?php

use App\Http\Controllers\Access\InvitationController;
use App\Http\Controllers\Access\MemberController;
use App\Http\Controllers\Auth\AccountRegistrationController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\FuelStationSignupController;
use App\Http\Controllers\Auth\InvitationAcceptController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\VerificationCodeController;
use App\Http\Controllers\FuelStation\FuelStationCompanyController;
use App\Http\Controllers\Location\LocationController;
use App\Http\Controllers\Registry\CarrierController;
use App\Http\Controllers\Registry\CustomerController;
use App\Http\Controllers\Registry\DriverController;
use App\Http\Controllers\Registry\EmployeeController;
use App\Http\Controllers\Registry\EntityController;
use App\Http\Controllers\Registry\FuelStationAgreementController;
use App\Http\Controllers\Registry\SupplierController;
use App\Http\Controllers\Registry\VehicleOwnerController;
use App\Http\Controllers\Registry\VehicleResponsibleController;
use App\Http\Controllers\System\OrganizationController;
use Illuminate\Support\Facades\Route;

/**
 * Todo `{uuid}` precisa ter forma de UUID para a rota casar.
 *
 * No Postgres a coluna é do tipo `uuid`: um valor fora do formato não dá
 * "não encontrado", dá erro de sintaxe no banco — 500 numa URL digitada
 * errada. Com a restrição aqui, o Laravel responde 404 antes de consultar.
 */
Route::pattern('uuid', '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}');

Route::get('/health', fn () => response()->json(['status' => 'up']));

// =============================== AUTENTICAÇÃO ===============================

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1,login');
    Route::post('verification-codes', [VerificationCodeController::class, 'store'])
        ->middleware('throttle:10,1,verification-send');
    Route::post('password/forgot', [PasswordResetController::class, 'forgot'])
        ->middleware('throttle:5,1,password-forgot');
    Route::post('password/reset', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:10,1,password-reset');

    // Cadastro de conta, e só de conta. O código chega por
    // `verification-codes` com a finalidade `account_register`, sempre
    // entregue; `register/check` confere e só então diz se a conta existe.
    // Posto e convite vêm depois, já com a sessão.
    Route::post('register/check', [AccountRegistrationController::class, 'check'])
        ->middleware('throttle:20,1,register-check');
    Route::post('register', [AccountRegistrationController::class, 'store'])
        ->middleware('throttle:10,1,register');

    // Os dados da Receita aparecem antes de a pessoa dizer quem é.
    Route::get('fuel-station-signup/cnpj/{cnpj}', [FuelStationSignupController::class, 'cnpj'])
        ->middleware('throttle:20,1,fuel-station-signup-cnpj');

    // Convite, pelo link. Quem tem o link pode ver e recusar sem entrar.
    Route::prefix('invitations/{token}')->group(function () {
        Route::get('/', [InvitationAcceptController::class, 'show'])
            ->middleware('throttle:20,1,invitation-show');
        Route::post('decline', [InvitationAcceptController::class, 'decline'])
            ->middleware('throttle:20,1,invitation-decline');
    });

    Route::middleware('auth:sanctum')->group(function () {
        // Traz usuário, organização atual, organizações disponíveis e
        // convites pendentes de uma vez.
        Route::get('me', [AuthController::class, 'me']);
        Route::get('organizations', [AuthController::class, 'organizations']);
        Route::post('organization', [AuthController::class, 'selectOrganization']);
        Route::post('logout', [AuthController::class, 'logout']);

        Route::post('fuel-stations', [FuelStationSignupController::class, 'storeForUser'])
            ->middleware('throttle:20,1,fuel-station-create');

        Route::post('invitations/{token}/accept', [InvitationAcceptController::class, 'accept'])
            ->middleware('throttle:20,1,invitation-accept');
        Route::get('pending-invitations', [InvitationAcceptController::class, 'mine']);
        Route::post('pending-invitations/{uuid}/accept', [InvitationAcceptController::class, 'acceptMine']);
        Route::post('pending-invitations/{uuid}/decline', [InvitationAcceptController::class, 'declineMine']);
    });
});

// =============================== LOCALIDADES ===============================

Route::middleware('auth:sanctum')->prefix('locations')->group(function () {
    Route::get('states', [LocationController::class, 'states']);
    Route::get('states/{state}/cities', [LocationController::class, 'cities']);
});

// =============================== ORGANIZAÇÃO ATIVA ===============================
// Membros e convites valem para qualquer tipo de organização: empresa hoje,
// posto hoje, fornecedor amanhã. Eram as mesmas rotas escritas três vezes.

Route::middleware(['auth:sanctum', 'organization'])->prefix('organization')->group(function () {
    Route::get('members', [MemberController::class, 'index']);
    Route::post('members/{uuid}/suspend', [MemberController::class, 'suspend']);
    Route::post('members/{uuid}/reactivate', [MemberController::class, 'reactivate']);
    Route::delete('members/{uuid}', [MemberController::class, 'destroy']);

    Route::get('invitations', [InvitationController::class, 'index']);
    Route::post('invitations', [InvitationController::class, 'store'])
        ->middleware('throttle:10,1,invitation-store');
    Route::post('invitations/{uuid}/resend', [InvitationController::class, 'resend'])
        ->middleware('throttle:10,1,invitation-resend');
    Route::delete('invitations/{uuid}', [InvitationController::class, 'destroy']);
});

// =============================== EMPRESA ===============================

Route::middleware(['auth:sanctum', 'organization:company'])->group(function () {
    Route::prefix('registry')->group(function () {
        // A pessoa, acima dos papéis.
        Route::prefix('entities')->controller(EntityController::class)->group(function () {
            Route::get('/', 'index');
            Route::get('by-document/{document}', 'byDocument');
            Route::delete('{uuid}', 'destroy');
        });

        // Um recurso por papel, cada um com o próprio controller.
        $roles = [
            'drivers' => DriverController::class,
            'vehicle-owners' => VehicleOwnerController::class,
            'vehicle-responsibles' => VehicleResponsibleController::class,
            'employees' => EmployeeController::class,
            'suppliers' => SupplierController::class,
            'customers' => CustomerController::class,
            'carriers' => CarrierController::class,
        ];

        foreach ($roles as $path => $controller) {
            Route::prefix($path)->controller($controller)->group(function () {
                Route::get('/', 'index');
                Route::post('/', 'store');
                Route::get('{uuid}', 'show');
                Route::put('{uuid}', 'update');
                Route::delete('{uuid}', 'destroy');
                Route::post('{uuid}/activate', 'activate');
                Route::post('{uuid}/deactivate', 'deactivate');
            });
        }

        // Postos conveniados.
        Route::prefix('fuel-stations')->group(function () {
            Route::get('/', [FuelStationAgreementController::class, 'index']);
            Route::get('by-document', [FuelStationAgreementController::class, 'byDocument'])
                ->middleware('throttle:20,1,fuel-station-lookup');
            Route::post('/', [FuelStationAgreementController::class, 'store'])
                ->middleware('throttle:10,1,fuel-station-request');
            Route::delete('/{uuid}', [FuelStationAgreementController::class, 'destroy']);
            Route::post('/{uuid}/disconnect', [FuelStationAgreementController::class, 'disconnect']);
        });
    });
});

// =============================== POSTO ===============================

Route::middleware(['auth:sanctum', 'organization:fuel_station'])->prefix('fuel-station')->group(function () {
    Route::get('companies', [FuelStationCompanyController::class, 'index']);
    Route::post('companies/{uuid}/accept', [FuelStationCompanyController::class, 'accept']);
    Route::post('companies/{uuid}/reject', [FuelStationCompanyController::class, 'reject']);
    Route::post('companies/{uuid}/disconnect', [FuelStationCompanyController::class, 'disconnect']);
});

// =============================== SISTEMA ===============================

Route::middleware(['auth:sanctum', 'system'])->prefix('system')->group(function () {
    Route::prefix('organizations')->group(function () {
        Route::get('/', [OrganizationController::class, 'index']);
        Route::post('/', [OrganizationController::class, 'store']);
        Route::get('/{uuid}', [OrganizationController::class, 'show']);
        Route::put('/{uuid}', [OrganizationController::class, 'update']);
        Route::post('/{uuid}/activate', [OrganizationController::class, 'activate']);
        Route::post('/{uuid}/deactivate', [OrganizationController::class, 'deactivate']);
    });
});
