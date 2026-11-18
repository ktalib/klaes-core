<?php

/*
 * KLAES-CORE Survey Desktop API (user, 2026-11-17; plan docs/plans/KLAES_SURVEY_DESKTOP_PLAN.md section 5).
 * Loaded by RouteServiceProvider under /api/desktop/v1 with the 'api' middleware group (stateless, JSON).
 */

use App\Http\Controllers\Api\Desktop\DesktopAuthController;
use App\Http\Controllers\Api\Desktop\DesktopDataController;
use App\Http\Controllers\Api\Desktop\DesktopMapController;
use App\Http\Controllers\Api\Desktop\DesktopPlottingController;
use Illuminate\Support\Facades\Route;

Route::name('desktop.')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('auth/login', [DesktopAuthController::class, 'login'])->name('login');
        Route::post('auth/verify', [DesktopAuthController::class, 'verify'])->name('verify');
        Route::post('auth/resend', [DesktopAuthController::class, 'resend'])->name('resend');
    });

    Route::middleware(['auth:sanctum', \App\Http\Middleware\DesktopToken::class])->group(function () {
        Route::post('auth/logout', [DesktopAuthController::class, 'logout'])->name('logout');
        Route::get('me', [DesktopAuthController::class, 'me'])->name('me');
        Route::get('plans', [DesktopDataController::class, 'plans'])->name('plans');
        Route::get('plans/{plan}', [DesktopDataController::class, 'show'])->whereNumber('plan')->name('plans.show');
        Route::get('plans/{plan}/file', [DesktopDataController::class, 'file'])->whereNumber('plan')->name('plans.file');
        Route::get('beacons/search', [DesktopDataController::class, 'searchBeacons'])->middleware('throttle:120,1')->name('beacons.search');
        Route::get('register/beacons', [DesktopDataController::class, 'register'])->name('register');
        // The Kano map data, for a PC without its own copy (user, 2026-11-18: as on the web). Range reads, so no low throttle.
        Route::get('map/layers', [DesktopMapController::class, 'layers'])->name('map.layers');
        Route::get('gis/{file}', [DesktopMapController::class, 'gis'])->where('file', '[a-z_]+')->withoutMiddleware('throttle:api')->middleware('throttle:3000,1')->name('map.gis');
        // Digital Plotting from the app (user, 2026-11-18): the form's lists, and Plot and Send.
        Route::get('lookups/digital-plotting', [DesktopPlottingController::class, 'lookups'])->name('plotting.lookups');
        Route::post('plans/digital', [DesktopPlottingController::class, 'store'])->middleware('throttle:30,1')->name('plotting.store');
    });
});
