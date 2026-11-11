<?php

use App\Http\Controllers\Survey\AllocationRecordController;
use App\Http\Controllers\Survey\BeaconRegisterController;
use App\Http\Controllers\Survey\BeneficiaryController;
use App\Http\Controllers\Survey\BoundaryDisputeController;
use App\Http\Controllers\Survey\CadastralFilesController;
use App\Http\Controllers\Survey\CalculatorController;
use App\Http\Controllers\Survey\CaseController;
use App\Http\Controllers\Survey\ChangeReportController;
use App\Http\Controllers\Survey\CoFileController;
use App\Http\Controllers\Survey\CommissioningSheetController;
use App\Http\Controllers\Survey\ControlPointRegisterController;
use App\Http\Controllers\Survey\CoordinateRegisterController;
use App\Http\Controllers\Survey\ComplaintController;
use App\Http\Controllers\Survey\DashboardController;
use App\Http\Controllers\Survey\ExaminationController;
use App\Http\Controllers\Survey\FileStatusController;
use App\Http\Controllers\Survey\GknApplicationController;
use App\Http\Controllers\Survey\GknController;
use App\Http\Controllers\Survey\LookupController;
use App\Http\Controllers\Survey\LpknController;
use App\Http\Controllers\Survey\MiscKnController;
use App\Http\Controllers\Survey\MobileCaseController;
use App\Http\Controllers\Survey\MobileObservationController;
use App\Http\Controllers\Survey\MobileOfflineController;
use App\Http\Controllers\Survey\MobilePlotController;
use App\Http\Controllers\Survey\PlotConfirmationController;
use App\Http\Controllers\Survey\OldSurveyController;
use App\Http\Controllers\Survey\OpController;
use App\Http\Controllers\Survey\OpShowingController;
use App\Http\Controllers\Survey\OpVerificationController;
use App\Http\Controllers\Survey\PlotAllocationController;
use App\Http\Controllers\Survey\ProjectController;
use App\Http\Controllers\Survey\RegisterFileController;
use App\Http\Controllers\Survey\ReportController;
use App\Http\Controllers\Survey\ResettlementCalculatorController;
use App\Http\Controllers\Survey\SdiMapRequestController;
use App\Http\Controllers\Survey\SdiProductionController;
use App\Http\Controllers\Survey\SdiRegistryController;
use App\Http\Controllers\Survey\SurveyBeaconNosController;
use App\Http\Controllers\Survey\SurveyCoordinateController;
use App\Http\Controllers\Survey\SurveySketchPlanController;
use App\Http\Controllers\Survey\SurveyItsController;
use App\Http\Controllers\Survey\SurveyorReportController;
use App\Http\Controllers\Survey\SurveyorListController;
use App\Http\Controllers\Survey\TreeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Survey Module
|--------------------------------------------------------------------------
| Auth is required: these views render inside layouts.app, whose admin.menu
| partial reads auth()->user()->assignedRoleNames().
*/

