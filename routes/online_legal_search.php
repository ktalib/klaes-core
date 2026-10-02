<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OnlineLegalSearch\OnlineLsDashboardController;
use App\Http\Controllers\OnlineLegalSearch\OnlineLsIdVerificationController;

/*
|--------------------------------------------------------------------------
| Online Legal Search Portal Routes
|--------------------------------------------------------------------------
|
| Fully public, no accounts. Anyone can run a Legal Search, pay, and view the
| full report without logging in or registering. Each payment is tracked
| back-office by a generated tracking id (e.g. USER-0001).
| Staff-side administration lives in routes/app3.php under the staff `auth` guard.
|
*/

Route::prefix('online-legal-search')->name('ols.')->group(function () {

    // Public landing page.
    Route::get('/', [OnlineLsDashboardController::class, 'landing'])->name('landing');

    // Public search: anyone can run a Legal Search and see a preview/summary.
    Route::post('search', [OnlineLsDashboardController::class, 'search'])->name('search');

    // Public Select2 autocomplete for file numbers (queries file_indexings).
    Route::get('file-numbers', [OnlineLsDashboardController::class, 'fileNumbers'])->name('file-numbers');

    // Public full result. Shows the Paystack checkout until a verified payment
    // exists for this file number; the report then opens via its payment reference.
    Route::get('result', [OnlineLsDashboardController::class, 'result'])->name('result');
    Route::post('payment/verify', [OnlineLsDashboardController::class, 'verifyPayment'])->name('payment.verify');

    /*
     | ID NAME verification. Runs before the Paystack checkout may open: the
     | applicant's identification is stored privately, OCR'd, and the name on it
     | compared with the name they typed. Only a `verified` result unlocks payment.
     |
     | Rate limited because each call performs file writes and an OCR pass — both
     | far more expensive than an ordinary request, and both worth abusing.
     |
     | 12/minute rather than 6: the check now fires automatically as the applicant
     | completes the form, so a legitimate person correcting a typo or swapping a
     | photo makes several attempts in a sitting. The browser debounces and skips
     | unchanged submissions, which keeps an honest applicant well under this; the
     | limit is here for everyone else.
     */
    Route::post('verification', [OnlineLsIdVerificationController::class, 'store'])
        ->middleware('throttle:12,1')
        ->name('verification.store');
});

/*
| Staff-side view of a submitted identification. Lives outside the public group:
| it is behind the staff `auth` guard and the same Director / Deputy Director
| check that guards the approval queue, and it is the ONLY way to read a file off
| the private disk.
*/
Route::middleware(['auth'])
    ->prefix('legal-search/online/admin')
    ->name('legal-search-online.admin.')
    ->group(function () {
        Route::get('verifications/{id}/document/{side}', [OnlineLsIdVerificationController::class, 'document'])
            ->whereNumber('id')
            ->whereIn('side', ['front', 'back'])
            ->name('verifications.document');
    });
