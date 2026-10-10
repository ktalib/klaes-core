<?php

use App\Http\Controllers\CadastralDept\CaseController;
use App\Http\Controllers\CadastralDept\ChartingController;
use App\Http\Controllers\CadastralDept\DeskController;
use App\Http\Controllers\CadastralDept\FieldJacketController;
use App\Http\Controllers\CadastralDept\FmcController;
use App\Http\Controllers\CadastralDept\PndBillController;
use App\Http\Controllers\CadastralDept\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Cadastral Department workflow
|--------------------------------------------------------------------------
| Built from the "Cadastral Department Workflow" doc (2026-10-09); routing
| lives in config/cadastral_dept.php. Sidebar (2026-10-10, user-approved): one
| entry per desk under Cadastral > Cadastral Department, gated by Cad - Records.
|
| Prefix "cadastral-dept", kept apart from the legacy cadastral.* screens.
| config/module_permissions.php maps it to Cad - Records.
|
| Route names drive the permission action inference: "store" reads as create;
| "mark", "save" and "update" read as edit. Read-only pages stay on GET.
|
| Auth is required: the pages render in layouts.app, whose admin.menu reads
| auth()->user()->assignedRoleNames().
*/

Route::middleware(['auth', 'XSS'])->prefix('cadastral-dept')->name('cadastral-dept.')->group(function () {

    Route::get('/', [WorkflowController::class, 'index'])->name('index');
    Route::get('/workflow-map', [WorkflowController::class, 'map'])->name('map');

    // Desk inboxes: files currently waiting at one desk.
    Route::get('/desks/{desk}', [DeskController::class, 'show'])->name('desks.show');

    // Cases: one per main file passing through the department.
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
    Route::get('/lookup/file', [CaseController::class, 'lookupFile'])->name('lookup.file');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->whereNumber('case')->name('cases.show');

    Route::post('/cases/{case}/tasks', [CaseController::class, 'completeTask'])->whereNumber('case')->name('cases.tasks.mark-done');
    Route::post('/cases/{case}/move', [CaseController::class, 'advance'])->whereNumber('case')->name('cases.move.save');
    Route::post('/cases/{case}/sub-type', [CaseController::class, 'setSubType'])->whereNumber('case')->name('cases.sub-type.update');

    // Create Correspondence File at AD Cad Report (matches an existing one first).
    Route::post('/cases/{case}/correspondence', [CaseController::class, 'createCorrespondence'])->whereNumber('case')->name('cases.correspondence.store');
    // Registry: detach / attach the correspondence file (history kept).
    Route::post('/cases/{case}/correspondence/custody', [CaseController::class, 'correspondenceCustody'])->whereNumber('case')->name('cases.correspondence.custody.save');

    // Charting card at each charting step, and the charting tables.
    Route::get('/cases/{case}/charting/create', [ChartingController::class, 'create'])->whereNumber('case')->name('cases.charting.create');
    Route::post('/cases/{case}/charting', [ChartingController::class, 'store'])->whereNumber('case')->name('cases.charting.store');
    Route::get('/charting', [ChartingController::class, 'index'])->name('charting.index');
    Route::get('/charting/{charting}', [ChartingController::class, 'show'])->whereNumber('charting')->name('charting.show');
    Route::get('/charting/{charting}/print', [ChartingController::class, 'print'])->whereNumber('charting')->name('charting.print');

    // Field Jacket at AD P&D: commissioning registers the survey job; the job's I to S is issued from it.
    Route::post('/cases/{case}/field-jacket', [FieldJacketController::class, 'store'])->whereNumber('case')->name('cases.field-jacket.store');
    Route::post('/cases/{case}/its', [FieldJacketController::class, 'issueIts'])->whereNumber('case')->name('cases.its.store');
    Route::get('/field-jackets', [FieldJacketController::class, 'index'])->name('field-jackets.index');
    Route::get('/field-jackets/{jacket}', [FieldJacketController::class, 'show'])->whereNumber('jacket')->name('field-jackets.show');
    Route::post('/field-jackets/{jacket}/reassign', [FieldJacketController::class, 'reassign'])->whereNumber('jacket')->name('field-jackets.reassign.update');
    Route::get('/field-jackets/{jacket}/print', [FieldJacketController::class, 'print'])->whereNumber('jacket')->name('field-jackets.print');
    Route::get('/field-jackets/{jacket}/its/print', [FieldJacketController::class, 'printIts'])->whereNumber('jacket')->name('field-jackets.its.print');

    // Fees P&D bill at AD P&D: area and beacons, description, fees; no payment.
    Route::get('/cases/{case}/bill', [PndBillController::class, 'show'])->whereNumber('case')->name('cases.bill.show');
    Route::post('/cases/{case}/bill/area', [PndBillController::class, 'saveArea'])->whereNumber('case')->name('cases.bill.area.save');
    Route::post('/cases/{case}/bill/description', [PndBillController::class, 'generateDescription'])->whereNumber('case')->name('cases.bill.description.store');
    Route::put('/cases/{case}/bill/description', [PndBillController::class, 'saveDescription'])->whereNumber('case')->name('cases.bill.description.save');
    Route::get('/cases/{case}/bill/preview', [PndBillController::class, 'preview'])->whereNumber('case')->name('cases.bill.preview');
    Route::post('/cases/{case}/bill', [PndBillController::class, 'store'])->whereNumber('case')->name('cases.bill.store');
    Route::get('/bills', [PndBillController::class, 'index'])->name('bills.index');
    Route::get('/bills/{bill}/print', [PndBillController::class, 'print'])->whereNumber('bill')->name('bills.print');

    // File Movement Card: commissioned at AD Cad Info; its QR code opens fmc.scan.
    Route::post('/cases/{case}/fmc', [FmcController::class, 'store'])->whereNumber('case')->name('cases.fmc.store');
    Route::get('/cases/{case}/fmc', [FmcController::class, 'show'])->whereNumber('case')->name('cases.fmc.show');
    Route::get('/cases/{case}/fmc/print', [FmcController::class, 'print'])->whereNumber('case')->name('cases.fmc.print');
    Route::get('/fmc/{token}', [FmcController::class, 'scan'])->name('fmc.scan');
    Route::post('/fmc/{token}/log', [FmcController::class, 'log'])->name('fmc.log.save');
});
