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
use App\Http\Controllers\Survey\ControlPointRegisterController;
use App\Http\Controllers\Survey\CoordinateRegisterController;
use App\Http\Controllers\Survey\ComplaintController;
use App\Http\Controllers\Survey\DashboardController;
use App\Http\Controllers\Survey\ExaminationController;
use App\Http\Controllers\Survey\GknApplicationController;
use App\Http\Controllers\Survey\GknController;
use App\Http\Controllers\Survey\LookupController;
use App\Http\Controllers\Survey\LpknController;
use App\Http\Controllers\Survey\MiscKnController;
use App\Http\Controllers\Survey\MobileCaseController;
use App\Http\Controllers\Survey\OldSurveyController;
use App\Http\Controllers\Survey\OpController;
use App\Http\Controllers\Survey\PlotAllocationController;
use App\Http\Controllers\Survey\ProjectController;
use App\Http\Controllers\Survey\RegisterFileController;
use App\Http\Controllers\Survey\ReportController;
use App\Http\Controllers\Survey\SurveyCoordinateController;
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

    /* ------------------------------ Compensation ------------------------------ */
    Route::prefix('compensation')->name('compensation.')->group(function () {

        Route::get('/dashboard', [DashboardController::class, 'compensation'])->name('dashboard');

        // CO files (Prj-YYYY-0001), one to many projects (user, 2026-10-25)
        Route::get('/co-files', [CoFileController::class, 'index'])->name('co-files');
        Route::get('/co-files/commission', [CoFileController::class, 'create'])->name('co-files.create');
        Route::post('/co-files', [CoFileController::class, 'store'])->name('co-files.store');
        Route::get('/co-files/{coFile}/edit', [CoFileController::class, 'edit'])->name('co-files.edit');
        Route::put('/co-files/{coFile}', [CoFileController::class, 'update'])->name('co-files.update');
        Route::delete('/co-files/{coFile}', [CoFileController::class, 'destroy'])->name('co-files.destroy');

        // Allocation Records: surveyor portions per project, read-only (2026-10-25)
        Route::get('/allocation-records', [AllocationRecordController::class, 'index'])->name('allocation-records');

        // Complaints (2026-10-25)
        Route::get('/complaints', [ComplaintController::class, 'index'])->name('complaints');
        Route::post('/complaints', [ComplaintController::class, 'store'])->name('complaints.store');
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
    Route::get('/login', [MobileCaseController::class, 'loginForm'])->name('login');
    Route::post('/login', [MobileCaseController::class, 'login'])->middleware('throttle:10,1')->name('login.submit');
    Route::post('/logout', [MobileCaseController::class, 'logout'])->name('logout');

    Route::middleware(['auth'])->group(function () {
        Route::get('/', [MobileCaseController::class, 'index'])->name('index');           // dashboard
        Route::get('/register', [MobileCaseController::class, 'create'])->name('register');
        Route::post('/cases', [MobileCaseController::class, 'store'])->name('cases.store');
        Route::get('/cases/{case}', [MobileCaseController::class, 'show'])->name('cases.show');
        Route::post('/cases/{case}/submit', [MobileCaseController::class, 'submit'])->name('cases.submit');
    });
});
