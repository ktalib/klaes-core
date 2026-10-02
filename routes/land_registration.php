<?php

use App\Http\Controllers\LandRegistration\LandCaptureController;
use App\Http\Controllers\LandRegistration\LandCorController;
use App\Http\Controllers\LandRegistration\LandRdsController;
use App\Http\Controllers\LandRegistration\LandRegistrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Land Registration
|--------------------------------------------------------------------------
|
| The Land Registry's clone of the Deeds capture -> registration -> RDS/CoR
| pipeline. Separate screens and controllers, but the SAME tables underneath;
| every route here is scoped to config('land_registration.instrument_type').
|
| Reference lookups the capture form needs (LGAs, TP lookups, temp file
| numbers) are deliberately NOT cloned — they are registry-agnostic, so the
| land screens call the existing /instruments endpoints for them.
|
*/

Route::middleware(['auth'])
    ->prefix('land-registration')
    ->name('land-registration.')
    ->group(function () {

        /* ---------------------------------------------------------------
         | Instrument Capture
         | Static segments are declared before /{id} so "create" and
         | "check-duplicate" are never swallowed by the wildcard.
         --------------------------------------------------------------- */
        Route::prefix('instruments')->name('capture.')->group(function () {
            Route::get('/', [LandCaptureController::class, 'index'])->name('index');
            Route::get('/create', [LandCaptureController::class, 'create'])->name('create');
            Route::post('/store', [LandCaptureController::class, 'store'])->name('store');
            Route::get('/check-duplicate', [LandCaptureController::class, 'checkDuplicate'])->name('check-duplicate');
            Route::get('/next-registration-particulars', [LandCaptureController::class, 'nextRegistrationParticulars'])->name('next-registration-particulars');
            Route::get('/export', [LandCaptureController::class, 'exportCapture'])->name('export');
            Route::get('/{id}', [LandCaptureController::class, 'show'])->name('show');
            Route::get('/{id}/edit', [LandCaptureController::class, 'edit'])->name('edit');
            Route::put('/{id}', [LandCaptureController::class, 'update'])->name('update');
            Route::delete('/{id}', [LandCaptureController::class, 'destroy'])->name('destroy');
        });

        /* ---------------------------------------------------------------
         | Instrument Registration
         --------------------------------------------------------------- */
        Route::prefix('registration')->name('registration.')->group(function () {
            Route::get('/', [LandRegistrationController::class, 'index'])->name('index');
            Route::get('/view/{id}', [LandRegistrationController::class, 'view'])->name('view');
            Route::get('/next-serial', [LandRegistrationController::class, 'getNextSerialNumber'])->name('next-serial');
            Route::post('/register-single', [LandRegistrationController::class, 'registerSingle'])->name('register-single');
            Route::delete('/delete/{id}', [LandRegistrationController::class, 'destroy'])->name('destroy');
        });

        /* ---------------------------------------------------------------
         | Documents. Same rds_tracking / deed_registrations plumbing as
         | Deeds; only the templates differ (Land Registry + Director Land).
         --------------------------------------------------------------- */
        Route::post('/generate-rds/{id}', [LandRdsController::class, 'generateRDS'])->name('rds.generate');
        Route::get('/view-rds/{id}', [LandRdsController::class, 'viewRDS'])->name('rds.view');
        Route::get('/print-rds/{id}', [LandRdsController::class, 'printRDS'])->name('rds.print');

        Route::get('/cor', [LandCorController::class, 'index'])->name('cor.index');
        Route::post('/cor/generate/{id}', [LandCorController::class, 'generate'])->name('cor.generate');
    });
