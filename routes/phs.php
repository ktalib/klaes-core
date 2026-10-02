<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Phs\PhsLandingController;
use App\Http\Controllers\Phs\PhsAuthController;
use App\Http\Controllers\Phs\PhsDashboardController;
use App\Http\Controllers\Phs\PhsSlipController;
use App\Http\Controllers\Phs\PhsTokenController;
use App\Http\Controllers\Phs\PhsOrganizationController;
use App\Http\Controllers\Phs\PhsOnboardingController;
use App\Http\Controllers\Phs\PhsFeedbackController;
use App\Http\Controllers\Phs\PhsLoginOtpController;
use App\Http\Controllers\Phs\PhsPhoneSetupController;

/*
|--------------------------------------------------------------------------
| PHS Portal Routes (Property History Search — Institutional SaaS)
|--------------------------------------------------------------------------
|
| Public landing + auth screens, then the authenticated portal (guard: phs).
| Staff-side administration lives in routes/app3.php under the staff `auth` guard.
|
*/

Route::prefix('phs')->name('phs.')->group(function () {

    // ---- Public (no login required) ----
    Route::get('/', [PhsLandingController::class, 'index'])->name('landing');
    Route::get('login', [PhsAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [PhsAuthController::class, 'login'])->name('login.submit');
    Route::get('get-started', function () { return view('phs.get-started'); })->name('get_started');
    Route::get('register', [PhsAuthController::class, 'showRegister'])->name('register');
    Route::post('register', [PhsAuthController::class, 'register'])->name('register.submit');

    /*
     | Second step of sign-in. Public on purpose: the browser holds a GUEST
     | session here — the password has been accepted but nobody is signed in
     | until the code comes back — so `auth:phs` would turn every one of these
     | away. The pending sign-in in the session is what authorises the screen,
     | and it names the member, so none of these can be pointed at anyone else.
     */
    Route::get('login/verify', [PhsLoginOtpController::class, 'show'])->name('login.otp');
    Route::post('login/verify', [PhsLoginOtpController::class, 'verify'])
        ->middleware('throttle:10,1')->name('login.otp.verify');
    Route::post('login/verify/resend', [PhsLoginOtpController::class, 'resend'])
        ->middleware('throttle:6,1')->name('login.otp.resend');
    Route::post('login/verify/cancel', [PhsLoginOtpController::class, 'cancel'])->name('login.otp.cancel');

    // Onboarding request flow
    Route::get('request', [PhsOnboardingController::class, 'showForm'])->name('request.form');
    Route::post('request', [PhsOnboardingController::class, 'confirmPayment'])->name('request.confirm');
    Route::post('request/submit', [PhsOnboardingController::class, 'submitRequest'])->name('request.submit');
    Route::get('request/{id}/pending', [PhsOnboardingController::class, 'showPending'])->name('request.pending');
    Route::get('request/{id}/invoice', [PhsOnboardingController::class, 'downloadInvoice'])->name('request.invoice');
    Route::get('register/{token}', [PhsAuthController::class, 'showRegisterWithToken'])->name('register.token');
    Route::post('register/{token}', [PhsAuthController::class, 'registerWithToken'])->name('register.token.submit');

    // LSA (License & Service Agreement) — public, secured by per-request token in URL
    Route::get('lsa/{id}/{token}/download', [PhsOnboardingController::class, 'downloadLsa'])->name('lsa.download');
    Route::get('lsa/{id}/{token}/upload', [PhsOnboardingController::class, 'showLsaUpload'])->name('lsa.upload.form');
    Route::post('lsa/{id}/{token}/upload', [PhsOnboardingController::class, 'uploadLsa'])->name('lsa.upload');

    // Paystack payment — public, secured by per-request payment_token in URL
    Route::get('payment/{id}/{token}', [PhsOnboardingController::class, 'showPaymentPage'])->name('payment.form');
    Route::post('payment/{id}/{token}/initiate', [PhsOnboardingController::class, 'initiatePaystackPayment'])->name('payment.initiate');
    Route::get('payment/{id}/{token}/callback', [PhsOnboardingController::class, 'handlePaystackCallback'])->name('payment.callback');

    /*
     | The mobile-number card. Signed in and past the sign-in code, but NOT
     | behind `phs.phone` — a gate that blocked its own screens would leave the
     | member bouncing between the card and itself. Logout sits here for the same
     | reason: somebody who does not want to give a number must still be able to
     | leave.
     */
    Route::middleware(['auth:phs', 'phs.otp'])->group(function () {
        Route::post('logout', [PhsAuthController::class, 'logout'])->name('logout');

        Route::get('phone/confirm', [PhsPhoneSetupController::class, 'show'])->name('phone.setup');
        Route::post('phone/confirm', [PhsPhoneSetupController::class, 'send'])
            ->middleware('throttle:10,1')->name('phone.setup.send');
        Route::post('phone/confirm/resend', [PhsPhoneSetupController::class, 'resend'])
            ->middleware('throttle:6,1')->name('phone.setup.resend');
        Route::post('phone/confirm/code', [PhsPhoneSetupController::class, 'confirm'])
            ->middleware('throttle:10,1')->name('phone.setup.confirm');
        Route::post('phone/confirm/change', [PhsPhoneSetupController::class, 'change'])->name('phone.setup.change');
    });

    // ---- Authenticated PHS members ----
    Route::middleware(['auth:phs', 'phs.otp', 'phs.phone'])->group(function () {

        Route::get('dashboard', [PhsDashboardController::class, 'index'])->name('dashboard');
        Route::post('search', [PhsDashboardController::class, 'search'])->name('search');

        // File-number selector data — PHS's own gated copy of the internal
        // /api/file-numbers/* endpoints. Reuses the tested controller logic but
        // sits behind auth:phs so only logged-in members can search the registry.
        Route::prefix('api/file-numbers')->name('api.file-numbers.')
            ->controller(\App\Http\Controllers\FileNumberApiController::class)
            ->group(function () {
                Route::get('mls', 'mls')->name('mls');
                Route::get('kangis', 'kangis')->name('kangis');
                Route::get('newkangis', 'newKangis')->name('newkangis');
                Route::get('lookup', 'lookup')->name('lookup');
            });

        Route::get('slip/data', [PhsSlipController::class, 'data'])->name('slip.data');
        Route::get('slip/print', [PhsSlipController::class, 'print'])->name('slip.print');

        // Feedback / complaints about incomplete or wrong transactions in a slip.
        Route::post('feedback', [PhsFeedbackController::class, 'store'])->name('feedback.store');

        // "Send Edit Request" — a result the member says is wrong, routed to the
        // PHS-P Admin. Once corrected, index() reports the free re-run the member
        // is owed; the search route itself applies it.
        Route::post('edit-requests', [\App\Http\Controllers\Phs\PhsEditRequestController::class, 'store'])
            ->name('edit-requests.store');
        Route::get('edit-requests', [\App\Http\Controllers\Phs\PhsEditRequestController::class, 'index'])
            ->name('edit-requests.index');

        Route::get('tokens/transactions', [PhsTokenController::class, 'transactions'])->name('tokens.transactions');
        Route::post('tokens/pay-online', [PhsTokenController::class, 'payOnline'])->name('tokens.payOnline');
        Route::post('tokens/topup', [PhsTokenController::class, 'requestTopup'])->name('tokens.topup');
        Route::post('tokens/topup/paystack/initiate', [PhsTokenController::class, 'initiateTopupPaystack'])->name('tokens.topup.paystack.initiate');
        Route::get('tokens/topup/paystack/callback', [PhsTokenController::class, 'handleTopupCallback'])->name('tokens.topup.paystack.callback');
        Route::post('tokens/request-invoice', [PhsTokenController::class, 'requestInvoice'])->name('tokens.requestInvoice');

        // Organization / User Management console — super_admin only.
        Route::middleware('phs.admin')->prefix('organization')->name('org.')->group(function () {
            Route::get('/', [PhsOrganizationController::class, 'index'])->name('index');
            Route::get('members', [PhsOrganizationController::class, 'listMembers'])->name('members');
            Route::post('members', [PhsOrganizationController::class, 'storeMember'])->name('members.store');
            Route::put('members/{id}', [PhsOrganizationController::class, 'updateMember'])->name('members.update');
            Route::delete('members/{id}', [PhsOrganizationController::class, 'destroyMember'])->name('members.destroy');
            Route::get('activity', [PhsOrganizationController::class, 'activity'])->name('activity');
            Route::get('email-history', [PhsOrganizationController::class, 'emailHistory'])->name('emailHistory');
            Route::post('branding', [PhsOrganizationController::class, 'updateBranding'])->name('branding');
            Route::get('invoice/download', [PhsOnboardingController::class, 'downloadOrgInvoice'])->name('invoice.download');
        });
    });
});
