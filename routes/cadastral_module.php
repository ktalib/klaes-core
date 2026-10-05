<?php

use App\Http\Controllers\Cadastral\ChartingController;
use App\Http\Controllers\Cadastral\CorrespondenceController;
use App\Http\Controllers\Cadastral\DashboardController;
use App\Http\Controllers\Cadastral\DuplicateController;
use App\Http\Controllers\Cadastral\FileReceiptController;
use App\Http\Controllers\Cadastral\FileStatusController;
use App\Http\Controllers\Cadastral\IndexCardController;
use App\Http\Controllers\Cadastral\MovementController;
use App\Http\Controllers\Cadastral\PlanDescriptionController;
use App\Http\Controllers\Cadastral\ReportController;
use App\Http\Controllers\Cadastral\SurveyJobController;
use App\Http\Controllers\Cadastral\SurveyorController;
use App\Http\Controllers\Survey\LookupController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cadastral Module
|--------------------------------------------------------------------------
| The four units of the Cadastral concept note: Registry, Report, Information,
| and Plan & Description.
|
| PREFIX. "cadastral-module", not "cadastral": config/module_permissions.php
| already maps 'cadastral.*' to Cad - Records for the legacy screens, and the two
| must stay separately addressable. Str::is('cadastral.*', 'cadastral-module.x')
| is false, so neither pattern swallows the other.
|
| ROUTE NAMES ARE LOAD-BEARING. config/module_permissions.php infers the
| permission action from the name. Names containing "mark", "save", "assign",
| "update" or "edit" read as `edit`; "store", "create", "new" or "generate" as
| `create`; "print", "label" or "certificate" as `print`; "destroy" or "delete"
| as `delete`; "export" or "download" as `export`. Hence "mark-registered"
| rather than "register", and "mark-done" rather than "advance" -- staying
| inside the pattern language is what keeps the overrides list empty. Read-only
| endpoints stay on GET so they infer `view`.
|
| Auth is required: these views render inside layouts.app, whose admin.menu
| partial reads auth()->user()->assignedRoleNames().
*/

