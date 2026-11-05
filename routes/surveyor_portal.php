<?php

use App\Http\Controllers\SurveyorPortal\AuthController;
use App\Http\Controllers\SurveyorPortal\PortalController;
use Illuminate\Support\Facades\Route;

/*
 | The Surveyor General's Office portal, KSSGOF (CR 03112026 items 17-21). Surveyors, Public and Private,
 | register here and ask the SG for beacon numbers. Own guard, separate from staff logins.
 */
Route::prefix('kssgof')->name('surveyor-portal.')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.submit');
    Route::get('registered', [AuthController::class, 'registered'])->name('registered');

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
    });
});
