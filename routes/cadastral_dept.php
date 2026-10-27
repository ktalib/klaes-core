<?php

use App\Http\Controllers\CadastralDept\CaseController;
use App\Http\Controllers\CadastralDept\CentralAdminController;
use App\Http\Controllers\CadastralDept\ChartingController;
use App\Http\Controllers\CadastralDept\DeskController;
use App\Http\Controllers\CadastralDept\FieldJacketController;
use App\Http\Controllers\CadastralDept\FmcController;
use App\Http\Controllers\CadastralDept\PndBillController;
use App\Http\Controllers\CadastralDept\PropertyIndexCardController;
use App\Http\Controllers\CadastralDept\WorkflowController;
use App\Http\Controllers\CadastralDept\YesNoFormController;
use App\Http\Controllers\CadastralDept\VerificationReportController;
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
| Desk roles (2026-10-15): the cadastral.desk middleware (CadastralDeskAccess) lets a
| desk page and every write through only for that desk's role or Cad - Records
| (config cadastral_dept.desk_roles).
|
| Route names drive the permission action inference: "store" reads as create;
| "mark", "save" and "update" read as edit. Read-only pages stay on GET.
|
| Auth is required: the pages render in layouts.app, whose admin.menu reads
| auth()->user()->assignedRoleNames().
*/

