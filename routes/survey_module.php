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

    /* ------------------------------ Compensation ------------------------------ */
    Route::prefix('compensation')->name('compensation.')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'compensation'])->name('dashboard');

        // CO files (CO-YYYY-0001), one to many projects (user, 2026-10-25)
        // Two lists (user, 2026-10-30): CO Files = captured files, Commission CO File = commissioned files.
        Route::get('/co-files', [CoFileController::class, 'index'])->name('co-files');
        Route::get('/co-files/commissioned', [CoFileController::class, 'commissioned'])->name('co-files.commissioned');
        Route::get('/co-files/commission', [CoFileController::class, 'create'])->name('co-files.create');
        Route::get('/co-files/related-lookup', [CoFileController::class, 'relatedLookup'])->name('co-files.related-lookup');
        Route::get('/co-files/number-lookup', [CoFileController::class, 'numberLookup'])->name('co-files.number-lookup');
        Route::get('/co-files/options', [CoFileController::class, 'options'])->name('co-files.options');
        Route::post('/co-files', [CoFileController::class, 'store'])->name('co-files.store');
        // Commissioning Sheet (CR 30102026 item 2): the MLPP sheet, issued by the Survey Department.
        Route::get('/co-files/{coFile}/commissioning-sheet', [CommissioningSheetController::class, 'coFile'])->name('co-files.commissioning-sheet');
        Route::get('/co-files/{coFile}/edit', [CoFileController::class, 'edit'])->name('co-files.edit');
        Route::put('/co-files/{coFile}', [CoFileController::class, 'update'])->name('co-files.update');
        Route::delete('/co-files/{coFile}', [CoFileController::class, 'destroy'])->name('co-files.destroy');

        // Allocation Records: surveyor portions per project, read-only (2026-10-25)
        Route::get('/allocation-records', [AllocationRecordController::class, 'index'])->name('allocation-records');

        // Letter of Grant: every Direct Allocation OP, from Deeds (2026-10-30).
        Route::get('/letter-of-grant', [\App\Http\Controllers\Survey\LetterOfGrantController::class, 'index'])->name('letter-of-grant');

        // Plot Confirmation register: OP showings and ground checks from the field app (2026-10-30).
        Route::get('/plot-confirmations', [PlotConfirmationController::class, 'index'])->name('plot-confirmations');

        // Resettlement Calculator: plots = area ÷ plot size by land use / density (SG formulas, 2026-10-29).
        Route::get('/resettlement-calculator', [ResettlementCalculatorController::class, 'index'])->name('resettlement-calculator');
        Route::post('/resettlement-calculator', [ResettlementCalculatorController::class, 'store'])->name('resettlement-calculator.store');
        Route::post('/resettlement-calculator/rates', [ResettlementCalculatorController::class, 'storeRate'])->name('resettlement-calculator.rates.store');
        Route::put('/resettlement-calculator/rates/{rate}', [ResettlementCalculatorController::class, 'updateRate'])->whereNumber('rate')->name('resettlement-calculator.rates.update');
        Route::post('/resettlement-calculator/rates/{rate}/toggle', [ResettlementCalculatorController::class, 'toggleRate'])->whereNumber('rate')->name('resettlement-calculator.rates.toggle');

        // Complaints (2026-10-25)
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints');
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
        Route::get('/complaints/print-blank', [ComplaintController::class, 'printBlank'])->name('complaints.print-blank');
        Route::get('/complaints/{complaint}/print', [ComplaintController::class, 'print'])->name('complaints.print');
        Route::get('/complaints/{complaint}/edit', [ComplaintController::class, 'edit'])->name('complaints.edit');
        Route::put('/complaints/{complaint}', [ComplaintController::class, 'update'])->name('complaints.update');
        Route::post('/complaints/{complaint}/status', [ComplaintController::class, 'status'])->name('complaints.status');
        Route::delete('/complaints/{complaint}', [ComplaintController::class, 'destroy'])->name('complaints.destroy');

        // Projects — scheme type is set here and inherited by cases
        Route::get('/projects', [ProjectController::class, 'index'])->name('projects');
        Route::get('/projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
        Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

        // Cases — the six-step register
        Route::get('/cases', [CaseController::class, 'index'])->name('cases');
        Route::get('/cases/register', [CaseController::class, 'create'])->name('cases.register');
        // Capture Compensation List: paper lists keyed in by hand (user, 2026-10-30)
        Route::get('/capture-list', [\App\Http\Controllers\Survey\CompensationCaptureController::class, 'create'])->name('capture-list');
        Route::post('/capture-list', [\App\Http\Controllers\Survey\CompensationCaptureController::class, 'store'])->name('capture-list.store');
        Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
        Route::get('/cases/{case}', [CaseController::class, 'show'])->name('cases.show');
        Route::get('/cases/{case}/edit', [CaseController::class, 'edit'])->name('cases.edit');
        Route::put('/cases/{case}', [CaseController::class, 'update'])->name('cases.update');
        Route::delete('/cases/{case}', [CaseController::class, 'destroy'])->name('cases.destroy');
        Route::post('/cases/{case}/submit', [CaseController::class, 'submit'])->name('cases.submit');

        // Beneficiaries
        Route::get('/beneficiaries', [BeneficiaryController::class, 'index'])->name('beneficiaries');
        Route::post('/beneficiaries', [BeneficiaryController::class, 'store'])->name('beneficiaries.store');
        Route::get('/beneficiaries/{beneficiary}/edit', [BeneficiaryController::class, 'edit'])->name('beneficiaries.edit');
        Route::put('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'update'])->name('beneficiaries.update');
        Route::delete('/beneficiaries/{beneficiary}', [BeneficiaryController::class, 'destroy'])->name('beneficiaries.destroy');

        // Economic trees — catalogue plus per-case lines
        Route::get('/trees', [TreeController::class, 'index'])->name('trees');
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
        Route::post('/op/generate', [OpController::class, 'generate'])->name('op.generate');
        Route::post('/op/{op}/advance', [OpController::class, 'advance'])->name('op.advance');
        Route::delete('/op/{op}', [OpController::class, 'destroy'])->name('op.destroy');

        Route::get('/reports', [ReportController::class, 'compensation'])->name('reports');
    });

    /* ---------------------------------- GKN ---------------------------------- */
    Route::prefix('gkn')->name('gkn.')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'gkn'])->name('dashboard');
        // Applications: GKN / LPKN / LPPL / Misc KN files in one typed table (2026-10-25).
        Route::get('/applications', [GknApplicationController::class, 'index'])->name('applications');
        // Commissioning Sheet for GKN, Misc KN, LPKN, LPPL and RDKN files (CR 30102026 item 2).
        Route::get('/applications/{id}/commissioning-sheet', [CommissioningSheetController::class, 'applicationFile'])->whereNumber('id')->name('applications.commissioning-sheet');
        // Computation Files (Field Jackets), CR 30102026 item 12: blank table, source pending (2026-10-31).
        Route::view('/computation-files', 'survey_module.gkn.computation_files')->name('computation-files');
        // Instruction to Survey: Survey's own jobs plus Cadastral's, read-only (2026-10-25).
        Route::prefix('instruction-to-survey')->name('its.')->group(function () {
            Route::get('/', [SurveyItsController::class, 'index'])->name('index');
            Route::get('/create', [SurveyItsController::class, 'create'])->name('create');
            Route::post('/', [SurveyItsController::class, 'store'])->name('store');
            Route::get('/lookup', [SurveyItsController::class, 'lookup'])->name('lookup');
            Route::get('/{its}/edit', [SurveyItsController::class, 'edit'])->name('edit');
            Route::put('/{its}', [SurveyItsController::class, 'update'])->name('update');
            Route::post('/{its}/issue', [SurveyItsController::class, 'issue'])->name('issue');
            Route::post('/{its}/complete', [SurveyItsController::class, 'complete'])->name('complete');
            Route::post('/{its}/cancel', [SurveyItsController::class, 'cancel'])->name('cancel');
            Route::get('/{its}/print', [SurveyItsController::class, 'print'])->name('print');
        });
        // List of Coordinates and Field Observations, per I to S (2026-10-25). {its} = survey-<id> | cadastral-<id>.
        Route::prefix('coordinates')->name('coordinates.')->group(function () {
            Route::get('/', [SurveyCoordinateController::class, 'index'])->name('index');
            Route::get('/{its}', [SurveyCoordinateController::class, 'show'])->where('its', '(survey|cadastral)-[0-9]+')->name('show');
            Route::post('/{its}', [SurveyCoordinateController::class, 'storeCoordinate'])->where('its', '(survey|cadastral)-[0-9]+')->name('store');
            Route::put('/{its}/{coord}', [SurveyCoordinateController::class, 'updateCoordinate'])->where('its', '(survey|cadastral)-[0-9]+')->name('update');
            Route::post('/{its}/{coord}/move', [SurveyCoordinateController::class, 'moveCoordinate'])->where('its', '(survey|cadastral)-[0-9]+')->name('move');
            Route::delete('/{its}/{coord}', [SurveyCoordinateController::class, 'destroyCoordinate'])->where('its', '(survey|cadastral)-[0-9]+')->name('destroy');
        });
        Route::prefix('field-observations')->name('observations.')->group(function () {
            Route::get('/', [SurveyCoordinateController::class, 'observations'])->name('index');
            Route::get('/{its}', [SurveyCoordinateController::class, 'showObservations'])->where('its', '(survey|cadastral)-[0-9]+')->name('show');
            Route::post('/{its}', [SurveyCoordinateController::class, 'storeObservation'])->where('its', '(survey|cadastral)-[0-9]+')->name('store');
            Route::delete('/{its}/{obs}', [SurveyCoordinateController::class, 'destroyObservation'])->where('its', '(survey|cadastral)-[0-9]+')->name('destroy');
        });
        // Beacon Nos (checked against the Beacon Register) and the Sketch Plan on Field Observations (change list p.2, 2026-10-29).
        Route::get('/beacon-nos', [SurveyBeaconNosController::class, 'index'])->name('beacons.index');
        Route::get('/beacon-nos/{its}', [SurveyBeaconNosController::class, 'show'])->where('its', '(survey|cadastral)-[0-9]+')->name('beacons.show');
        Route::post('/field-observations/{its}/sketch-plan', [SurveySketchPlanController::class, 'store'])->where('its', '(survey|cadastral)-[0-9]+')->name('sketch.store');
        // Surveyors Report against an issued I to S, from the Survey Dept form (2026-10-25).
        Route::prefix('surveyors-report')->name('reports.')->group(function () {
            Route::get('/', [SurveyorReportController::class, 'index'])->name('index');
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
            Route::post('/', [SurveyorListController::class, 'store'])->name('store');
            Route::put('/{surveyor}', [SurveyorListController::class, 'update'])->name('update');
            Route::post('/{surveyor}/toggle', [SurveyorListController::class, 'toggle'])->name('toggle');
        });

        // Public Survey → Private Layout and Layout Records (user, 2026-10-30).
        Route::get('/private-layouts', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'index'])->name('private-layouts');
        Route::post('/private-layouts', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'store'])->name('private-layouts.store');
        Route::put('/private-layouts/{layout}', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'update'])->whereNumber('layout')->name('private-layouts.update');
        Route::delete('/private-layouts/{layout}', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'destroy'])->whereNumber('layout')->name('private-layouts.destroy');
        Route::get('/private-layouts/{layout}/plan', [\App\Http\Controllers\Survey\PrivateLayoutController::class, 'plan'])->whereNumber('layout')->name('private-layouts.plan');
        Route::get('/layout-records', [\App\Http\Controllers\Survey\LayoutRecordsController::class, 'index'])->name('layout-records');
        // TP Nos register, by serial number (user, 2026-10-30).
        Route::get('/tp-numbers', [\App\Http\Controllers\Survey\TpNumberController::class, 'index'])->name('tp-numbers');
        Route::post('/tp-numbers', [\App\Http\Controllers\Survey\TpNumberController::class, 'store'])->name('tp-numbers.store');
        Route::put('/tp-numbers/{tp}', [\App\Http\Controllers\Survey\TpNumberController::class, 'update'])->whereNumber('tp')->name('tp-numbers.update');
        Route::delete('/tp-numbers/{tp}', [\App\Http\Controllers\Survey\TpNumberController::class, 'destroy'])->whereNumber('tp')->name('tp-numbers.destroy');

        Route::get('/lpkn', [LpknController::class, 'index'])->name('lpkn');
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
            Route::post('/', [BoundaryDisputeController::class, 'store'])->name('store');
            Route::get('/{dispute}/edit', [BoundaryDisputeController::class, 'edit'])->name('edit');
            Route::put('/{dispute}', [BoundaryDisputeController::class, 'update'])->name('update');
            Route::post('/{dispute}/status', [BoundaryDisputeController::class, 'status'])->name('status');
            Route::delete('/{dispute}', [BoundaryDisputeController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('old-surveys')->name('old-surveys.')->group(function () {
            Route::get('/', [OldSurveyController::class, 'index'])->name('index');
            Route::post('/', [OldSurveyController::class, 'store'])->name('store');
            Route::get('/{old}/edit', [OldSurveyController::class, 'edit'])->name('edit');
            Route::put('/{old}', [OldSurveyController::class, 'update'])->name('update');
            Route::post('/{old}/verify', [OldSurveyController::class, 'verify'])->name('verify');
            Route::delete('/{old}', [OldSurveyController::class, 'destroy'])->name('destroy');
        });
        Route::prefix('change-reports')->name('change-reports.')->group(function () {
            Route::get('/', [ChangeReportController::class, 'index'])->name('index');
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
            Route::post('/', [ControlPointRegisterController::class, 'store'])->name('store');
            Route::put('/{entry}', [ControlPointRegisterController::class, 'update'])->name('update');
            Route::delete('/{entry}', [ControlPointRegisterController::class, 'destroy'])->name('destroy');
            Route::post('/{entry}/review', [ControlPointRegisterController::class, 'review'])->name('review');
            Route::post('/{entry}/audit', [ControlPointRegisterController::class, 'audit'])->name('audit');
        });

        // SURCON Beacon Register (user, 2026-10-22)
        Route::prefix('beacon-register')->name('beacon-register.')->group(function () {
            Route::get('/', [BeaconRegisterController::class, 'index'])->name('index');
            // Rev-M payment lookup for the Payment section, and the printed book (2026-10-25).
            Route::get('/revm-lookup', [BeaconRegisterController::class, 'revmLookup'])->name('revm-lookup');
            Route::get('/print', [BeaconRegisterController::class, 'printBook'])->name('print');
            Route::post('/', [BeaconRegisterController::class, 'store'])->name('store');
            Route::put('/{entry}', [BeaconRegisterController::class, 'update'])->name('update');
            Route::delete('/{entry}', [BeaconRegisterController::class, 'destroy'])->name('destroy');
            Route::post('/{entry}/review', [BeaconRegisterController::class, 'review'])->name('review');
            Route::post('/{entry}/audit', [BeaconRegisterController::class, 'audit'])->name('audit');
        });
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
        Route::get('/map-requests/create', [SdiMapRequestController::class, 'create'])->name('map-requests.create');
        Route::get('/map-requests/land-use-points', [SdiMapRequestController::class, 'landUsePoints'])->name('map-requests.land-use-points');
        Route::post('/map-requests', [SdiMapRequestController::class, 'store'])->name('map-requests.store');
        Route::get('/map-requests/{mapRequest}', [SdiMapRequestController::class, 'show'])->whereNumber('mapRequest')->name('map-requests.show');
        Route::get('/map-requests/{mapRequest}/edit', [SdiMapRequestController::class, 'edit'])->whereNumber('mapRequest')->name('map-requests.edit');
        Route::put('/map-requests/{mapRequest}', [SdiMapRequestController::class, 'update'])->whereNumber('mapRequest')->name('map-requests.update');
        Route::delete('/map-requests/{mapRequest}', [SdiMapRequestController::class, 'destroy'])->whereNumber('mapRequest')->name('map-requests.destroy');
        Route::get('/map-requests/{mapRequest}/map', [SdiMapRequestController::class, 'deliverable'])->whereNumber('mapRequest')->name('map-requests.deliverable');

        Route::get('/production', [SdiProductionController::class, 'index'])->name('production');
        Route::post('/production/{mapRequest}/start', [SdiProductionController::class, 'start'])->whereNumber('mapRequest')->name('production.start');
        Route::post('/production/{mapRequest}/fulfil', [SdiProductionController::class, 'fulfil'])->whereNumber('mapRequest')->name('production.fulfil');
        Route::post('/production/{mapRequest}/reject', [SdiProductionController::class, 'reject'])->whereNumber('mapRequest')->name('production.reject');

        Route::get('/registry', [SdiRegistryController::class, 'index'])->name('registry');
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
    // Offline support (2026-10-25): open to the service worker without a session.
    Route::get('/sw.js', [MobileOfflineController::class, 'serviceWorker'])->name('sw');
    Route::get('/manifest.webmanifest', [MobileOfflineController::class, 'manifest'])->name('manifest');
    Route::get('/offline', [MobileOfflineController::class, 'offline'])->name('offline');

    Route::get('/login', [MobileCaseController::class, 'loginForm'])->name('login');
    Route::post('/login', [MobileCaseController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::post('/logout', [MobileCaseController::class, 'logout'])->name('logout');

    Route::middleware(['auth'])->group(function () {
        Route::get('/', [MobileCaseController::class, 'index'])->name('index');           // dashboard
        Route::get('/register', [MobileCaseController::class, 'create'])->name('register');
        Route::post('/cases', [MobileCaseController::class, 'store'])->name('cases.store');
        Route::get('/cases/{case}', [MobileCaseController::class, 'show'])->name('cases.show');
        Route::post('/cases/{case}/submit', [MobileCaseController::class, 'submit'])->name('cases.submit');

        // Offline sync: session check, geo-tagged photos, and field readings (Mobile App 2).
        Route::get('/session', [MobileOfflineController::class, 'session'])->name('session');
        Route::post('/cases/{case}/photos', [MobileOfflineController::class, 'storePhoto'])->name('cases.photos.store');
        Route::get('/cases/{case}/photos/{photo}', [MobileOfflineController::class, 'showPhoto'])->name('cases.photos.show');
        Route::get('/observations', [MobileObservationController::class, 'index'])->name('observations');
        // Computation & Field Observations app (2026-10-30): private reports too, beacons, Sketch Plan.
        Route::get('/observations/{its}', [MobileObservationController::class, 'show'])->where('its', '(survey|cadastral|private)-[0-9]+')->name('observations.show');
        Route::post('/observations/{its}', [MobileObservationController::class, 'store'])->where('its', '(survey|cadastral|private)-[0-9]+')->name('observations.store');
        Route::post('/observations/{its}/coordinates', [MobileObservationController::class, 'storeCoordinate'])->where('its', '(survey|cadastral|private)-[0-9]+')->name('observations.coords.store');
        Route::post('/observations/{its}/sketch-plan', [SurveySketchPlanController::class, 'store'])->where('its', '(survey|cadastral|private)-[0-9]+')->name('observations.sketch.store');

        // Plot Confirmation app (2026-10-30): show OP plots to allottees, ground-check plots.
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