Route::middleware(['auth'])->prefix('survey-module')->name('survey-module.')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    // File Status search across every File Status list (CR 30102026 item 29, 2026-10-31).
    Route::get('/file-status', [FileStatusController::class, 'index'])->name('file-status');
    // Survey Plan Upload (Survey Update 5g/5h, 2026-11-10): the short form, every upload, and the plan file.
    Route::prefix('survey-plans')->name('survey-plans.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Survey\SurveyPlanUploadController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\Survey\SurveyPlanUploadController::class, 'export'])->name('export');
        Route::post('/', [\App\Http\Controllers\Survey\SurveyPlanUploadController::class, 'store'])->name('store');
        Route::get('/{plan}/file', [\App\Http\Controllers\Survey\SurveyPlanUploadController::class, 'file'])->whereNumber('plan')->name('file');
    });

    /* ------------------------------ Compensation ------------------------------ */
    Route::prefix('compensation')->name('compensation.')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'compensation'])->name('dashboard');

        // Special Projects > Confirmation Forms: serials per layout, blank form and capture.
        Route::prefix('confirmation-forms')->name('confirmation-forms')->group(function () {
            Route::get('/', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'index'])->name('');
            Route::post('/generate', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'generate'])->name('.generate');
            Route::get('/print', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'print'])->name('.print');
            Route::get('/export', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'export'])->name('.export');
            Route::post('/capture', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'capture'])->name('.capture');
            Route::post('/{form}', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'update'])->whereNumber('form')->name('.update');
            Route::post('/{form}/void', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'void'])->whereNumber('form')->name('.void');
            // The Guarantor's Form, step 2 of capture, printed from the row menu (user, 2026-11-03).
            Route::get('/{form}/guarantor', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'guarantorPrint'])->whereNumber('form')->name('.guarantor');
            // Guarantor Forms: their own table and form under the Confirmation Forms tab (user, 2026-11-03).
            Route::post('/guarantor', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'saveGuarantor'])->name('.guarantor.save');
            Route::post('/{form}/guarantor/remove', [\App\Http\Controllers\Survey\ConfirmationFormController::class, 'removeGuarantor'])->whereNumber('form')->name('.guarantor.remove');
        });

        // CO files (CO-YYYY-0001), one to many projects (user, 2026-10-25)
        // Two lists (user, 2026-10-30): CO Files = captured files, Commission CO File = commissioned files.
        Route::get('/co-files', [CoFileController::class, 'index'])->name('co-files');
        Route::get('/co-files/export', [CoFileController::class, 'export'])->name('co-files.export');
        Route::get('/co-files/commissioned', [CoFileController::class, 'commissioned'])->name('co-files.commissioned');
        Route::get('/co-files/commission', [CoFileController::class, 'create'])->name('co-files.create');
        Route::get('/co-files/related-lookup', [CoFileController::class, 'relatedLookup'])->name('co-files.related-lookup');
        Route::get('/co-files/number-lookup', [CoFileController::class, 'numberLookup'])->name('co-files.number-lookup');
        Route::get('/co-files/options', [CoFileController::class, 'options'])->name('co-files.options');
        Route::post('/co-files', [CoFileController::class, 'store'])->name('co-files.store');
        // Commissioning Sheet (CR 30102026 item 2): the MLPP sheet, issued by the Survey Department.
        Route::get('/co-files/{coFile}/commissioning-sheet', [CommissioningSheetController::class, 'coFile'])->name('co-files.commissioning-sheet');
        // View Uploaded Files: the raw blind-scan pages until the file is page-typed (CR 30102026 item 17).
        Route::get('/co-files/{coFile}/uploaded-files', [\App\Http\Controllers\Survey\CoFileScansController::class, 'show'])->name('co-files.uploaded-files');
        Route::get('/co-files/{coFile}/edit', [CoFileController::class, 'edit'])->name('co-files.edit');
        Route::put('/co-files/{coFile}', [CoFileController::class, 'update'])->name('co-files.update');
        Route::delete('/co-files/{coFile}', [CoFileController::class, 'destroy'])->name('co-files.destroy');

        // Allocation Records: surveyor portions per project, read-only (2026-10-25)
        Route::get('/allocation-records', [AllocationRecordController::class, 'index'])->name('allocation-records');

        // Letter of Grant is now Recommendation + RofO (CR 03112026 items 9-10, App\Support\SurveyLandRoutes). The old
        // list of Deeds OPs is gone from this page; no OP was changed. This keeps old links working.
        Route::get('/letter-of-grant', fn () => redirect()->route('survey-module.lg.land-recommendations.index', ['type' => 'ROFO']))->name('letter-of-grant');

        // Plot Confirmation register: OP showings and ground checks from the field app (2026-10-30).
        Route::get('/plot-confirmations', [PlotConfirmationController::class, 'index'])->name('plot-confirmations');
        Route::get('/plot-confirmations/export', [PlotConfirmationController::class, 'export'])->name('plot-confirmations.export');
        // Record Plot Showing from the office (user, 2026-11-02): the OP search and the save.
        Route::get('/plot-confirmations/op-search', [PlotConfirmationController::class, 'opSearch'])->name('plot-confirmations.op-search');
        Route::get('/plot-confirmations/tp-plots', [PlotConfirmationController::class, 'tpPlots'])->name('plot-confirmations.tp-plots');
        Route::get('/plot-confirmations/file-search', [PlotConfirmationController::class, 'indexedFileSearch'])->name('plot-confirmations.file-search');
        // Capture OP: OPs already captured that match what is being typed (user, 2026-11-10).
        Route::get('/plot-confirmations/op-matches', [PlotConfirmationController::class, 'opMatches'])->name('plot-confirmations.op-matches');
        Route::post('/plot-confirmations/ops', [PlotConfirmationController::class, 'storeShowingOp'])->name('plot-confirmations.ops.store');
        Route::post('/plot-confirmations/showings', [PlotConfirmationController::class, 'storeShowing'])->name('plot-confirmations.showings.store');
        // Evidence of Payment on Record Plot Showing (New), Survey Update 5e (2026-11-10).
        Route::get('/plot-confirmations/showings/{showing}/payment-evidence', [PlotConfirmationController::class, 'paymentEvidence'])->whereNumber('showing')->name('plot-confirmations.showings.payment-evidence');
        // Ground Check from the office (user, 2026-11-06): a tab on Record Plot Showing.
        Route::post('/plot-confirmations/checks', [PlotConfirmationController::class, 'storeCheck'])->name('plot-confirmations.checks.store');
        // Old register entries (user, 2026-11-03): showings captured from the old book, no OP needed.
        Route::post('/plot-confirmations/old', [PlotConfirmationController::class, 'storeOld'])->name('plot-confirmations.old.store');
        Route::put('/plot-confirmations/old/{showing}', [PlotConfirmationController::class, 'updateOld'])->whereNumber('showing')->name('plot-confirmations.old.update');
        Route::delete('/plot-confirmations/old/{showing}', [PlotConfirmationController::class, 'destroyOld'])->whereNumber('showing')->name('plot-confirmations.old.destroy');

        // Resettlement Calculator: plots = area ÷ plot size by land use / density (SG formulas, 2026-10-29).
        Route::get('/resettlement-calculator', [ResettlementCalculatorController::class, 'index'])->name('resettlement-calculator');
        Route::get('/resettlement-calculator/export', [ResettlementCalculatorController::class, 'export'])->name('resettlement-calculator.export');
        Route::post('/resettlement-calculator', [ResettlementCalculatorController::class, 'store'])->name('resettlement-calculator.store');
        Route::post('/resettlement-calculator/rates', [ResettlementCalculatorController::class, 'storeRate'])->name('resettlement-calculator.rates.store');
        Route::put('/resettlement-calculator/rates/{rate}', [ResettlementCalculatorController::class, 'updateRate'])->whereNumber('rate')->name('resettlement-calculator.rates.update');
        Route::post('/resettlement-calculator/rates/{rate}/toggle', [ResettlementCalculatorController::class, 'toggleRate'])->whereNumber('rate')->name('resettlement-calculator.rates.toggle');

        // Complaints (2026-10-25)
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints');
        Route::get('/complaints/export', [ComplaintController::class, 'export'])->name('complaints.export');
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/complaints/print-blank', [ComplaintController::class, 'printBlank'])->name('complaints.print-blank');
        Route::get('/complaints/{complaint}/print', [ComplaintController::class, 'print'])->name('complaints.print');
        Route::get('/complaints/{complaint}/edit', [ComplaintController::class, 'edit'])->name('complaints.edit');
        Route::put('/complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
        Route::post('/complaints/{complaint}/status', [ComplaintController::class, 'status'])->name('complaints.status');
        Route::delete('/complaints/{complaint}', [ComplaintController::class, 'destroy'])->name('complaints.destroy');

        // Projects — scheme type is set here and inherited by cases
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects');
        Route::get('/projects/export', [ProjectController::class, 'export'])->name('projects.export');
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        // Cases — the six-step register
        Route::get('/cases', [CaseController::class, 'index'])->name('cases');
        // Farm Assessment > Imported: a block's farms, one per farmer, loaded in place (user, 2026-11-09).
        Route::get('/cases/farms', [CaseController::class, 'farms'])->name('cases.farms');
        // Farm Assessment > Computation Sheet: the workbook as written, a sheet's lines loaded in place (user, 2026-11-06).
        Route::get('/computation-sheet/rows', [\App\Http\Controllers\Survey\ComputationSheetController::class, 'rows'])->name('computation-sheet.rows');
        Route::get('/computation-sheet/export', [\App\Http\Controllers\Survey\ComputationSheetController::class, 'export'])->name('computation-sheet.export');
        // A staged KMC Access farm record's passport photo (user, 2026-11-11).
        Route::get('/computation-sheet/photo/{id}', [\App\Http\Controllers\Survey\ComputationSheetController::class, 'photo'])->whereNumber('id')->name('computation-sheet.photo');
        Route::get('/cases/register', [CaseController::class, 'create'])->name('cases.register');
        // Capture Compensation List: paper lists keyed in by hand (user, 2026-10-30)
        Route::get('/capture-list', [\App\Http\Controllers\Survey\CompensationCaptureController::class, 'create'])->name('capture-list');
        Route::post('/capture-list', [\App\Http\Controllers\Survey\CompensationCaptureController::class, 'store'])->name('capture-list.store');
        Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
        // "Is this the existing farmer?" (CR 03112026). Before {case}, so it is not read as a case id.
        Route::get('/cases/farmer-lookup', [CaseController::class, 'farmerLookup'])->name('cases.farmer-lookup');
        Route::get('/cases/next-section', [CaseController::class, 'nextSectionPreview'])->name('cases.next-section');
        Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
        Route::get('/cases/{case}/farm-sheet/{farm}', [CaseController::class, 'farmSheet'])->whereNumber('farm')->name('cases.farm-sheet');
        Route::get('/cases/{case}/edit', [CaseController::class, 'edit'])->name('cases.edit');
        Route::put('/cases/{case}', [CaseController::class, 'update'])->name('cases.update');
        Route::delete('/cases/{case}', [CaseController::class, 'destroy'])->name('cases.destroy');
        Route::post('/cases/{case}/submit', [CaseController::class, 'submit'])->name('cases.submit');

        // Beneficiaries
        Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])->name('beneficiaries');
        Route::get('/beneficiaries/export', [BeneficiaryController::class, 'export'])->name('beneficiaries.export');
        Route::post('/beneficiaries', [BeneficiaryController::class, 'store'])->name('beneficiaries.store');
        Route::get('/beneficiaries/{beneficiary}/edit', [BeneficiaryController::class, 'edit'])->name('beneficiaries.edit');
        Route::put('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'update'])->name('beneficiaries.update');
        Route::delete('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'destroy'])->name('beneficiaries.destroy');

        // Economic trees — catalogue plus per-case lines
        Route::get('/trees', [TreeController::class, 'index'])->name('trees');
        Route::get('/trees/export', [TreeController::class, 'export'])->name('trees.export');
        Route::post('/trees', [TreeController::class, 'store'])->name('trees.store');
        Route::put('/trees/{treeType}', [TreeController::class, 'update'])->name('trees.update');
        Route::delete('/trees/{treeType}', [TreeController::class, 'destroy'])->name('trees.destroy');
        Route::post('/cases/{case}/trees', [TreeController::class, 'storeCaseTree'])->name('trees.case.store');
        Route::delete('/case-trees/{caseTree}', [TreeController::class, 'destroyCaseTree'])->name('trees.case.destroy');

        Route::get('/calculator', [CalculatorController::class, 'index'])->name('calculator');
        Route::post('/calculator', [CalculatorController::class, 'calculate'])->name('calculator.run');

        Route::get('/land', [CaseController::class, 'landAllocation'])->name('land');

        // OP Verification (Records): Survey checks each generated OP, then marks it sent to Deeds (Survey-side only).
        Route::get('/op-verification', [OpVerificationController::class, 'index'])->name('op-verification');
        Route::post('/op-verification/{op}', [OpVerificationController::class, 'act'])->name('op-verification.act');
        // Showing History per OP (Compensation Status → Verification (Records), 2026-10-29).
        Route::get('/op-verification/{op}/showings', [OpShowingController::class, 'index'])->whereNumber('op')->name('op-verification.showings');
        Route::post('/op-verification/{op}/showings', [OpShowingController::class, 'store'])->whereNumber('op')->name('op-verification.showings.store');
        Route::get('/op-showing-photos/{photo}', [OpShowingController::class, 'photo'])->whereNumber('photo')->name('op-showings.photo');

        // Occupancy Permits
        Route::get('/op', [OpController::class, 'index'])->name('op');
        Route::get('/op/export', [OpController::class, 'export'])->name('op.export');
        Route::post('/op/generate', [OpController::class, 'generate'])->name('op.generate');
        // Existing OPs, captured as issued (user, 2026-11-03): the Existing tab; no generator.
        Route::post('/op/existing', [OpController::class, 'storeExisting'])->name('op.existing.store');
        Route::put('/op/existing/{op}', [OpController::class, 'updateExisting'])->whereNumber('op')->name('op.existing.update');
        Route::delete('/op/existing/{op}', [OpController::class, 'destroyExisting'])->whereNumber('op')->name('op.existing.destroy');
        Route::post('/op/{op}/advance', [OpController::class, 'advance'])->name('op.advance');
        Route::delete('/op/{op}', [OpController::class, 'destroy'])->name('op.destroy');
        // Return a generated OP made by mistake; its number is freed (CR 30102026 item 35).
        Route::post('/op/{op}/return', [OpController::class, 'returnOp'])->name('op.return');

        Route::get('/reports', [ReportController::class, 'compensation'])->name('reports');
    });

    /* ---------------------------------- GKN ---------------------------------- */
    Route::prefix('gkn')->name('gkn.')->group(function () {
        // Plot Showing Register, Resettlement + Direct Allocation, with the actions (CR 03112026 item 1).
        Route::get('/plot-showing', [PlotConfirmationController::class, 'gkn'])->name('plot-showing');
        Route::get('/plot-showing/export', [PlotConfirmationController::class, 'gknExport'])->name('plot-showing.export');
        Route::get('/dashboard', [DashboardController::class, 'gkn'])->name('dashboard');
        // Applications: GKN / LPKN / LPPL / Misc KN files in one typed table (2026-10-25).
        Route::get('/applications', [GknApplicationController::class, 'index'])->name('applications');
        Route::get('/applications/export', [GknApplicationController::class, 'export'])->name('applications.export');
        // Commissioning Sheet for GKN, Misc KN, LPKN, LPPL and RDKN files (CR 30102026 item 2).
        Route::get('/applications/{id}/commissioning-sheet', [CommissioningSheetController::class, 'applicationFile'])->whereNumber('id')->name('applications.commissioning-sheet');
        // Computation Files (Field Jackets), CR 30102026 items 12-14 (2026-10-31): Government = commissioned here
        // (LPKN-<year>-<serial>); Private = Cadastral's (LPPL-<year>-<serial>), read-only.
        Route::get('/computation-files', [\App\Http\Controllers\Survey\ComputationFileController::class, 'index'])->name('computation-files');
        Route::get('/computation-files/export', [\App\Http\Controllers\Survey\ComputationFileController::class, 'export'])->name('computation-files.export');
        Route::get('/computation-files/beacon-search', [\App\Http\Controllers\Survey\ComputationFileController::class, 'beaconSearch'])->name('computation-files.beacon-search');
        Route::post('/computation-files', [\App\Http\Controllers\Survey\ComputationFileController::class, 'store'])->name('computation-files.store');
        Route::put('/computation-files/{computationFile}', [\App\Http\Controllers\Survey\ComputationFileController::class, 'update'])->whereNumber('computationFile')->name('computation-files.update');
        // Passing & Checking (Final Survey Plan), CR 30102026 item 21 (2026-10-31): a first version.
        Route::get('/passing-checks', [\App\Http\Controllers\Survey\PassingCheckController::class, 'index'])->name('passing-checks');
        Route::get('/passing-checks/export', [\App\Http\Controllers\Survey\PassingCheckController::class, 'export'])->name('passing-checks.export');
        Route::post('/passing-checks', [\App\Http\Controllers\Survey\PassingCheckController::class, 'store'])->name('passing-checks.store');
        Route::put('/passing-checks/{passingCheck}', [\App\Http\Controllers\Survey\PassingCheckController::class, 'update'])->whereNumber('passingCheck')->name('passing-checks.update');
        // Checking & Passing (Survey Plan) > Uploaded Survey Plan: plans sent to GKN (Survey Update 5i, 2026-11-10).
        Route::get('/uploaded-survey-plans', [\App\Http\Controllers\Survey\SurveyPlanUploadController::class, 'gkn'])->name('uploaded-plans');
        // Instruction to Survey: Survey's own jobs plus Cadastral's, read-only (2026-10-25).
        Route::prefix('instruction-to-survey')->name('its.')->group(function () {
            Route::get('/', [SurveyItsController::class, 'index'])->name('index');
            Route::get('/export', [SurveyItsController::class, 'export'])->name('export');
            Route::get('/create', [SurveyItsController::class, 'create'])->name('create');
            Route::post('/', [SurveyItsController::class, 'store'])->name('store');
            Route::get('/lookup', [SurveyItsController::class, 'lookup'])->name('lookup');
            Route::get('/beacon-search', [SurveyItsController::class, 'beaconSearch'])->name('beacon-search');
            Route::get('/{its}/edit', [SurveyItsController::class, 'edit'])->name('edit');
            Route::put('/{its}', [SurveyItsController::class, 'update'])->name('update');
            Route::post('/{its}/issue', [SurveyItsController::class, 'issue'])->name('issue');
            Route::post('/{its}/complete', [SurveyItsController::class, 'complete'])->name('complete');
            Route::post('/{its}/cancel', [SurveyItsController::class, 'cancel'])->name('cancel');
            Route::get('/{its}/print', [SurveyItsController::class, 'print'])->name('print');
            Route::post('/{its}/return', [SurveyItsController::class, 'saveReturn'])->name('return');
            Route::get('/blank-print', [SurveyItsController::class, 'printBlank'])->name('print-blank');
            // The Private (Cadastral) I to S on the Survey Department's form (user, 2026-11-10).
            Route::get('/private/{job}/print', [SurveyItsController::class, 'printPrivate'])->whereNumber('job')->name('print-private');
            Route::post('/private', [SurveyItsController::class, 'storePrivate'])->name('store-private');
        });
        // List of Coordinates and Field Observations, per I to S (2026-10-25). {its} = survey-<id> | cadastral-<id>.
        Route::prefix('coordinates')->name('coordinates.')->group(function () {
            Route::get('/', [SurveyCoordinateController::class, 'index'])->name('index');
            Route::get('/export', [SurveyCoordinateController::class, 'exportCoordinates'])->name('export');
            Route::get('/{its}', [SurveyCoordinateController::class, 'show'])->where('its', '(survey|cadastral)-[0-9]+')->name('show');
            Route::post('/{its}', [SurveyCoordinateController::class, 'storeCoordinate'])->where('its', '(survey|cadastral)-[0-9]+')->name('store');
            Route::put('/{its}/{coord}', [SurveyCoordinateController::class, 'updateCoordinate'])->where('its', '(survey|cadastral)-[0-9]+')->name('update');
            Route::post('/{its}/{coord}/move', [SurveyCoordinateController::class, 'moveCoordinate'])->where('its', '(survey|cadastral)-[0-9]+')->name('move');
            Route::delete('/{its}/{coord}', [SurveyCoordinateController::class, 'destroyCoordinate'])->where('its', '(survey|cadastral)-[0-9]+')->name('destroy');
        });
        Route::prefix('field-observations')->name('observations.')->group(function () {
            Route::get('/', [SurveyCoordinateController::class, 'observations'])->name('index');
            Route::get('/export', [SurveyCoordinateController::class, 'exportObservations'])->name('export');
            Route::get('/{its}', [SurveyCoordinateController::class, 'showObservations'])->where('its', '(survey|cadastral)-[0-9]+')->name('show');
            Route::post('/{its}', [SurveyCoordinateController::class, 'storeObservation'])->where('its', '(survey|cadastral)-[0-9]+')->name('store');
            Route::delete('/{its}/{obs}', [SurveyCoordinateController::class, 'destroyObservation'])->where('its', '(survey|cadastral)-[0-9]+')->name('destroy');
        });
        // Beacon Nos (checked against the Beacon Register) and the Sketch Plan on Field Observations (change list p.2, 2026-10-29).
        Route::get('/beacon-nos', [SurveyBeaconNosController::class, 'index'])->name('beacons.index');
        Route::get('/beacon-nos/export', [SurveyBeaconNosController::class, 'export'])->name('beacons.export');
        Route::get('/beacon-nos/{its}', [SurveyBeaconNosController::class, 'show'])->where('its', '(survey|cadastral)-[0-9]+')->name('beacons.show');
        Route::post('/field-observations/{its}/sketch-plan', [SurveySketchPlanController::class, 'store'])->where('its', '(survey|cadastral)-[0-9]+')->name('sketch.store');
        // Surveyors Report against an issued I to S, from the Survey Dept form (2026-10-25).
        Route::prefix('surveyors-report')->name('reports.')->group(function () {
            Route::get('/', [SurveyorReportController::class, 'index'])->name('index');
            Route::get('/export', [SurveyorReportController::class, 'export'])->name('export');
            Route::get('/create', [SurveyorReportController::class, 'create'])->name('create');
            Route::post('/', [SurveyorReportController::class, 'store'])->name('store');
            Route::get('/{report}/edit', [SurveyorReportController::class, 'edit'])->name('edit');
            Route::put('/{report}', [SurveyorReportController::class, 'update'])->name('update');
            Route::post('/{report}/submit', [SurveyorReportController::class, 'submit'])->name('submit');
            Route::delete('/{report}', [SurveyorReportController::class, 'destroy'])->name('destroy');
            Route::get('/{report}/print', [SurveyorReportController::class, 'print'])->name('print');
        });
        Route::get('/lands', [GknController::class, 'index'])->name('lands');
        Route::get('/register', [GknController::class, 'create'])->name('register');
        Route::post('/register', [GknController::class, 'store'])->name('register.store');
        // Backfill for a number picked in the global file number selector (2026-10-21).
        Route::get('/lookup', [GknController::class, 'lookup'])->name('lookup');
        Route::get('/{gkn}/edit', [GknController::class, 'edit'])->name('edit');
        Route::put('/{gkn}', [GknController::class, 'update'])->name('update');
        Route::delete('/{gkn}', [GknController::class, 'destroy'])->name('destroy');

        Route::get('/tracking', [GknController::class, 'tracking'])->name('tracking');
        Route::post('/tracking', [GknController::class, 'storeMovement'])->name('tracking.store');
        Route::post('/tracking/{movement}/receive', [GknController::class, 'receiveMovement'])->name('tracking.receive');

        Route::get('/reports', [ReportController::class, 'gkn'])->name('reports');
    });

    /* ------------------------------ Other records ------------------------------ */
    Route::prefix('records')->name('records.')->group(function () {
        Route::get('/misc', [MiscKnController::class, 'index'])->name('misc');
        Route::post('/misc', [MiscKnController::class, 'store'])->name('misc.store');
        Route::put('/misc/{misc}', [MiscKnController::class, 'update'])->name('misc.update');
        Route::delete('/misc/{misc}', [MiscKnController::class, 'destroy'])->name('misc.destroy');

        // Survey Department staff list (surveyors_list), user 2026-10-22
        Route::prefix('surveyors')->name('surveyors.')->group(function () {
            Route::get('/', [SurveyorListController::class, 'index'])->name('index');
            Route::get('/export', [SurveyorListController::class, 'export'])->name('export');
            Route::post('/', [SurveyorListController::class, 'store'])->name('store');
            Route::put('/{surveyor}', [SurveyorListController::class, 'update'])->name('update');
            Route::post('/{surveyor}/toggle', [SurveyorListController::class, 'toggle'])->name('toggle');
        });

        // Public Survey → Private Layout and Layout Records (user, 2026-10-30).
        Route::get('/private-layouts', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'index'])->name('private-layouts');
        Route::get('/private-layouts/export', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'export'])->name('private-layouts.export');
        Route::post('/private-layouts', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'store'])->name('private-layouts.store');
        Route::put('/private-layouts/{layout}', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'update'])->whereNumber('layout')->name('private-layouts.update');
        Route::delete('/private-layouts/{layout}', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'destroy'])->whereNumber('layout')->name('private-layouts.destroy');
        Route::get('/private-layouts/{layout}/plan', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'plan'])->whereNumber('layout')->name('private-layouts.plan');
        Route::get('/layout-records', [\App\Http\Controllers\Survey\LayoutRecordsController::class, 'index'])->name('layout-records');
        Route::get('/layout-records/export', [\App\Http\Controllers\Survey\LayoutRecordsController::class, 'export'])->name('layout-records.export');
        // TP Nos register, by serial number (user, 2026-10-30).
        Route::get('/tp-numbers', [\App\Http\Controllers\Survey\TpNumberController::class, 'index'])->name('tp-numbers');
        Route::get('/tp-numbers/export', [\App\Http\Controllers\Survey\TpNumberController::class, 'export'])->name('tp-numbers.export');
        Route::post('/tp-numbers', [\App\Http\Controllers\Survey\TpNumberController::class, 'store'])->name('tp-numbers.store');
        Route::put('/tp-numbers/{tp}', [\App\Http\Controllers\Survey\TpNumberController::class, 'update'])->whereNumber('tp')->name('tp-numbers.update');
        Route::delete('/tp-numbers/{tp}', [\App\Http\Controllers\Survey\TpNumberController::class, 'destroy'])->whereNumber('tp')->name('tp-numbers.destroy');

        Route::get('/lpkn', [LpknController::class, 'index'])->name('lpkn');
        Route::get('/lpkn/export', [LpknController::class, 'export'])->name('lpkn.export');
        foreach (['instruction', 'report', 'coordinates', 'observations'] as $section) {
            Route::get('/lpkn/' . $section, [LpknController::class, 'section'])
                ->defaults('section', $section)->name('lpkn.' . $section);
        }
        Route::put('/lpkn/{lpkn}/instruction', [LpknController::class, 'saveInstruction'])->name('lpkn.instruction.save');
        Route::put('/lpkn/{lpkn}/report', [LpknController::class, 'saveReport'])->name('lpkn.report.save');
        Route::post('/lpkn/{lpkn}/coordinates', [LpknController::class, 'saveCoordinate'])->name('lpkn.coordinates.save');
        Route::delete('/lpkn/{lpkn}/coordinates/{coordinate}', [LpknController::class, 'deleteCoordinate'])->name('lpkn.coordinates.delete');
        Route::post('/lpkn', [LpknController::class, 'store'])->name('lpkn.store');
        Route::put('/lpkn/{lpkn}', [LpknController::class, 'update'])->name('lpkn.update');
        Route::delete('/lpkn/{lpkn}', [LpknController::class, 'destroy'])->name('lpkn.destroy');
    });

    /* -------------------------------- Workflow -------------------------------- */
    Route::prefix('workflow')->name('workflow.')->group(function () {
        // Checking & Passing (Survey Plan) > Uploaded Survey Plan: plans sent to Examination/Verification (Survey Update 5i).
        Route::get('/uploaded-survey-plans', [\App\Http\Controllers\Survey\SurveyPlanUploadController::class, 'examination'])->name('uploaded-plans');
        // Surveyor's Report (Private): List of Coordinates, Beacon Nos, Field Observations and Sketch Plan
        // on the report (change list p.2, 2026-10-29). The GKN editors, anchored "private-<report id>".
        Route::prefix('private-report')->name('private.')->group(function () {
            $p = 'private-[0-9]+';
            Route::get('/coordinates/{its}', [SurveyCoordinateController::class, 'show'])->where('its', $p)->name('coordinates.show');
            Route::post('/coordinates/{its}', [SurveyCoordinateController::class, 'storeCoordinate'])->where('its', $p)->name('coordinates.store');
            Route::put('/coordinates/{its}/{coord}', [SurveyCoordinateController::class, 'updateCoordinate'])->where('its', $p)->name('coordinates.update');
            Route::post('/coordinates/{its}/{coord}/move', [SurveyCoordinateController::class, 'moveCoordinate'])->where('its', $p)->name('coordinates.move');
            Route::delete('/coordinates/{its}/{coord}', [SurveyCoordinateController::class, 'destroyCoordinate'])->where('its', $p)->name('coordinates.destroy');
            Route::get('/field-observations/{its}', [SurveyCoordinateController::class, 'showObservations'])->where('its', $p)->name('observations.show');
            Route::post('/field-observations/{its}', [SurveyCoordinateController::class, 'storeObservation'])->where('its', $p)->name('observations.store');
            Route::delete('/field-observations/{its}/{obs}', [SurveyCoordinateController::class, 'destroyObservation'])->where('its', $p)->name('observations.destroy');
            Route::post('/field-observations/{its}/sketch-plan', [SurveySketchPlanController::class, 'store'])->where('its', $p)->name('sketch.store');
            Route::get('/beacon-nos/{its}', [SurveyBeaconNosController::class, 'show'])->where('its', $p)->name('beacons.show');
        });

        Route::get('/examination', [ExaminationController::class, 'index'])->name('examination');
        Route::post('/examination', [ExaminationController::class, 'store'])->name('examination.store');
        Route::post('/examination/{examination}/pass', [ExaminationController::class, 'pass'])->name('examination.pass');
        Route::post('/examination/{examination}/return', [ExaminationController::class, 'returnItem'])->name('examination.return');
        Route::delete('/examination/{examination}', [ExaminationController::class, 'destroy'])->name('examination.destroy');
        // Register File (2026-10-25): files from Cadastral (auto) and registered here; Verification only.
        Route::prefix('register-file')->name('register-file.')->group(function () {
            Route::get('/', [RegisterFileController::class, 'index'])->name('index');
            Route::get('/export', [RegisterFileController::class, 'export'])->name('export');
            Route::get('/create', [RegisterFileController::class, 'create'])->name('create');
            Route::post('/', [RegisterFileController::class, 'store'])->name('store');
            Route::post('/{file}/status', [RegisterFileController::class, 'status'])->name('status');
            Route::get('/lookup/file', [RegisterFileController::class, 'lookupFile'])->name('lookup.file');
            Route::get('/lookup/files', [RegisterFileController::class, 'lookupFiles'])->name('lookup.files');
            Route::get('/lookup/file-status', [RegisterFileController::class, 'lookupFileStatus'])->name('lookup.file-status');
        });
        // Cadastral Dept Verification files, read-only from Survey (2026-10-21).
        Route::get('/cadastral-files', [CadastralFilesController::class, 'index'])->name('cadastral-files');

        Route::get('/occupancy', [OpController::class, 'workflow'])->name('occupancy');

        // Boundary Dispute, Old Surveys, Surveyors' Report (Changes) (2026-10-25)
        Route::prefix('boundary-disputes')->name('boundary-disputes.')->group(function () {
            Route::get('/', [BoundaryDisputeController::class, 'index'])->name('index');
            Route::get('/export', [BoundaryDisputeController::class, 'export'])->name('export');
            Route::post('/', [BoundaryDisputeController::class, 'store'])->name('store');
            Route::get('/{dispute}/edit', [BoundaryDisputeController::class, 'edit'])->name('edit');
            Route::put('/{dispute}', [BoundaryDisputeController::class, 'update'])->name('update');
            Route::post('/{dispute}/status', [BoundaryDisputeController::class, 'status'])->name('status');
            Route::delete('/{dispute}', [BoundaryDisputeController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('old-surveys')->name('old-surveys.')->group(function () {
            Route::get('/', [OldSurveyController::class, 'index'])->name('index');
            Route::get('/export', [OldSurveyController::class, 'export'])->name('export');
            Route::post('/', [OldSurveyController::class, 'store'])->name('store');
            Route::get('/{old}/edit', [OldSurveyController::class, 'edit'])->name('edit');
            Route::put('/{old}', [OldSurveyController::class, 'update'])->name('update');
            Route::post('/{old}/verify', [OldSurveyController::class, 'verify'])->name('verify');
            Route::delete('/{old}', [OldSurveyController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('change-reports')->name('change-reports.')->group(function () {
            Route::get('/', [ChangeReportController::class, 'index'])->name('index');
            Route::get('/export', [ChangeReportController::class, 'export'])->name('export');
            Route::get('/create', [ChangeReportController::class, 'create'])->name('create');
            Route::post('/', [ChangeReportController::class, 'store'])->name('store');
            Route::get('/{report}/edit', [ChangeReportController::class, 'edit'])->name('edit');
            Route::put('/{report}', [ChangeReportController::class, 'update'])->name('update');
            Route::post('/{report}/issue', [ChangeReportController::class, 'issue'])->name('issue');
            Route::delete('/{report}', [ChangeReportController::class, 'destroy'])->name('destroy');
            Route::get('/{report}/print', [ChangeReportController::class, 'print'])->name('print');
        });

        // Coordinate Register: every coordinate in the module, read-only (2026-10-25)
        Route::get('/coordinate-register', [CoordinateRegisterController::class, 'index'])->name('coordinate-register.index');
        Route::get('/coordinate-register/export', [CoordinateRegisterController::class, 'export'])->name('coordinate-register.export');

        // Control Points: a copy of the Beacon Register in its own tables (user, 2026-10-25)
        Route::prefix('control-points')->name('control-points.')->group(function () {
            Route::get('/', [ControlPointRegisterController::class, 'index'])->name('index');
            Route::get('/export', [ControlPointRegisterController::class, 'export'])->name('export');
            Route::post('/', [ControlPointRegisterController::class, 'store'])->name('store');
            Route::put('/{entry}', [ControlPointRegisterController::class, 'update'])->name('update');
            Route::delete('/{entry}', [ControlPointRegisterController::class, 'destroy'])->name('destroy');
            Route::post('/{entry}/review', [ControlPointRegisterController::class, 'review'])->name('review');
            Route::post('/{entry}/audit', [ControlPointRegisterController::class, 'audit'])->name('audit');
        });

        // SURCON Beacon Register (user, 2026-10-22)
        Route::prefix('beacon-register')->name('beacon-register.')->group(function () {
            Route::get('/', [BeaconRegisterController::class, 'index'])->name('index');
            Route::get('/export', [BeaconRegisterController::class, 'export'])->name('export');
            // By issue: the entries of one letter group, opened in place (user, 2026-11-06).
            Route::get('/group-rows', [BeaconRegisterController::class, 'groupRows'])->name('group-rows');
            // Rev-M payment lookup for the Payment section, and the printed book (2026-10-25).
            Route::get('/revm-lookup', [BeaconRegisterController::class, 'revmLookup'])->name('revm-lookup');
            Route::get('/print', [BeaconRegisterController::class, 'printBook'])->name('print');
            // Issue Beacon No (user, 2026-10-31): the next free serial for a prefix (and optional letter).
            Route::get('/next-serial', [BeaconRegisterController::class, 'nextSerial'])->name('next-serial');
            // Issue Beacon No: numbers of a run already in the register (duplicate check, user 2026-11-02).
            Route::get('/check-range', [BeaconRegisterController::class, 'checkRange'])->name('check-range');
            // CR 03112026: the next public numbers from the pool, and the Duplicate Checker.
            Route::get('/next-public', [BeaconRegisterController::class, 'nextPublic'])->name('next-public');
            Route::post('/duplicate-check', [BeaconRegisterController::class, 'duplicateCheck'])->name('duplicate-check');
            // Issue Beacon No: save every group in the table as its own entry (user, 2026-11-02).
            Route::post('/issue', [BeaconRegisterController::class, 'issueBatch'])->name('issue');
            Route::post('/', [BeaconRegisterController::class, 'store'])->name('store');
            Route::put('/{entry}', [BeaconRegisterController::class, 'update'])->name('update');
            Route::delete('/{entry}', [BeaconRegisterController::class, 'destroy'])->name('destroy');
            Route::post('/{entry}/review', [BeaconRegisterController::class, 'review'])->name('review');
            Route::post('/{entry}/audit', [BeaconRegisterController::class, 'audit'])->name('audit');
        });
    });

    /* ------------------- Beacon Requests (the SG's desk, CR 03112026) ------------------- */
    Route::prefix('beacon-requests')->name('beacon-requests.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'export'])->name('export');
        Route::post('/{request}/issue', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'issue'])->whereNumber('request')->name('issue');
        Route::post('/{request}/reject', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'reject'])->whereNumber('request')->name('reject');
        Route::get('/{request}/payment-evidence', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'paymentEvidence'])->whereNumber('request')->name('payment-evidence');
        Route::get('/{request}/slip', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'slip'])->whereNumber('request')->name('slip');
        Route::get('/accounts/{account}/passport', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'passport'])->whereNumber('account')->name('accounts.passport');
        Route::post('/accounts/{account}/approve', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'approveAccount'])->whereNumber('account')->name('accounts.approve');
        Route::post('/accounts/{account}/reject', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'rejectAccount'])->whereNumber('account')->name('accounts.reject');
        Route::post('/companies/{company}/approve', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'approveCompany'])->whereNumber('company')->name('companies.approve');
        // Add Company / Add Surveyor on Registrations (user, 2026-11-10): the SG's own forms, approved at once.
        Route::post('/companies', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'storeCompany'])->name('companies.store');
        Route::post('/accounts', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'storeSurveyor'])->name('accounts.store');
        Route::post('/companies/{company}/reject', [\App\Http\Controllers\Survey\BeaconRequestController::class, 'rejectCompany'])->whereNumber('company')->name('companies.reject');
    });

    /* ---------------------------------- Tools ---------------------------------- */
    Route::prefix('tools')->name('tools.')->group(function () {
        Route::get('/gis', [DashboardController::class, 'gis'])->name('gis');

        Route::get('/plot-allocation', [PlotAllocationController::class, 'index'])->name('plot-allocation');
        Route::post('/plot-allocation', [PlotAllocationController::class, 'store'])->name('plot-allocation.store');
        Route::post('/plot-allocation/add-row', [PlotAllocationController::class, 'addRow'])->name('plot-allocation.add-row');
        Route::put('/plot-allocation/{plot}', [PlotAllocationController::class, 'update'])->name('plot-allocation.update');
        Route::delete('/plot-allocation/{plot}', [PlotAllocationController::class, 'destroy'])->name('plot-allocation.destroy');
        Route::get('/plot-allocation/export', [PlotAllocationController::class, 'export'])->name('plot-allocation.export');
    });

    /* ------------- Geo-informatics → SDI (ported from ALAES, 2026-10-29) ------------- */
    Route::prefix('sdi')->name('sdi.')->group(function () {
        Route::get('/map-requests', [SdiMapRequestController::class, 'index'])->name('map-requests');
        Route::get('/map-requests/export', [SdiMapRequestController::class, 'export'])->name('map-requests.export');
        Route::get('/map-requests/create', [SdiMapRequestController::class, 'create'])->name('map-requests.create');
        Route::get('/map-requests/land-use-points', [SdiMapRequestController::class, 'landUsePoints'])->name('map-requests.land-use-points');
        Route::post('/map-requests', [SdiMapRequestController::class, 'store'])->name('map-requests.store');
        Route::get('/map-requests/{mapRequest}/request-file', [SdiMapRequestController::class, 'requestFile'])->name('map-requests.request-file');
        Route::get('/map-requests/{mapRequest}', [SdiMapRequestController::class, 'show'])->whereNumber('mapRequest')->name('map-requests.show');
        Route::get('/map-requests/{mapRequest}/edit', [SdiMapRequestController::class, 'edit'])->whereNumber('mapRequest')->name('map-requests.edit');
        Route::put('/map-requests/{mapRequest}', [SdiMapRequestController::class, 'update'])->whereNumber('mapRequest')->name('map-requests.update');
        Route::delete('/map-requests/{mapRequest}', [SdiMapRequestController::class, 'destroy'])->whereNumber('mapRequest')->name('map-requests.destroy');
        Route::get('/map-requests/{mapRequest}/map', [SdiMapRequestController::class, 'deliverable'])->whereNumber('mapRequest')->name('map-requests.deliverable');

        // Geo-informatics > Basemap (Satellite Imagery) (user, 2026-11-03): the SDI maps on the satellite basemap.
        Route::get('/basemap', [SdiMapRequestController::class, 'basemap'])->name('basemap');
        Route::get('/production', [SdiProductionController::class, 'index'])->name('production');
        Route::get('/production/export', [SdiProductionController::class, 'export'])->name('production.export');
        Route::post('/production/{mapRequest}/start', [SdiProductionController::class, 'start'])->whereNumber('mapRequest')->name('production.start');
        Route::post('/production/{mapRequest}/fulfil', [SdiProductionController::class, 'fulfil'])->whereNumber('mapRequest')->name('production.fulfil');
        Route::post('/production/{mapRequest}/reject', [SdiProductionController::class, 'reject'])->whereNumber('mapRequest')->name('production.reject');

        Route::get('/registry', [SdiRegistryController::class, 'index'])->name('registry');
        Route::get('/registry/export', [SdiRegistryController::class, 'export'])->name('registry.export');
        Route::post('/registry/mdas', [SdiRegistryController::class, 'storeMda'])->name('registry.mdas.store');
        Route::put('/registry/mdas/{mda}', [SdiRegistryController::class, 'updateMda'])->whereNumber('mda')->name('registry.mdas.update');
        Route::post('/registry/mdas/{mda}/toggle', [SdiRegistryController::class, 'toggleMda'])->whereNumber('mda')->name('registry.mdas.toggle');
        Route::post('/registry/map-types', [SdiRegistryController::class, 'storeType'])->name('registry.types.store');
        Route::put('/registry/map-types/{mapType}', [SdiRegistryController::class, 'updateType'])->whereNumber('mapType')->name('registry.types.update');
        Route::post('/registry/map-types/{mapType}/toggle', [SdiRegistryController::class, 'toggleType'])->whereNumber('mapType')->name('registry.types.toggle');
    });

    Route::get('/reports', [ReportController::class, 'index'])->name('reports');

    /* ------------------------ Address-builder lookups ------------------------ */
    Route::prefix('lookup')->name('lookup.')->group(function () {
        Route::get('/districts', [LookupController::class, 'districts'])->name('districts');
        Route::get('/streets', [LookupController::class, 'streets'])->name('streets');
        Route::get('/lgas', [LookupController::class, 'lgas'])->name('lgas');
        Route::get('/states', [LookupController::class, 'states'])->name('states');
        // A file number's details, for any number picked in the global file number selector.
        Route::get('/file', [SurveyItsController::class, 'lookup'])->name('file');
    });
});

