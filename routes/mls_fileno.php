<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MlsFileNoController;
use App\Http\Controllers\MlsPlotExtensionController;

// The Commission New File Number modal's own account of itself — modal opened,
// application/allocation type, prefix and land use, serial and preview changes,
// the Generate and its outcome, script errors, unloads. Outside the auth group on
// purpose: a session that dropped while the modal was open is one of the things
// this trace exists to prove, and an authenticated-only endpoint would bounce
// exactly that beacon. See MlsFileNumberDiagnosticsController for what it will and
// will not accept.
Route::post('mls-fileno/client-log', [\App\Http\Controllers\MlsFileNumberDiagnosticsController::class, 'clientLog'])
    ->name('mls-fileno.client-log');

// File Number Management Routes (Global Management)
// NOTE: This /mls-fileno route provides a global view of ALL file numbers (MLS, ST, KANGIS, etc.)
// from the main 'fileNumber' table. This is NOT the generator tool.
Route::prefix('/mls-fileno')->middleware(['auth'])->group(function () {
    Route::get('/', [MlsFileNoController::class, 'index'])->name('mls-fileno.index');
    Route::get('/data', [MlsFileNoController::class, 'getData'])->name('mls-fileno.data');
    Route::get('/datatable', [MlsFileNoController::class, 'getData'])->name('mls-fileno.datatable');
    Route::get('/stats', [MlsFileNoController::class, 'getStats'])->name('mls-fileno.stats');
    Route::get('/sources', [MlsFileNoController::class, 'getSources'])->name('mls-fileno.sources');
    Route::get('/debug', [MlsFileNoController::class, 'debug'])->name('mls-fileno.debug');
    Route::get('/test-st', [MlsFileNoController::class, 'testST'])->name('mls-fileno.test-st');

    Route::get('/get-dependent-data', [MlsFileNoController::class, 'getDependentData'])->name('mls-fileno.get-dependent-data');

    // New routes for land-use-based serial numbering (MUST come before /{id})
    Route::get('/serial-status', [MlsFileNoController::class, 'getSerialStatus'])->name('mls-fileno.serial-status');
    Route::post('/generate', [MlsFileNoController::class, 'generateMlsFileNumber'])->name('mls-fileno.generate');
    Route::post('/plot-extension', [MlsPlotExtensionController::class, 'store'])->name('mls-fileno.plot-extension.store');
    Route::post('/generate-batch', [MlsFileNoController::class, 'generateBatch'])->name('mls-fileno.generate-batch');
    // Live "X of Y files" for a batch / duplex commissioning the modal is waiting on.
    Route::get('/commissioning-progress/{token}', [MlsFileNoController::class, 'commissioningProgress'])
        ->where('token', '[A-Za-z0-9_-]{8,64}')
        ->name('mls-fileno.commissioning-progress');
    Route::get('/preview-tracking-id', [MlsFileNoController::class, 'previewTrackingId'])->name('mls-fileno.preview-tracking-id');
    // Serial initialization moved to System Admin → Configurable Entries →
    // Serial Initialization (ConfigurableEntriesController::initializeSerial).
    Route::post('/batch-records', [MlsFileNoController::class, 'getBatchRecords'])->name('mls-fileno.batch-records');
    Route::post('/mark-batch-printed', [MlsFileNoController::class, 'markBatchPrinted'])->name('mls-fileno.mark-batch-printed');
    Route::get('/printable-batches', [MlsFileNoController::class, 'getPrintableBatches'])->name('mls-fileno.printable-batches');
    Route::get('/temp-file-details', [MlsFileNoController::class, 'getTempFileDetailsByPropId'])->name('mls-fileno.temp-file-details');
    // Serials the counter has already passed that nothing holds any more — offered only
    // when the officer asks, and only for the land use they have selected.
    Route::get('/reclaimable-serials', [MlsFileNoController::class, 'getReclaimableSerials'])->name('mls-fileno.reclaimable-serials');
    // Did commissioning write every table it should? (action-menu card + post-commission check)
    Route::post('/commissioning-checklist', [\App\Http\Controllers\CommissioningChecklistController::class, 'check'])->name('mls-fileno.commissioning-checklist');
    // Action menu: put a commissioned file on Applications (No Change of Ownership).
    Route::post('/send-to-oss-applications', [\App\Http\Controllers\OssApplicationSendController::class, 'fromMlpp'])->name('mls-fileno.send-to-oss-applications');

    // These MUST be last because they use route parameters
    Route::get('/{id}', [MlsFileNoController::class, 'show'])->name('mls-fileno.show');
    Route::put('/{id}', [MlsFileNoController::class, 'update'])->name('mls-fileno.update');
});