Route::middleware(['auth', 'XSS', 'cadastral.desk'])->prefix('cadastral-dept')->name('cadastral-dept.')->group(function () {

    Route::get('/', [WorkflowController::class, 'index'])->name('index');
    Route::get('/workflow-map', [WorkflowController::class, 'map'])->name('map');

    // Central Admin (CADASTRAL UPDATE, 2026-10-26): every open file in one table; the Central Admin
    // role acts for any desk or unit head through the case routes below. Sidebar: Admin, after Dashboard.
    Route::get('/central-admin', [CentralAdminController::class, 'index'])->name('central-admin.index');

    // Generate Property Index Card (2026-10-16): one card per file from the Legal Search records,
    // filed as a PDF to EDMS/SCAN_UPLOAD/Cadastral_Registry/PIC/{file}/. Registering a file generates it too.
    Route::get('/pic', [PropertyIndexCardController::class, 'index'])->name('pic.index');
    Route::get('/pic/preview', [PropertyIndexCardController::class, 'preview'])->name('pic.preview');
    Route::get('/pic/card', [PropertyIndexCardController::class, 'show'])->name('pic.show');
    Route::post('/pic', [PropertyIndexCardController::class, 'store'])->name('pic.store');

    // Desk inboxes: files currently waiting at one desk.
    Route::get('/desks/{desk}', [DeskController::class, 'show'])->name('desks.show');

    // Cases: one per main file passing through the department.
    Route::get('/cases', [CaseController::class, 'index'])->name('cases.index');
    Route::get('/cases/create', [CaseController::class, 'create'])->name('cases.create');
    Route::get('/lookup/file', [CaseController::class, 'lookupFile'])->name('lookup.file');
    Route::get('/lookup/files', [CaseController::class, 'lookupFiles'])->name('lookup.files');
    // Register File, Verification: the File Status checks for the picked file, before it is registered.
    Route::get('/lookup/file-status', [CaseController::class, 'lookupFileStatus'])->name('lookup.file-status');
    Route::post('/cases', [CaseController::class, 'store'])->name('cases.store');
    Route::get('/cases/{case}', [CaseController::class, 'show'])->whereNumber('case')->name('cases.show');
    // Verification: which File Status lists the file is on. Loaded after the page, it can be slow.
    Route::get('/cases/{case}/file-status', [CaseController::class, 'fileStatus'])->whereNumber('case')->name('cases.file-status');

    Route::post('/cases/{case}/tasks', [CaseController::class, 'completeTask'])->whereNumber('case')->name('cases.tasks.mark-done');
    Route::post('/cases/{case}/move', [CaseController::class, 'advance'])->whereNumber('case')->name('cases.move.save');
    // Super Admin / Central Admin: move to any step from the step bar, past the checks, with a reason (2026-10-26).
    Route::post('/cases/{case}/jump', [CaseController::class, 'jump'])->whereNumber('case')->name('cases.jump.save');
    Route::post('/cases/{case}/sub-type', [CaseController::class, 'setSubType'])->whereNumber('case')->name('cases.sub-type.update');
    // Master Delete (Supper Admin, typed confirmation): clears a file and its workflow rows.
    Route::delete('/cases/{case}/master-destroy', [CaseController::class, 'masterDestroy'])->whereNumber('case')->name('cases.master-destroy');

    // Create Correspondence File at AD Cad Report (matches an existing one first).
    Route::post('/cases/{case}/correspondence', [CaseController::class, 'createCorrespondence'])->whereNumber('case')->name('cases.correspondence.store');
    // KAMMA / Physical Planning's Planning Advice, filed into EDMS when the file comes back.
    Route::post('/cases/{case}/planning-advice', [CaseController::class, 'uploadPlanningAdvice'])->whereNumber('case')->name('cases.planning-advice.store');
    // Change request 26-10-2026: SG's Final Survey Plan, AD Cad Info's signed Fees P&D (StepDocuments).
    Route::post('/cases/{case}/step-documents/{task}', [CaseController::class, 'uploadStepDocument'])->whereNumber('case')
        ->whereIn('task', array_keys(\App\Services\CadastralDept\StepDocuments::TASKS))->name('cases.step-documents.store');
    Route::post('/cases/{case}/bill/sign', [CaseController::class, 'signBill'])->whereNumber('case')->name('cases.bill.sign');
    // Registry, step 31: the Computation File comes off the correspondence file (FieldJackets::detach).
    Route::post('/cases/{case}/computation-file/detach', [CaseController::class, 'detachComputationFile'])->whereNumber('case')->name('cases.computation-file.detach');
    // Registry: detach / attach the correspondence file (history kept).
    Route::post('/cases/{case}/correspondence/custody', [CaseController::class, 'correspondenceCustody'])->whereNumber('case')->name('cases.correspondence.custody.save');
    Route::post('/cases/{case}/page-count', [CaseController::class, 'pageCount'])->whereNumber('case')->name('cases.page-count.save');
    Route::post('/cases/{case}/dms-upload', [CaseController::class, 'dmsUpload'])->whereNumber('case')->name('cases.dms-upload');

    // Yes/No form ("Report on Application") at AD Cad Report, done with Land 12.
    Route::get('/cases/{case}/yes-no', [YesNoFormController::class, 'show'])->whereNumber('case')->name('cases.yes-no.show');
    Route::post('/cases/{case}/yes-no', [YesNoFormController::class, 'save'])->whereNumber('case')->name('cases.yes-no.save');
    Route::get('/cases/{case}/yes-no/print', [YesNoFormController::class, 'print'])->whereNumber('case')->name('cases.yes-no.print');
    // Cadastral Verification Report Sheet (client DOCX, 2026-10-15), Verification files.
    Route::get('/cases/{case}/verification-report', [VerificationReportController::class, 'show'])->whereNumber('case')->name('cases.verification-report.show');
    Route::post('/cases/{case}/verification-report', [VerificationReportController::class, 'save'])->whereNumber('case')->name('cases.verification-report.save');
    Route::get('/cases/{case}/verification-report/print', [VerificationReportController::class, 'print'])->whereNumber('case')->name('cases.verification-report.print');

    // Charting card at each charting step, and the charting tables.
    Route::get('/cases/{case}/charting/create', [ChartingController::class, 'create'])->whereNumber('case')->name('cases.charting.create');
    Route::post('/cases/{case}/charting', [ChartingController::class, 'store'])->whereNumber('case')->name('cases.charting.store');
    Route::get('/charting', [ChartingController::class, 'index'])->name('charting.index');
    Route::get('/charting/{charting}', [ChartingController::class, 'show'])->whereNumber('charting')->name('charting.show');
    Route::get('/charting/{charting}/print', [ChartingController::class, 'print'])->whereNumber('charting')->name('charting.print');

    // Field Jacket at AD P&D: commissioning registers the survey job; the job's I to S is issued from it.
    Route::post('/cases/{case}/field-jacket', [FieldJacketController::class, 'store'])->whereNumber('case')->name('cases.field-jacket.store');
    Route::post('/cases/{case}/its', [FieldJacketController::class, 'issueIts'])->whereNumber('case')->name('cases.its.store');
    // AD Cad Info (transcript, 2026-10-16): attach the Job Book job and surveyor, then issue the job's instruction.
    Route::post('/cases/{case}/job', [FieldJacketController::class, 'attachJob'])->whereNumber('case')->name('cases.job.store');
    Route::post('/cases/{case}/job-its', [FieldJacketController::class, 'issueJobIts'])->whereNumber('case')->name('cases.job-its.store');
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
    Route::get('/cases/{case}/bill/description/print', [PndBillController::class, 'printDescription'])->whereNumber('case')->name('cases.bill.description.print');
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
