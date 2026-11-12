<?php

use App\Http\Controllers\SurveyorPortal\AuthController;
use App\Http\Controllers\SurveyorPortal\CompanyRegistrationController;
use App\Http\Controllers\SurveyorPortal\LoginOtpController;
use App\Http\Controllers\SurveyorPortal\MapRequestController;
use App\Http\Controllers\SurveyorPortal\PortalController;
use App\Http\Controllers\SurveyorPortal\ReportController;
use App\Http\Controllers\SurveyorPortal\SecurityQuestionsController;
use Illuminate\Support\Facades\Route;

/*
 | The Surveyor General's Office portal (CR 03112026 items 17-21). Surveyors, Public and Private,
 | register here and ask the SG for beacon numbers. Own guard, separate from staff logins.
 | Renamed KSSGOF -> MLPP-SG (user, 2026-11-10), so /kssgof became /mlpp-sg; links already sent
 | out under /kssgof are sent on to the same page under /mlpp-sg.
 */
Route::get('kssgof/{path?}', function (\Illuminate\Http\Request $r, ?string $path = null) {
    $qs = $r->getQueryString();

    return redirect('/mlpp-sg' . ($path !== null && $path !== '' ? '/' . $path : '') . ($qs ? '?' . $qs : ''), 301);
})->where('path', '.*')->name('surveyor-portal.legacy');

