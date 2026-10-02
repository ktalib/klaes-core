<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileNumberController;

// File Number Generation Routes (MLS File Number Generator)
// NOTE: This is for creating NEW MLS file numbers and managing the generation process.
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'file-numbers'], function () {
    Route::get('/', [FileNumberController::class, 'index'])->name('file-numbers.index');
    Route::get('/data', [FileNumberController::class, 'getData'])->name('file-numbers.data');
    Route::get('/test-db', [FileNumberController::class, 'testDatabase'])->name('file-numbers.test-db');
    Route::get('/stats', [FileNumberController::class, 'getStats'])->name('file-numbers.stats');
    Route::get('/debug-data', function () {
        try {
            $data = DB::connection('sqlsrv')
                ->table('fileNumber')
                ->select(['id', 'kangisFileNo', 'NewKANGISFileNo', 'FileName', 'mlsfNo', 'created_by', 'created_at'])
                ->limit(5)
                ->get();

            return response()->json([
                'success' => true,
                'raw_data' => $data->toArray(),
                'formatted_data' => $data->map(function ($row) {
                    return [
                        'id' => $row->id,
                        'kangisFileNo' => trim($row->kangisFileNo ?? '') ?: '-',
                        'NewKANGISFileNo' => trim($row->NewKANGISFileNo ?? '') ?: '-',
                        'FileName' => trim($row->FileName ?? '') ?: '-',
                        'mlsfNo' => trim($row->mlsfNo ?? '') ?: '-',
                        'created_by' => trim($row->created_by ?? '') ?: 'System',
                        'created_at' => $row->created_at ?: '-'
                    ];
                })->toArray()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    })->name('file-numbers.debug-data');
    Route::get('/next-serial', [FileNumberController::class, 'getNextSerial'])->name('file-numbers.next-serial');
    Route::get('/existing', [FileNumberController::class, 'getExistingFileNumbers'])->name('file-numbers.existing');
    Route::post('/store', [FileNumberController::class, 'store'])->name('file-numbers.store');
    // One-off SMS to the applicant, from the phone card on the file list. POST, so
    // it never collides with the GET /{id} route below.
    Route::get('/contact-sms-template', [FileNumberController::class, 'contactSmsTemplate'])->name('file-numbers.contact-sms-template');
    Route::post('/send-sms', [FileNumberController::class, 'sendSms'])->name('file-numbers.send-sms');
    Route::post('/migrate', [FileNumberController::class, 'migrate'])->name('file-numbers.migrate');
    // Must precede the {id} route below, or "acquisition-method" is read as an id.
    Route::get('/conversion-application/{id}/acquisition-method', [FileNumberController::class, 'getConversionAcquisitionMethod'])->name('file-numbers.conversion-acquisition-method');
    Route::get('/conversion-application/{id}', [FileNumberController::class, 'generateConversionApplication'])->name('file-numbers.conversion-application');
    Route::get('/batch-conversion-application/{batchNo}', [FileNumberController::class, 'generateBatchConversionApplication'])->name('file-numbers.batch-conversion-application');
    Route::get('/date-conversion-application', [FileNumberController::class, 'generateDateConversionApplication'])->name('file-numbers.date-conversion-application');
    Route::get('/get-print-status', [FileNumberController::class, 'getPrintStatus'])->name('file-numbers.get-print-status');
    Route::post('/record-print', [FileNumberController::class, 'recordPrint'])->name('file-numbers.record-print');
    Route::get('/consolidation-report', [FileNumberController::class, 'getConsolidationReport'])->name('file-numbers.consolidation-report');
    Route::get('/{id}', [FileNumberController::class, 'show'])->name('file-numbers.show');
    Route::put('/{id}', [FileNumberController::class, 'update'])->name('file-numbers.update');
    Route::post('/bulk-destroy', [FileNumberController::class, 'bulkDestroy'])->name('file-numbers.bulk-destroy');
    Route::delete('/{id}', [FileNumberController::class, 'destroy'])->name('file-numbers.destroy');
    Route::get('/count/total', [FileNumberController::class, 'getCount'])->name('file-numbers.count');
    Route::post('/clear-cache', [FileNumberController::class, 'clearCache'])->name('file-numbers.clear-cache');

});

// ST File Numbers Management Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'st-file-numbers'], function () {
    Route::get('/', function () {
        return view('file_numbers.st_index');
    })->name('st-file-numbers.index');

    // Master delete — removes the ST file number from every table it was written to.
    // Super Admin only, and the request must echo back the exact file number.
    Route::delete('/master-destroy', [\App\Http\Controllers\CommissionNewSTController::class, 'masterDestroy'])
        ->name('st-file-numbers.master-destroy');
});

// Capture Existing File Numbers Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'existing-file-numbers'], function () {
    Route::get('/', [FileNumberController::class, 'captureIndex'])->name('existing-file-numbers.index');
    Route::get('/data', [FileNumberController::class, 'getCaptureData'])->name('existing-file-numbers.data');
    Route::post('/store', [FileNumberController::class, 'captureStore'])->name('existing-file-numbers.store');
});