Route::middleware(['auth'])->prefix('cadastral-module')->name('cadastral-module.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Management figures over a date range. GET, and a name with no verb, so it infers `view`.
    Route::get('/analytics', [DashboardController::class, 'analytics'])->name('analytics');

    /* ------------------------ 4.1 Cadastral Registry ------------------------ */
    Route::prefix('registry')->name('registry.')->group(function () {

        Route::get('/receipts', [FileReceiptController::class, 'index'])->name('receipts');
        Route::get('/receipts/create', [FileReceiptController::class, 'create'])->name('receipts.create');
        Route::post('/receipts', [FileReceiptController::class, 'store'])->name('receipts.store');
        Route::get('/receipts/{receipt}/edit', [FileReceiptController::class, 'edit'])->name('receipts.edit');
        Route::put('/receipts/{receipt}', [FileReceiptController::class, 'update'])->name('receipts.update');

        // Intake File No picker (Select2 AJAX). GET and verb-free, so it infers `view`.
        Route::get('/source-files', [FileReceiptController::class, 'sourceFiles'])->name('source-files');

        // "mark-*" so the action inference reads these as edit, not create.
        Route::post('/receipts/{receipt}/registered', [FileReceiptController::class, 'markRegistered'])->name('receipts.mark-registered');
        Route::post('/receipts/{receipt}/archived', [FileReceiptController::class, 'markArchived'])->name('receipts.mark-archived');
        Route::post('/receipts/{receipt}/returned', [FileReceiptController::class, 'markReturned'])->name('receipts.mark-returned');

        // Investigation hold (Phase 3). Placing needs a reason, clearing a remark; both audited.
        Route::post('/receipts/{receipt}/held', [FileReceiptController::class, 'markHeld'])->name('receipts.mark-held');
        Route::post('/receipts/{receipt}/cleared', [FileReceiptController::class, 'markCleared'])->name('receipts.mark-cleared');

        Route::delete('/receipts/{receipt}', [FileReceiptController::class, 'destroy'])->name('receipts.destroy');

        Route::get('/duplicates', [DuplicateController::class, 'index'])->name('duplicates');
        Route::get('/duplicates/lookup', [DuplicateController::class, 'lookup'])->name('duplicates.lookup');

        Route::get('/correspondence', [CorrespondenceController::class, 'index'])->name('correspondence');
        Route::get('/movements', [MovementController::class, 'index'])->name('movements');
    });

    /* ---------------------- 4.3 Cadastral Information ---------------------- */

    // Each unit has its own dashboard; Registry is the module landing page above.
    Route::get('/information/dashboard', [DashboardController::class, 'information'])
        ->name('information.dashboard');

    // Charting
    Route::prefix('charting')->name('charting.')->group(function () {
        Route::get('/', [ChartingController::class, 'index'])->name('index');
        Route::get('/conflicts', [ChartingController::class, 'conflicts'])->name('conflicts');
        Route::get('/create', [ChartingController::class, 'create'])->name('create');
        Route::post('/', [ChartingController::class, 'store'])->name('store');
        Route::get('/{chart}/edit', [ChartingController::class, 'edit'])->name('edit');
        Route::put('/{chart}', [ChartingController::class, 'update'])->name('update');
        Route::post('/{chart}/new-version', [ChartingController::class, 'newVersion'])->name('new-version');
        Route::post('/{chart}/checked', [ChartingController::class, 'markChecked'])->name('mark-checked');
        Route::post('/{chart}/coordinates', [ChartingController::class, 'saveCoordinates'])->name('coordinates.save');
        Route::delete('/{chart}', [ChartingController::class, 'destroy'])->name('destroy');
    });

    // Index cards
    Route::prefix('index-cards')->name('index-cards.')->group(function () {
        Route::get('/', [IndexCardController::class, 'index'])->name('index');
        Route::post('/', [IndexCardController::class, 'store'])->name('store');

        // Commission File No picker over registered intake receipts (Select2 AJAX).
        // GET and verb-free, so it infers `view`. Declared before /{card}.
        Route::get('/intake-files', [IndexCardController::class, 'intakeFiles'])->name('intake-files');

        Route::get('/{card}', [IndexCardController::class, 'show'])->name('show');
        Route::put('/{card}', [IndexCardController::class, 'update'])->name('update');

        // Update the card's movement stage ("save" -> edit). Recorded in audit_logs.
        Route::post('/{card}/movement', [IndexCardController::class, 'saveMovement'])->name('movement.save');
        Route::get('/{card}/print', [IndexCardController::class, 'print'])->name('print');
    });

    // Survey job numbers and Instructions to Surveyor
    Route::prefix('survey-jobs')->name('survey-jobs.')->group(function () {
        Route::get('/', [SurveyJobController::class, 'index'])->name('index');
        Route::post('/', [SurveyJobController::class, 'store'])->name('store');
        Route::get('/{surveyJob}/edit', [SurveyJobController::class, 'edit'])->name('edit');
        Route::put('/{surveyJob}', [SurveyJobController::class, 'update'])->name('update');
        Route::post('/{surveyJob}/its', [SurveyJobController::class, 'generateIts'])->name('its.generate');
        Route::get('/{surveyJob}/its/print', [SurveyJobController::class, 'printIts'])->name('its.print');
        Route::post('/{surveyJob}/submitted', [SurveyJobController::class, 'markSubmitted'])->name('mark-submitted');
        Route::post('/{surveyJob}/accepted', [SurveyJobController::class, 'markAccepted'])->name('mark-accepted');
    });

    // Registered surveyor / firm directory
    Route::prefix('surveyors')->name('surveyors.')->group(function () {
        Route::get('/', [SurveyorController::class, 'index'])->name('index');
        Route::post('/', [SurveyorController::class, 'store'])->name('store');
        Route::put('/{surveyor}', [SurveyorController::class, 'update'])->name('update');
        Route::delete('/{surveyor}', [SurveyorController::class, 'destroy'])->name('destroy');
    });

    // File status: revoked / reinstated / withdrawn / change of purpose / open / close
    Route::prefix('file-status')->name('file-status.')->group(function () {
        Route::get('/', [FileStatusController::class, 'index'])->name('index');
        Route::get('/history', [FileStatusController::class, 'history'])->name('history');
        Route::put('/{card}', [FileStatusController::class, 'update'])->name('update');
    });

    /* ------------------------- 4.2 Cadastral Report ------------------------- */
    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/dashboard', [ReportController::class, 'dashboard'])->name('dashboard');
        Route::get('/export', [ReportController::class, 'export'])->name('export');
        Route::get('/', [ReportController::class, 'index'])->name('index');

        // One page per stream (Phase 4). GET with verb-free names, so they infer `view`.
        Route::get('/verification', [ReportController::class, 'verification'])->name('verification');
        Route::get('/customary', [ReportController::class, 'customary'])->name('customary');
        Route::get('/statutory', [ReportController::class, 'statutory'])->name('statutory');

        Route::get('/create', [ReportController::class, 'create'])->name('create');
        Route::post('/', [ReportController::class, 'store'])->name('store');
        Route::get('/{report}', [ReportController::class, 'show'])->whereNumber('report')->name('show');
        Route::get('/{report}/edit', [ReportController::class, 'edit'])->whereNumber('report')->name('edit');
        Route::put('/{report}', [ReportController::class, 'update'])->whereNumber('report')->name('update');
        Route::get('/{report}/print', [ReportController::class, 'print'])->whereNumber('report')->name('print');

        // Report on Application (§3a): the questionnaire is saved on the Report
        // step ("save" -> edit); the official form prints ("print" -> print).
        Route::post('/{report}/application', [ReportController::class, 'saveApplication'])->whereNumber('report')->name('application.save');
        Route::get('/{report}/application/print', [ReportController::class, 'applicationPrint'])->whereNumber('report')->name('application-print');

        Route::post('/{report}/assign', [ReportController::class, 'assign'])->name('assign');
        Route::post('/{report}/steps/{step}/done', [ReportController::class, 'markStepDone'])->name('steps.mark-done');
        Route::post('/{report}/steps/{step}/returned', [ReportController::class, 'markStepReturned'])->name('steps.mark-returned');
        Route::post('/{report}/approve', [ReportController::class, 'approve'])->name('approve');
        Route::post('/{report}/reject', [ReportController::class, 'reject'])->name('reject');
        Route::post('/{report}/dispatched', [ReportController::class, 'markDispatched'])->name('mark-dispatched');

        Route::post('/{report}/manual-chart', [ReportController::class, 'storeManualChart'])->name('manual-chart.store');
        Route::post('/{report}/inspections', [ReportController::class, 'storeInspection'])->name('inspections.store');
        Route::put('/{report}/inspections/{inspection}', [ReportController::class, 'updateInspection'])->name('inspections.update');
    });

    /* --------------------- 4.4 Plan and Description --------------------- */
    Route::prefix('plan-description')->name('plan-description.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'planDescription'])->name('dashboard');
        Route::get('/', [PlanDescriptionController::class, 'index'])->name('index');
        // GET so the permission layer infers view rather than create.
        Route::get('/area/preview', [PlanDescriptionController::class, 'previewArea'])->name('area.preview');

        // Phase 6: Area & Pillars and Descriptions are their own pages, GET with
        // verb-free names (view); starting a record is "store" (create),
        // re-recording its area "update" (edit). Their files are picked with the
        // shared picker (cadastral-module.lookup.file); the Select2 sources
        // intake-files and description-files are gone.
        // Declared before the /{planDescription} routes.
        Route::get('/area', [PlanDescriptionController::class, 'area'])->name('area');
        Route::post('/area', [PlanDescriptionController::class, 'areaStore'])->name('area.store');
        Route::get('/descriptions', [PlanDescriptionController::class, 'descriptionsPage'])->name('descriptions');

        // Phase 7: the Fee Calculator page and its live preview are GET with
        // verb-free names (view); the fee-files Select2 source is gone. Saving
        // the bill stays "bill.generate" (create); cancelling a draft is
        // "mark-cancelled" (edit).
        Route::get('/fees', [PlanDescriptionController::class, 'fees'])->name('fees');
        Route::get('/fees/preview', [PlanDescriptionController::class, 'previewFees'])->name('fees.preview');

        Route::get('/create', [PlanDescriptionController::class, 'create'])->name('create');
        Route::post('/', [PlanDescriptionController::class, 'store'])->name('store');
        Route::get('/{planDescription}/edit', [PlanDescriptionController::class, 'edit'])->name('edit');
        Route::put('/{planDescription}', [PlanDescriptionController::class, 'update'])->name('update');
        Route::delete('/{planDescription}', [PlanDescriptionController::class, 'destroy'])->name('destroy');

        Route::post('/{planDescription}/pillars', [PlanDescriptionController::class, 'savePillars'])->name('pillars.save');
        Route::post('/{planDescription}/bill', [PlanDescriptionController::class, 'generateBill'])->name('bill.generate');
        Route::get('/{planDescription}/bill/{bill}/print', [PlanDescriptionController::class, 'printBill'])->name('bill.print');
        Route::post('/{planDescription}/bill/{bill}/cancelled', [PlanDescriptionController::class, 'cancelBill'])->name('bill.mark-cancelled');
        Route::post('/{planDescription}/description', [PlanDescriptionController::class, 'generateDescription'])->name('description.generate');
        Route::put('/{planDescription}/description', [PlanDescriptionController::class, 'saveDescription'])->name('description.save');

        Route::put('/{planDescription}/area', [PlanDescriptionController::class, 'areaUpdate'])->name('area.update');
        Route::get('/{planDescription}/description/print', [PlanDescriptionController::class, 'printDescription'])->name('description.print');
    });

    /*
    | Address-builder lookups.
    |
    | Pointed at the Survey module's LookupController -- the controller is reused,
    | the ROUTE is not. The Survey lookup routes map to "Survey - Records", so a
    | Cadastral-only user calling them gets a 403 and empty district/street
    | dropdowns. These names map to Cad - Records instead.
    */
    Route::prefix('lookup')->name('lookup.')->group(function () {
        Route::get('/districts', [LookupController::class, 'districts'])->name('districts');
        Route::get('/streets', [LookupController::class, 'streets'])->name('streets');
        Route::get('/lgas', [LookupController::class, 'lgas'])->name('lgas');
        Route::get('/states', [LookupController::class, 'states'])->name('states');

        // The shared file picker's resolver (partials/_file_picker): one picked
        // file number in, the file, its module records and the scope verdict out.
        // GET and verb-free, so it infers `view`. Reads only.
        Route::get('/file', [\App\Http\Controllers\Cadastral\FileLookupController::class, 'file'])->name('file');
    });
});