Route::prefix('mlpp-sg')->name('surveyor-portal.')->group(function () {
    // The front page (user, 2026-11-05: "a nice landing page, and hero"); sign-in is /mlpp-sg/login.
    Route::get('/', [AuthController::class, 'landing'])->name('home');
    // Company and surveyor sign in at their own URLs (user, 2026-11-11); /login asks which first.
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::get('login/{as}', [AuthController::class, 'showLogin'])->where('as', 'company|surveyor')->name('login.as');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    // The sign-in code, then the security questions when it is late (user, 2026-11-10). Work on the pending sign-in only.
    Route::get('login/verify', [LoginOtpController::class, 'show'])->name('login.otp');
    Route::post('login/verify', [LoginOtpController::class, 'verify'])->middleware('throttle:10,1')->name('login.otp.verify');
    Route::post('login/verify/resend', [LoginOtpController::class, 'resend'])->middleware('throttle:6,1')->name('login.otp.resend');
    Route::post('login/verify/cancel', [LoginOtpController::class, 'cancel'])->name('login.otp.cancel');
    Route::get('login/verify/questions', [SecurityQuestionsController::class, 'challenge'])->name('login.questions');
    Route::post('login/verify/questions', [SecurityQuestionsController::class, 'verify'])->middleware('throttle:10,1')->name('login.questions.verify');
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
        Route::get('lgas', [\App\Http\Controllers\Survey\LookupController::class, 'portalLgas'])->name('lgas');
    });

    Route::get('password/forgot', [AuthController::class, 'showForgot'])->name('password.forgot');
    Route::post('password/forgot', [AuthController::class, 'sendCode'])->middleware('throttle:5,1')->name('password.send');
    Route::get('password/reset', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('password/reset', [AuthController::class, 'reset'])->middleware('throttle:10,1')->name('password.update');

    // surveyor.password: a company login changes its sent password first (user, 2026-11-10).
    Route::middleware(['auth:surveyor', 'surveyor.otp', 'surveyor.questions', 'surveyor.password'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('account/password', [\App\Http\Controllers\SurveyorPortal\PasswordController::class, 'edit'])->name('password.edit');
        Route::post('account/password', [\App\Http\Controllers\SurveyorPortal\PasswordController::class, 'update'])->middleware('throttle:10,1')->name('password.change');
        Route::get('account/security-questions', [SecurityQuestionsController::class, 'edit'])->name('security-questions.edit');
        Route::post('account/security-questions', [SecurityQuestionsController::class, 'update'])->middleware('throttle:10,1')->name('security-questions.update');
        Route::get('dashboard', [PortalController::class, 'dashboard'])->name('dashboard');
        Route::get('notifications', [PortalController::class, 'notifications'])->name('notifications');

        // A surveyor's own pages; a company login has its own below (user, 2026-11-10).
        Route::middleware('surveyor.kind:surveyor')->group(function () {
            Route::prefix('requests')->name('requests.')->group(function () {
                Route::get('/', [PortalController::class, 'index'])->name('index');
                Route::get('new', [PortalController::class, 'create'])->name('create');
                Route::post('/', [PortalController::class, 'store'])->name('store');
                Route::get('{request}', [PortalController::class, 'show'])->whereNumber('request')->name('show');
                Route::post('{request}/accept', [PortalController::class, 'accept'])->whereNumber('request')->name('accept');
                Route::get('{request}/slip', [PortalController::class, 'slip'])->whereNumber('request')->name('slip');
            });

            // Survey Plan Upload (Survey Update 5g/5h, 2026-11-10): the short form and the surveyor's own uploads.
            Route::prefix('survey-plans')->name('survey-plans.')->group(function () {
                Route::get('/', [\App\Http\Controllers\SurveyorPortal\SurveyPlanController::class, 'index'])->name('index');
                // Survey Plan Upload > Signed (user, 2026-11-12): the surveyor's signed plans.
                Route::get('signed', [\App\Http\Controllers\SurveyorPortal\SurveyPlanController::class, 'signed'])->name('signed');
                Route::post('/', [\App\Http\Controllers\SurveyorPortal\SurveyPlanController::class, 'store'])->middleware('throttle:20,1')->name('store');
                Route::get('{plan}/file', [\App\Http\Controllers\SurveyorPortal\SurveyPlanController::class, 'file'])->whereNumber('plan')->name('file');
                // CR 11112026-2: beacon search, the plan page, the final copy and the stage files.
                Route::get('beacons', [\App\Http\Controllers\SurveyorPortal\SurveyPlanController::class, 'beacons'])->middleware('throttle:120,1')->name('beacons');
                Route::get('{plan}', [\App\Http\Controllers\SurveyorPortal\SurveyPlanController::class, 'show'])->whereNumber('plan')->name('show');
                Route::post('{plan}/final', [\App\Http\Controllers\SurveyorPortal\SurveyPlanController::class, 'final'])->whereNumber('plan')->middleware('throttle:20,1')->name('final');
                Route::get('{plan}/files/{file}', [\App\Http\Controllers\SurveyorPortal\SurveyPlanController::class, 'stageFile'])->whereNumber(['plan', 'file'])->name('stage-file');
            });

            // Requested Beacons > Beacon Register and Surveyor's Report (user, 2026-11-09): sidebar items, not tabs.
            Route::get('beacon-register', [PortalController::class, 'beaconRegister'])->name('beacon-register');
            Route::prefix('surveyors-report')->name('reports.')->group(function () {
                Route::get('/', [ReportController::class, 'index'])->name('index');
                Route::get('new', [ReportController::class, 'create'])->name('create');
                Route::post('/', [ReportController::class, 'store'])->name('store');
                Route::get('{report}', [ReportController::class, 'show'])->whereNumber('report')->name('show');
                Route::put('{report}', [ReportController::class, 'update'])->whereNumber('report')->name('update');
                Route::post('{report}/coordinates', [ReportController::class, 'storeCoordinates'])->whereNumber('report')->name('coordinates.store');
                Route::delete('{report}/coordinates/{coord}', [ReportController::class, 'destroyCoordinate'])->whereNumber('report')->whereNumber('coord')->name('coordinates.destroy');
                Route::post('{report}/observations', [ReportController::class, 'storeObservations'])->whereNumber('report')->name('observations.store');
                Route::delete('{report}/observations/{obs}', [ReportController::class, 'destroyObservation'])->whereNumber('report')->whereNumber('obs')->name('observations.destroy');
                Route::post('{report}/submit', [ReportController::class, 'submit'])->whereNumber('report')->name('submit');
                Route::get('{report}/print', [ReportController::class, 'print'])->whereNumber('report')->name('print');
            });

            // A map from the Production Unit (user, 2026-11-06). The requests join Survey's own in the SDI queue.
            Route::prefix('map-requests')->name('map-requests.')->group(function () {
                Route::get('/', [MapRequestController::class, 'index'])->name('index');
                Route::get('{mapRequest}', [MapRequestController::class, 'show'])->whereNumber('mapRequest')->name('show');
                Route::get('{mapRequest}/request-file', [MapRequestController::class, 'requestFile'])->whereNumber('mapRequest')->name('request-file');
                Route::get('{mapRequest}/map', [MapRequestController::class, 'map'])->whereNumber('mapRequest')->name('map');
            });
        });

        // A company's own login (user, 2026-11-10): Dashboard (above), Our Surveyors, Our Beacons, Notifications (above).
        Route::middleware('surveyor.kind:company')->prefix('company')->name('company.')->group(function () {
            Route::get('surveyors', [\App\Http\Controllers\SurveyorPortal\CompanyController::class, 'surveyors'])->name('surveyors');
            Route::get('beacons/{which}', [\App\Http\Controllers\SurveyorPortal\CompanyController::class, 'beacons'])->whereIn('which', ['issued', 'used'])->name('beacons');
            Route::get('surveyors-report', [\App\Http\Controllers\SurveyorPortal\CompanyController::class, 'reports'])->name('reports');
            Route::get('surveyors-report/{report}/print', [\App\Http\Controllers\SurveyorPortal\CompanyController::class, 'printReport'])->whereNumber('report')->name('reports.print');
            // Uploaded Survey Plans > Original / Signed (user, 2026-11-12): the plans the company's surveyors uploaded.
            Route::get('survey-plans/{which}', [\App\Http\Controllers\SurveyorPortal\CompanyController::class, 'plans'])->whereIn('which', ['original', 'signed'])->name('plans');
            Route::get('survey-plans/{plan}/file/{which}', [\App\Http\Controllers\SurveyorPortal\CompanyController::class, 'planFile'])->whereNumber('plan')->whereIn('which', ['original', 'signed'])->name('plans.file');
        });
    });
});
