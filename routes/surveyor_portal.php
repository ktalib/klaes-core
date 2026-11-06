<?php

use App\Http\Controllers\SurveyorPortal\AuthController;
use App\Http\Controllers\SurveyorPortal\CompanyRegistrationController;
use App\Http\Controllers\SurveyorPortal\MapRequestController;
use App\Http\Controllers\SurveyorPortal\PortalController;
use Illuminate\Support\Facades\Route;

/*
 | The Surveyor General's Office portal, KSSGOF (CR 03112026 items 17-21). Surveyors, Public and Private,
 | register here and ask the SG for beacon numbers. Own guard, separate from staff logins.
 */
Route::prefix('kssgof')->name('surveyor-portal.')->group(function () {
    // The front page (user, 2026-11-05: "a nice landing page, and hero"); sign-in moved to /kssgof/login.
    Route::get('/', [AuthController::class, 'landing'])->name('home');
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.submit');
    Route::get('company/register', [CompanyRegistrationController::class, 'create'])->name('company.register');
    Route::post('company/register', [CompanyRegistrationController::class, 'store'])->middleware('throttle:10,1')->name('company.submit');
    Route::get('registered', [AuthController::class, 'registered'])->name('registered');
    Route::get('map-requests/new', [MapRequestController::class, 'create'])->name('map-requests.create');
    Route::post('map-requests', [MapRequestController::class, 'store'])->middleware('throttle:10,1')->name('map-requests.store');

    // The address builder's search, open to someone registering. Place names only; the staff lookups need a login.
    Route::middleware('throttle:60,1')->prefix('lookup')->name('lookup.')->group(function () {
        Route::get('districts', [\App\Http\Controllers\Survey\LookupController::class, 'districts'])->name('districts');
        Route::get('streets', [\App\Http\Controllers\Survey\LookupController::class, 'streets'])->name('streets');
    });

    Route::get('password/forgot', [AuthController::class, 'showForgot'])->name('password.forgot');
    Route::post('password/forgot', [AuthController::class, 'sendCode'])->middleware('throttle:5,1')->name('password.send');
    Route::get('password/reset', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('password/reset', [AuthController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');

    Route::middleware('auth:surveyor')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('notifications', [PortalController::class, 'notifications'])->name('notifications');

        Route::prefix('requests')->name('requests.')->group(function () {
            Route::get('/', [PortalController::class, 'index'])->name('index');
            Route::get('new', [PortalController::class, 'create'])->name('create');
            Route::post('/', [PortalController::class, 'store'])->name('store');
            Route::get('{request}', [PortalController::class, 'show'])->whereNumber('request')->name('show');
            Route::post('{request}/accept', [PortalController::class, 'accept'])->whereNumber('request')->name('accept');
            Route::get('{request}/slip', [PortalController::class, 'slip'])->whereNumber('request')->name('slip');
        });

        // A map from the Production Unit (user, 2026-11-06). The requests join Survey's own in the SDI queue.
        Route::prefix('map-requests')->name('map-requests.')->group(function () {
            Route::get('/', [MapRequestController::class, 'index'])->name('index');
            Route::get('{mapRequest}', [MapRequestController::class, 'show'])->whereNumber('mapRequest')->name('show');
            Route::get('{mapRequest}/request-file', [MapRequestController::class, 'requestFile'])->whereNumber('mapRequest')->name('request-file');
            Route::get('{mapRequest}/map', [MapRequestController::class, 'map'])->whereNumber('mapRequest')->name('map');
        });
    });
});