/*
|--------------------------------------------------------------------------
| Survey Mobile — Register Compensation Case in the field
|--------------------------------------------------------------------------
| Its own URL and login page, like the VFC and File Tracker mobile apps. The
| login routes sit outside `auth` so a signed-out officer is not bounced to the
| desktop login; RequireLoginOtp still asks for the sign-in code.
*/
Route::prefix('survey-module/mobile')->name('survey-module.mobile.')->group(function () {
    Route::middleware([\App\Http\Middleware\RememberSurveyMobileApp::class])->group(function () {
        // Offline support: keep the app-specific context for the worker and manifest.
        Route::get('/sw.js', [MobileOfflineController::class, 'serviceWorker'])->name('sw');
        Route::get('/manifest.webmanifest', [MobileOfflineController::class, 'manifest'])->name('manifest');
        Route::get('/offline', [MobileOfflineController::class, 'offline'])->name('offline');

        Route::get('/login', [MobileCaseController::class, 'loginForm'])->name('login');
        Route::post('/login', [MobileCaseController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
        Route::post('/logout', [MobileCaseController::class, 'logout'])->name('logout');

        Route::middleware(['auth'])->group(function () {
            Route::get('/', [MobileCaseController::class, 'index'])->name('index');
            Route::get('/register', [MobileCaseController::class, 'create'])->name('register');
            Route::post('/cases', [MobileCaseController::class, 'store'])->name('cases.store');
            Route::get('/cases/{case}', [MobileCaseController::class, 'show'])->name('cases.show');
            Route::post('/cases/{case}/submit', [MobileCaseController::class, 'submit'])->name('cases.submit');

            Route::get('/session', [MobileOfflineController::class, 'session'])->name('session');
            Route::post('/cases/{case}/photos', [MobileOfflineController::class, 'storePhoto'])->name('cases.photos.store');
            Route::get('/cases/{case}/photos/{photo}', [MobileOfflineController::class, 'showPhoto'])->name('cases.photos.show');
            Route::get('/observations', [MobileObservationController::class, 'index'])->name('observations');
            Route::get('/observations/{its}', [MobileObservationController::class, 'show'])->where('its', '(survey|cadastral|private)-[0-9]+')->name('observations.show');
            Route::post('/observations/{its}', [MobileObservationController::class, 'store'])->where('its', '(survey|cadastral|private)-[0-9]+')->name('observations.store');
            Route::post('/observations/{its}/coordinates', [MobileObservationController::class, 'storeCoordinate'])->where('its', '(survey|cadastral|private)-[0-9]+')->name('observations.coords.store');
            Route::post('/observations/{its}/sketch-plan', [SurveySketchPlanController::class, 'store'])->where('its', '(survey|cadastral|private)-[0-9]+')->name('observations.sketch.store');

            Route::prefix('plots')->name('plots.')->group(function () {
                Route::get('/', [MobilePlotController::class, 'index'])->name('index');
                Route::get('/ops', [MobilePlotController::class, 'ops'])->name('ops');
                Route::get('/ops/{op}', [MobilePlotController::class, 'op'])->where('op', '(capture|pra)-[0-9]+')->name('op');
                Route::post('/ops/{op}/showings', [MobilePlotController::class, 'storeShowing'])->where('op', '(capture|pra)-[0-9]+')->name('showings.store');
                Route::post('/showings/{showing}/photos', [MobilePlotController::class, 'storeShowingPhoto'])->whereNumber('showing')->name('showings.photos.store');
                Route::get('/showing-photos/{photo}', [MobilePlotController::class, 'showingPhoto'])->whereNumber('photo')->name('showings.photos.show');
                Route::get('/checks/new', [MobilePlotController::class, 'createCheck'])->name('checks.create');
                Route::post('/checks', [MobilePlotController::class, 'storeCheck'])->name('checks.store');
                Route::get('/checks/{check}', [MobilePlotController::class, 'check'])->whereNumber('check')->name('checks.show');
                Route::post('/checks/{check}/photos', [MobilePlotController::class, 'storeCheckPhoto'])->whereNumber('check')->name('checks.photos.store');
                Route::get('/check-photos/{photo}', [MobilePlotController::class, 'checkPhoto'])->whereNumber('photo')->name('checks.photos.show');
            });
        });
    });
});
