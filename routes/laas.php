<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Laas\LaasApplicationController;
use App\Http\Controllers\Laas\LaasAuthController;
use App\Http\Controllers\Laas\LaasDashboardController;
use App\Http\Controllers\Laas\LaasLandingController;
use App\Http\Controllers\Laas\LaasLoginOtpController;
use App\Http\Controllers\Laas\LaasProfileController;
use App\Http\Controllers\AlaesPortalController;

/*
|--------------------------------------------------------------------------
| LAAS Portal Routes (Land Allocation Application System)
|--------------------------------------------------------------------------
|
| Public landing + auth screens, then the applicant portal (guard: laas).
| This whole file sits OUTSIDE staff admin auth, exactly as routes/phs.php does.
|
| The staff side of the same workflow — the Director's review queue and MLP's
| file-number desk — lives in routes/app3.php under the staff `auth` guard.
|
*/

Route::prefix('laas')->name('laas.')->group(function () {

    // ---- Public (no login required) ----
    Route::get('/', [LaasLandingController::class, 'index'])->name('landing');
    Route::get('login', [LaasAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [LaasAuthController::class, 'login'])->name('login.submit');
    Route::get('register', [LaasAuthController::class, 'showRegister'])->name('register');
    Route::post('register', [LaasAuthController::class, 'register'])->name('register.submit');

    /*
     | Second step of sign-in. Public on purpose: the browser holds a GUEST
     | session here — the password has been accepted but nobody is signed in
     | until the code comes back — so `auth:laas` would turn every one of these
     | away. The pending sign-in in the session is what authorises the screen,
     | and it names the account, so none of these can be pointed at anyone else.
     |
     | Shared by both front doors; LaasLoginOtpService remembers which one.
     */
    Route::get('login/verify', [LaasLoginOtpController::class, 'show'])->name('login.otp');
    Route::post('login/verify', [LaasLoginOtpController::class, 'verify'])
        ->middleware('throttle:10,1')->name('login.otp.verify');
    Route::post('login/verify/resend', [LaasLoginOtpController::class, 'resend'])
        ->middleware('throttle:6,1')->name('login.otp.resend');
    Route::post('login/verify/cancel', [LaasLoginOtpController::class, 'cancel'])->name('login.otp.cancel');

    // ---- Authenticated applicants ----
    Route::middleware(['auth:laas', 'laas.otp'])->group(function () {
        Route::post('logout', [LaasAuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', [LaasDashboardController::class, 'index'])->name('dashboard');
        Route::get('notifications', [LaasDashboardController::class, 'notifications'])->name('notifications');

        // Account settings. The phone number is the SMS delivery address for the
        // whole workflow, so changing it is a two-step, code-confirmed flow —
        // see LaasProfileController.
        Route::prefix('profile')->name('profile.')
            ->controller(LaasProfileController::class)
            ->group(function () {
                Route::get('/', 'show')->name('show');
                Route::put('details', 'updateDetails')->name('details');
                Route::post('phone', 'requestPhoneChange')->name('phone.request');
                Route::post('phone/confirm', 'confirmPhoneChange')->name('phone.confirm');
                Route::post('phone/cancel', 'cancelPhoneChange')->name('phone.cancel');
                Route::put('password', 'updatePassword')->name('password');
            });

        // Application form (spec a) and submission (spec b).
        Route::get('apply', [LaasApplicationController::class, 'form'])->name('apply.form');
        Route::post('apply/draft', [LaasApplicationController::class, 'saveDraft'])->name('apply.draft');
        Route::post('apply', [LaasApplicationController::class, 'store'])->name('apply.store');

        // Status page and its documents.
        Route::get('application/{reference}', [LaasApplicationController::class, 'show'])->name('application.show');
        Route::post('application/{reference}/documents', [LaasApplicationController::class, 'uploadDocument'])->name('application.documents.upload');
        Route::get('application/{reference}/documents/{document}', [LaasApplicationController::class, 'downloadDocument'])->name('application.documents.download');

        // Lookup data for the form's dependent dropdowns. The portal's own gated
        // copy of the internal /api/reference/* endpoints — same tested
        // controller, behind auth:laas. Districts are fetched per-LGA rather
        // than rendered into the page: the full table is ~1,800 rows and
        // inlining it into several selects is what makes the OSS applications
        // page unusable.
        Route::prefix('api/reference')->name('api.reference.')
            ->controller(\App\Http\Controllers\ReferenceDataController::class)
            ->group(function () {
                Route::get('states', 'states')->name('states');
                Route::get('lgas', 'lgas')->name('lgas');
                Route::get('districts', 'districts')->name('districts');
                Route::get('streets', 'streets')->name('streets');
                Route::get('land-uses', 'landUses')->name('land-uses');
                Route::get('purposes', 'purposes')->name('purposes');
            });
    });
});

Route::get('/alaes-portal', [AlaesPortalController::class, 'landing'])
    ->name('alaes-portal.landing');

Route::prefix('alaes-portal')->name('alaes_portal.')->group(function () {
    Route::get('login', [AlaesPortalController::class, 'login'])->name('login');
    Route::post('login', [LaasAuthController::class, 'login'])->name('login.submit');
    Route::get('register', [AlaesPortalController::class, 'register'])->name('register');
    Route::post('register', [LaasAuthController::class, 'register'])->name('register.submit');

    Route::middleware(['auth:laas', 'laas.otp'])->group(function () {
        Route::post('logout', [LaasAuthController::class, 'logout'])->name('logout');
        Route::get('dashboard', [LaasDashboardController::class, 'index'])->name('dashboard');
        Route::get('notifications', [LaasDashboardController::class, 'notifications'])->name('notifications');
        Route::get('apply', [LaasApplicationController::class, 'form'])->name('apply.form');
        Route::post('apply/draft', [LaasApplicationController::class, 'saveDraft'])->name('apply.draft');
        Route::post('apply', [LaasApplicationController::class, 'store'])->name('apply.store');
        Route::get('application/{reference}', [LaasApplicationController::class, 'show'])->name('application.show');
        Route::post('application/{reference}/documents', [LaasApplicationController::class, 'uploadDocument'])->name('application.documents.upload');
        Route::get('application/{reference}/documents/{document}', [LaasApplicationController::class, 'downloadDocument'])->name('application.documents.download');
    });
});
