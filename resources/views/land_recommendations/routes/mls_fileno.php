<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MlsFileNoController;

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
    Route::post('/generate-batch', [MlsFileNoController::class, 'generateBatch'])->name('mls-fileno.generate-batch');
    Route::post('/initialize-serial', [MlsFileNoController::class, 'initializeSerial'])->name('mls-fileno.initialize-serial');
    Route::post('/batch-records', [MlsFileNoController::class, 'getBatchRecords'])->name('mls-fileno.batch-records');
    Route::post('/mark-batch-printed', [MlsFileNoController::class, 'markBatchPrinted'])->name('mls-fileno.mark-batch-printed');
    Route::get('/printable-batches', [MlsFileNoController::class, 'getPrintableBatches'])->name('mls-fileno.printable-batches');
    Route::get('/temp-file-details', [MlsFileNoController::class, 'getTempFileDetailsByPropId'])->name('mls-fileno.temp-file-details');

    // These MUST be last because they use route parameters
    Route::get('/{id}', [MlsFileNoController::class, 'show'])->name('mls-fileno.show');
    Route::put('/{id}', [MlsFileNoController::class, 'update'])->name('mls-fileno.update');
});