<?php

use App\Http\Controllers\Survey\BeneficiaryController;
use App\Http\Controllers\Survey\CalculatorController;
use App\Http\Controllers\Survey\CaseController;
use App\Http\Controllers\Survey\DashboardController;
use App\Http\Controllers\Survey\ExaminationController;
use App\Http\Controllers\Survey\GknController;
use App\Http\Controllers\Survey\LookupController;
use App\Http\Controllers\Survey\LpknController;
use App\Http\Controllers\Survey\MiscKnController;
use App\Http\Controllers\Survey\MobileCaseController;
use App\Http\Controllers\Survey\OpController;
use App\Http\Controllers\Survey\PlotAllocationController;
use App\Http\Controllers\Survey\ProjectController;
use App\Http\Controllers\Survey\ReportController;
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
        Route::get('/lands', [GknController::class, 'index'])->name('lands');
        Route::get('/register', [GknController::class, 'create'])->name('register');
        Route::post('/register', [GknController::class, 'store'])->name('register.store');
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

        Route::get('/occupancy', [OpController::class, 'workflow'])->name('occupancy');
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
