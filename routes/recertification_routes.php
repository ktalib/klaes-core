<?php

use App\Http\Controllers\RecertificationController;
use App\Http\Controllers\CertificationController;
use App\Http\Controllers\EdmsController;
use Illuminate\Support\Facades\Route;

// Recertification Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'recertification'], function () {
    // Main Index and Application Routes
    Route::get('/', [RecertificationController::class, 'index'])->name('recertification.index');
    Route::get('/data', [RecertificationController::class, 'getApplicationsData'])->name('recertification.data');
    Route::get('/statistics', [RecertificationController::class, 'getStatistics'])->name('recertification.statistics');
    Route::get('/application', function() {
        return view('recertification.application_standalone_clean');
    })->name('recertification.application');
    Route::post('/application/store', [RecertificationController::class, 'store'])->name('recertification.application.store');
    
    // Migration Routes
    Route::get('/migrate', [RecertificationController::class, 'migrate'])->name('recertification.migrate');
    Route::post('/migrate/upload', [RecertificationController::class, 'uploadMigration'])->name('recertification.migrate.upload');
    Route::get('/migrate/template', [RecertificationController::class, 'downloadTemplate'])->name('recertification.migrate.template');
    
    // Verification Routes
    Route::get('/verification-sheet', [RecertificationController::class, 'verificationSheet'])->name('recertification.verification-sheet');
    Route::get('/verification-data', [RecertificationController::class, 'getVerificationData'])->name('recertification.verification-data');
    Route::post('/{id}/verify', [RecertificationController::class, 'verify'])->name('recertification.verify');
    
    // Utility Routes
    Route::get('/next-file-number', [RecertificationController::class, 'getNextFileNumber'])->name('recertification.nextFileNumber');
    Route::get('/next-new-kangis-file-number', [RecertificationController::class, 'getNextNewKangisFileNumber'])->name('recertification.nextNewKangisFileNumber');
    
    // Individual Record Routes
    Route::get('/{id}/view', [RecertificationController::class, 'view'])->name('recertification.view');
    Route::get('/{id}/details', [RecertificationController::class, 'details'])->name('recertification.details');
    Route::get('/{id}/edit', [RecertificationController::class, 'edit'])->name('recertification.edit');
    Route::put('/{id}', [RecertificationController::class, 'update'])->name('recertification.update');
    Route::delete('/{id}', [RecertificationController::class, 'destroy'])->name('recertification.destroy');

    // Acknowledgement Routes
    Route::post('/{id}/acknowledgement/generate', [RecertificationController::class, 'generateAcknowledgement'])->name('recertification.acknowledgement.generate');
    Route::get('/{id}/acknowledgement', [RecertificationController::class, 'viewAcknowledgement'])->name('recertification.acknowledgement.view');
    Route::post('/{id}/acknowledgement/submit', [RecertificationController::class, 'submitAcknowledgementDocs'])->name('recertification.acknowledgement.submit');
    Route::get('/{id}/verification', [RecertificationController::class, 'verificationView'])->name('recertification.verification');
    
    // Certification Management Routes
    Route::get('/certification', [CertificationController::class, 'index'])->name('recertification.certification');
    Route::get('/certification-data', [CertificationController::class, 'getCertificationData'])->name('recertification.certification-data');
    Route::get('/{id}/cor', [CertificationController::class, 'viewCoR'])->name('recertification.cor');
    Route::post('/{id}/generate-cofo-front', [CertificationController::class, 'generateCofoFrontPage'])->name('recertification.generate-cofo-front');
    Route::put('/{id}/certificate-details', [CertificationController::class, 'updateCertificateDetails'])->name('recertification.update-certificate-details');
    Route::get('/{id}/cofo-front-page', [CertificationController::class, 'viewCofoFrontPage'])->name('recertification.cofo-front-page');
    Route::get('/{id}/tdp', [CertificationController::class, 'viewTDP'])->name('recertification.tdp');
    Route::get('/{id}/cofo', [CertificationController::class, 'viewCofo'])->name('recertification.cofo');
    
    // Vetting Sheet Routes
    Route::get('/vetting-sheet', [CertificationController::class, 'vettingSheet'])->name('recertification.vetting-sheet');
    Route::get('/vetting-data', [CertificationController::class, 'getVettingData'])->name('recertification.vetting-data');
    Route::get('/vetting-sheet/browse', [CertificationController::class, 'browseVettingSheetDirectory'])->name('recertification.vetting-sheet.browse');
    
    // DG's List Routes
    Route::get('/dg-list', [CertificationController::class, 'dgList'])->name('recertification.dg-list');
    Route::get('/dg-data', [CertificationController::class, 'getDGData'])->name('recertification.dg-data');
    Route::post('/batch-process', [CertificationController::class, 'batchProcess'])->name('recertification.batch-process');
    
    // Governors List Routes
    Route::get('/governors-list', [CertificationController::class, 'governorsList'])->name('recertification.governors-list');
    Route::get('/governors-data', [CertificationController::class, 'getGovernorsData'])->name('recertification.governors-data');
    Route::post('/batch-process-governor', [CertificationController::class, 'batchProcessGovernor'])->name('recertification.batch-process-governor');
    
    // EDMS Routes
    Route::get('/edms', [CertificationController::class, 'edms'])->name('recertification.edms');
    Route::get('/edms-data', [CertificationController::class, 'getEDMSData'])->name('recertification.edms-data');
    
    // GIS Data Capture Routes
    Route::get('/gis-data-capture', [CertificationController::class, 'gisDataCapture'])->name('recertification.gis-data-capture');
    Route::get('/gis-data', [CertificationController::class, 'getGISData'])->name('recertification.gis-data');
    Route::get('/{id}/gis-capture', [RecertificationController::class, 'gisCapture'])->name('recertification.gis-capture');
    Route::post('/{id}/gis-capture', [RecertificationController::class, 'storeGisCapture'])->name('recertification.gis-capture.store');

    // CofO details storage (using PrimaryActionsController)
    Route::post('/cofo/store-deeds', [\App\Http\Controllers\PrimaryActionsController::class, 'storeDeeds'])->name('recertification.cofo.store-deeds');
    
    // Serial Number Routes
    Route::get('/available-serial-numbers', [RecertificationController::class, 'getAvailableSerialNumbers'])->name('recertification.available-serial-numbers');
    Route::post('/assign-serial-number', [RecertificationController::class, 'assignSerialNumber'])->name('recertification.assign-serial-number');
    Route::get('/application-data/{id}', [RecertificationController::class, 'getApplicationData'])->name('recertification.application-data');
});

// EDMS Routes for Recertification (outside the main group to avoid prefix conflicts)
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'edms'], function () {
    Route::get('/{applicationId}/recertification', [EdmsController::class, 'recertificationIndex'])->name('edms.recertification.index');
    Route::get('/{applicationId}/recertification/create-file-indexing', [EdmsController::class, 'createRecertificationFileIndexing'])->name('edms.recertification.create-file-indexing');
    Route::post('/{applicationId}/recertification/create-file-indexing', [EdmsController::class, 'createRecertificationFileIndexing']);
});

// Vetting Sheet Directory Browser Route (outside groups to avoid prefix conflicts)
Route::get('/vetting-sheet/browse', [CertificationController::class, 'browseVettingSheetDirectory'])
    ->middleware(['auth', 'XSS'])
    ->name('vetting-sheet.browse');
// Bills & Payments Routes (added separately)
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'recertification'], function () {
    Route::get('/bills-payments', [CertificationController::class, 'billsPayments'])->name('recertification.bills-payments');
    Route::get('/bills-payments-data', [CertificationController::class, 'getBillsPaymentsData'])->name('recertification.bills-payments-data');
    Route::get('/export-payments', [CertificationController::class, 'exportPayments'])->name('recertification.export-payments');
});
