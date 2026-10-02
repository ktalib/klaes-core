<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GisDataController;
use App\Http\Controllers\AttributionController;
use App\Http\Controllers\SurveyCadastralAttributionController;
use App\Http\Controllers\SurveyAttributionController;
use App\Http\Controllers\GisController;
use App\Http\Controllers\PlanningRecommendationController;
use App\Http\Controllers\SubPlanningRecommendationController;
use App\Http\Controllers\FileIndexController;
use App\Http\Controllers\PageTypingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyRecordController;
use App\Http\Controllers\ScanningController;
use App\Http\Controllers\FilearchiveController;
use App\Http\Controllers\FileTrackerController;
use App\Http\Controllers\SltroverviewController;
use App\Http\Controllers\SltrApplicationController;
use App\Http\Controllers\PrintLabelController;
use App\Http\Controllers\InstrumentRegistrationController;
use App\Http\Controllers\RDSController;
use App\Http\Controllers\SLTRApprovalController;
use App\Http\Controllers\SLTRDeedsRegController;
use App\Http\Controllers\InstrumentController;
use App\Http\Controllers\ApplicationFormController;
use App\Http\Controllers\SltrFieldDataController;
use App\Http\Controllers\SurveyPlanExtractionController;
use App\Http\Controllers\RecertificationController;

// Public routes
Route::get('/custom-public', function () {
    return 'This is a public custom route';
});

// Authenticated routes
Route::middleware(['auth'])->group(function () {
    // GIS related routes
    Route::prefix('gis')->name('gis.')->group(function () {
        Route::get('/', [GisDataController::class, 'index'])->name('index');
        Route::get('/create', [GisDataController::class, 'create'])->name('create');
        Route::post('/store', [GisDataController::class, 'store'])->name('store');

        // Add this new route for fetching unit file numbers
        Route::get('/get-units', [GisDataController::class, 'getUnits'])->name('get-units');

        // Important: This specific route must come BEFORE the wildcard {id} route
        Route::get('/search-files', [GisDataController::class, 'searchFiles'])->name('search-files');
        // Wildcard routes should come after more specific routes
        Route::get('/{id}', [GisDataController::class, 'show'])->name('view');
        Route::get('/{id}/edit', [GisDataController::class, 'edit'])->name('edit');
        Route::put('/{id}', [GisDataController::class, 'update'])->name('update');
        Route::delete('/{id}', [GisDataController::class, 'destroy'])->name('destroy');
    });


    Route::prefix('gis_record')->name('gis_record.')->group(function () {
        Route::get('/index', [GisController::class, 'index'])->name('index');
        Route::get('/create', [GisController::class, 'create'])->name('create');
        Route::post('/store', [GisController::class, 'store'])->name('store');

        // Add this new route for fetching unit file numbers
        Route::get('/get-units', [GisController::class, 'getUnits'])->name('get-units');

        // Important: This specific route must come BEFORE the wildcard {id} route
        Route::get('/search-files', [GisController::class, 'searchFiles'])->name('search-files');
        // Wildcard routes should come after more specific routes
        Route::get('/{id}', [GisController::class, 'show'])->name('view');
        Route::get('/{id}/edit', [GisController::class, 'edit'])->name('edit');
        Route::put('/{id}', [GisController::class, 'update'])->name('update');
        Route::delete('/{id}', [GisController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('attribution')->group(function () {
        // Routes for AttributionController
        Route::get('/', [AttributionController::class, 'Attributions'])->name('attribution.index');
        Route::get('/create', [AttributionController::class, 'create'])->name('attribution.create');
        Route::post('/store', [AttributionController::class, 'store'])->name('attribution.store');
        Route::get('/update-survey/{id}', [AttributionController::class, 'editSurvey'])->name('attribution.update-survey');
        Route::post('/update-survey', [AttributionController::class, 'updateSurvey'])->name('attribution.update-survey');
        Route::post('/search-fileno', [AttributionController::class, 'searchFileNo'])->name('attribution.search-fileno');

        // New routes for Primary Survey selection
        Route::post('/fetch-primary-surveys', [AttributionController::class, 'fetchPrimarySurveys'])->name('attribution.fetch-primary-surveys');
        Route::get('/primary-survey-details/{id}', [AttributionController::class, 'getPrimarySurveyDetails'])->name('attribution.primary-survey-details');
    });

    Route::prefix('survey_cadastral')->group(function () {
        // Routes for SurveyCadastralAttributionController
        Route::get('/', [SurveyCadastralAttributionController::class, 'Attributions'])->name('survey_cadastral.index');
        Route::get('/create', [SurveyCadastralAttributionController::class, 'create'])->name('survey_cadastral.create');
        Route::post('/store', [SurveyCadastralAttributionController::class, 'store'])->name('survey_cadastral.store');
        Route::get('/update-survey/{id}', [SurveyCadastralAttributionController::class, 'editSurvey'])->name('survey_cadastral.update-survey');
        Route::post('/update-survey', [SurveyCadastralAttributionController::class, 'updateSurvey'])->name('survey_cadastral.update-survey');
        Route::post('/search-fileno', [SurveyCadastralAttributionController::class, 'searchFileNo'])->name('survey_cadastral.search-fileno');

        // New routes for Primary Survey selection
        Route::post('/fetch-primary-surveys', [SurveyCadastralAttributionController::class, 'fetchPrimarySurveys'])->name('survey_cadastral.fetch-primary-surveys');
        Route::get('/primary-survey-details/{id}', [SurveyCadastralAttributionController::class, 'getPrimarySurveyDetails'])->name('survey_cadastral.primary-survey-details');

        // Additional action routes
        Route::get('/edit/{id}', [SurveyCadastralAttributionController::class, 'edit'])->name('survey_cadastral.edit');
        Route::get('/view-plan/{id}', [SurveyCadastralAttributionController::class, 'viewPlan'])->name('survey_cadastral.view-plan');
        Route::delete('/delete/{id}', [SurveyCadastralAttributionController::class, 'destroy'])->name('survey_cadastral.delete');
    });

    Route::prefix('survey_record')->group(function () {
        // Routes for SurveyAttributionController
        Route::get('/', [SurveyAttributionController::class, 'Attributions'])->name('survey_record.index');
        Route::get('/create', [SurveyAttributionController::class, 'create'])->name('survey_record.create');
        Route::post('/store', [SurveyAttributionController::class, 'store'])->name('survey_record.store');
        Route::get('/update-survey/{id}', [SurveyAttributionController::class, 'editSurvey'])->name('survey_record.update-survey');
        Route::post('/update-survey', [SurveyAttributionController::class, 'updateSurvey'])->name('survey_record.update-survey');
        Route::post('/search-fileno', [SurveyAttributionController::class, 'searchFileNo'])->name('survey_record.search-fileno');

        // New routes for Primary Survey selection
        Route::post('/fetch-primary-surveys', [SurveyAttributionController::class, 'fetchPrimarySurveys'])->name('survey_record.fetch-primary-surveys');
        Route::get('/primary-survey-details/{id}', [SurveyAttributionController::class, 'getPrimarySurveyDetails'])->name('survey_record.primary-survey-details');

        // Additional action routes
        Route::get('/edit/{id}', [SurveyAttributionController::class, 'edit'])->name('survey_record.edit');
        Route::get('/view-plan/{id}', [SurveyAttributionController::class, 'viewPlan'])->name('survey_record.view-plan');
        Route::delete('/delete/{id}', [SurveyAttributionController::class, 'destroy'])->name('survey_record.delete');
    });


    Route::prefix('pr_memos')->group(function () {
        // Routes for PlanningRecommendation memo
        Route::get('/approval', [PlanningRecommendationController::class, 'ApprovalMome'])->name('pr_memos.approval');
        Route::post('/store', [PlanningRecommendationController::class, 'ApprovalMomeSave'])->name('pr_memos.store');
        Route::post('/save-observations', [PlanningRecommendationController::class, 'saveAdditionalObservations'])->name('pr_memos.save-observations');

        Route::get('/declination', [PlanningRecommendationController::class, 'Declination'])->name('pr_memos.declination');
        Route::post('/declination/store', [PlanningRecommendationController::class, 'DeclinationSave'])->name('pr_memos.declination.store');

        // Print route for planning recommendation
        Route::get('/print/{id}', [PlanningRecommendationController::class, 'printRecommendation'])->name('pr_memos.print');
    });


    Route::prefix('sub_pr_memos')->group(function () {
        // Routes for SubPlanningRecommendation memo
        Route::get('/approval', [SubPlanningRecommendationController::class, 'ApprovalMome'])->name('sub_pr_memos.approval');
        Route::post('/store', [SubPlanningRecommendationController::class, 'ApprovalMomeSave'])->name('sub_pr_memos.store');
        Route::post('/save-observations', [SubPlanningRecommendationController::class, 'saveAdditionalObservations'])->name('sub_pr_memos.save-observations');

        Route::get('/declination', [SubPlanningRecommendationController::class, 'Declination'])->name('sub_pr_memos.declination');
        Route::post('/declination/store', [SubPlanningRecommendationController::class, 'DeclinationSave'])->name('sub_pr_memos.declination.store');

        // Print route for planning recommendation
        Route::get('/print/{id}', [SubPlanningRecommendationController::class, 'printRecommendation'])->name('sub_pr_memos.print');
    });

    Route::prefix('fileindexing')->group(function () {
        Route::get('/', [FileIndexController::class, 'index'])->name('fileindexing.index');
        Route::get('/create', \App\Http\Controllers\FileIndexCreatePageController::class)->name('fileindexing.create');

        // CSV Import routes - MOVED TO web.php to avoid route conflicts

        // AJAX routes - specific routes before wildcards
        Route::get('/search/applications', [FileIndexController::class, 'searchApplications'])->name('fileindexing.search-applications');
        Route::get('/list/file-indexings', [FileIndexController::class, 'getFileIndexingList'])->name('fileindexing.list');
        Route::get('/check/fileno', [FileIndexController::class, 'checkFileStatus'])->name('fileindexing.check-fileno');
        Route::get('/api/indexed-files/datatable', [FileIndexController::class, 'getIndexedFilesDataTable'])->name('fileindexing.api.indexed-files.datatable');
        Route::get('/api/indexed-files/quick', [FileIndexController::class, 'getIndexedFilesQuick'])->name('fileindexing.api.indexed-files.quick');

        // Wildcard routes - REMOVED - Already defined in web.php
    });

    Route::prefix('pagetyping')->group(function () {
        Route::get('/', [PageTypingController::class, 'index'])->name('pagetyping.index');
        Route::get('/create', [PageTypingController::class, 'create'])->name('pagetyping.create');
        Route::post('/store', [PageTypingController::class, 'store'])->name('pagetyping.store');

        // IMPORTANT: Specific routes MUST come before parameterized routes
        // AJAX routes
        Route::post('/save-single', [PageTypingController::class, 'saveSingle'])->name('pagetyping.save-single');
        Route::get('/list/page-typings', [PageTypingController::class, 'getPageTypings'])->name('pagetyping.list');
        Route::get('/api/stats', [PageTypingController::class, 'getStats'])->name('pagetyping.api.stats');
        Route::get('/api/files', [PageTypingController::class, 'getFilesByStatus'])->name('pagetyping.api.files');
        Route::get('/api/file-details', [PageTypingController::class, 'getFileDetails'])->name('pagetyping.api.file-details');
        Route::get('/api/next-serial-for-page-type', [PageTypingController::class, 'getNextSerialForPageType'])->name('pagetyping.api.next-serial-for-page-type');
        Route::post('/api/save-thumbnail', [PageTypingController::class, 'saveThumbnail'])->name('pagetyping.api.save-thumbnail');
        Route::get('/api/thumbnails', [PageTypingController::class, 'getThumbnails'])->name('pagetyping.api.thumbnails');

        // PageType More routes
        Route::get('/api/pagetype-more', [PageTypingController::class, 'pageTypeMore'])->name('pagetyping.api.pagetype-more');
        Route::post('/pagetype-more/store', [PageTypingController::class, 'storePageTypeMore'])->name('pagetyping.pagetype-more.store');
        Route::get('/api/pagetype-more-files', [PageTypingController::class, 'getPageTypeMoreFiles'])->name('pagetyping.api.pagetype-more-files');
        Route::get('/api/typing-data', [PageTypingController::class, 'getTypingData'])->name('pagetyping.api.typing-data');
        Route::post('/api/delete-page', [PageTypingController::class, 'deletePage'])->name('pagetyping.api.delete-page');
        Route::post('/api/replace-page', [PageTypingController::class, 'replacePage'])->name('pagetyping.api.replace-page');

        // Thumbnail routes for PDF splitting
        Route::get('/api/thumbnails', [PageTypingController::class, 'getThumbnails'])->name('pagetyping.api.thumbnails');
        Route::post('/api/save-thumbnail', [PageTypingController::class, 'saveThumbnail'])->name('pagetyping.api.save-thumbnail');

        // Tool interaction logging
        Route::post('/{file_indexing}/tools/log', [PageTypingController::class, 'logToolAction'])
            ->whereNumber('file_indexing')
            ->name('pagetyping.tools.log');

        Route::post('/{file_indexing}/folder/order', [PageTypingController::class, 'updateFolderOrder'])
            ->whereNumber('file_indexing')
            ->name('pagetyping.folder.order');

        // Parameterized routes MUST come last
        Route::get('/{id}', [PageTypingController::class, 'show'])->name('pagetyping.show');
        Route::get('/{id}/edit', [PageTypingController::class, 'edit'])->name('pagetyping.edit');
        Route::put('/{id}', [PageTypingController::class, 'update'])->name('pagetyping.update');
        Route::delete('/{id}', [PageTypingController::class, 'destroy'])->name('pagetyping.destroy');
    });

    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'index'])->name('profile.index');
        Route::get('/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/update', [ProfileController::class, 'update'])->name('profile.update');
    });

    // Property Record Routes - Keep only these relevant routes
    Route::prefix('propertycard')->group(function () {
        Route::get('/', [PropertyRecordController::class, 'index'])->name('propertycard.index');
        Route::get('/get-data', [PropertyRecordController::class, 'getData'])->name('propertycard.getData');
    });

    Route::prefix('property-records')->name('property-records.')->group(function () {
        Route::get('/{id}', [PropertyRecordController::class, 'show'])->name('show');
        Route::post('/', [PropertyRecordController::class, 'store'])->name('store');
        Route::post('/store-from-indexing', [PropertyRecordController::class, 'storeFromIndexing'])->name('storeFromIndexing');
        Route::put('/{id}', [PropertyRecordController::class, 'update'])->name('update');
        Route::delete('/{id}', [PropertyRecordController::class, 'destroy'])->name('destroy');
    });

    // API route for checking existing property records
    Route::get('/api/property-records/check/{fileNumber}', [PropertyRecordController::class, 'checkExistingRecords']);

    Route::prefix('scanning')->group(function () {
        Route::get('/', [ScanningController::class, 'index'])->name('scanning.index');
        Route::post('/upload', [ScanningController::class, 'upload'])->name('scanning.upload');
        Route::get('/view/{id}', [ScanningController::class, 'view'])->name('scanning.view');
        Route::get('/details/{id}', [ScanningController::class, 'details'])->name('scanning.details');
        Route::delete('/delete/{id}', [ScanningController::class, 'delete'])->name('scanning.delete');
        Route::put('/update/{id}', [ScanningController::class, 'updateDetails'])->name('scanning.update');
        Route::put('/update-details/{id}', [ScanningController::class, 'updateDetails'])->name('scanning.update-details');

        // AJAX routes
        Route::get('/list/scanned-files', [ScanningController::class, 'getScannedFiles'])->name('scanning.list');
    });

    Route::prefix('filearchive')->group(function () {
        Route::get('/', [FilearchiveController::class, 'index'])->name('filearchive.index');
        Route::get('/file-details/{id}', [FilearchiveController::class, 'getFileDetails'])->name('filearchive.file-details');
        Route::get('/document-pages/{id}', [FilearchiveController::class, 'getDocumentPages'])->name('filearchive.document-pages');
        Route::get('/search', [FilearchiveController::class, 'search'])->name('filearchive.search');
        Route::post('/upload', [FilearchiveController::class, 'upload'])->name('filearchive.upload');
        Route::get('/view/{id}', [FilearchiveController::class, 'view'])->name('filearchive.view');
        Route::delete('/delete/{id}', [FilearchiveController::class, 'delete'])->name('filearchive.delete');
    });

    Route::prefix('filetracker')->group(function () {
        Route::get('/', [FileTrackerController::class, 'index'])->name('filetracker.index');
        Route::get('/create', [FileTrackerController::class, 'create'])->name('filetracker.create');
        Route::post('/store', [FileTrackerController::class, 'store'])->name('filetracker.store');
        Route::put('/update/{id}', [FileTrackerController::class, 'update'])->name('filetracker.update');
        Route::post('/store-batch', [FileTrackerController::class, 'storeBatch'])->name('filetracker.store-batch');
        Route::get('/print', [FileTrackerController::class, 'print'])->name('filetracker.print');
        Route::post('/upload', [FileTrackerController::class, 'upload'])->name('filetracker.upload');
        Route::get('/view/{id}', [FileTrackerController::class, 'view'])->name('filetracker.view');
        Route::delete('/delete/{id}', [FileTrackerController::class, 'delete'])->name('filetracker.delete');

        // AJAX routes for file search and indexed files
        Route::get('/search-files', [FileTrackerController::class, 'searchFiles'])->name('filetracker.search-files');
        Route::get('/get-indexed-files', [FileTrackerController::class, 'getIndexedFiles'])->name('filetracker.get-indexed-files');
        Route::get('/get-next-batch-number', [FileTrackerController::class, 'getNextBatchNumber'])->name('filetracker.get-next-batch-number');

        // Status update route
        Route::post('/update-status/{id}', [FileTrackerController::class, 'updateStatus'])->name('filetracker.update-status');

        // Get file info by tracking ID
        Route::get('/get-file-info/{trackingId}', [FileTrackerController::class, 'getFileInfoByTrackingId'])->name('filetracker.get-file-info');

        // Get offices for Registry dropdown
        Route::get('/get-offices', [FileTrackerController::class, 'getOffices'])->name('filetracker.get-offices');
    });

    Route::prefix('sltroverview')->group(function () {
        Route::get('/', [SltroverviewController::class, 'index'])->name('sltroverview.index');

    });

    Route::prefix('sltrapplication')->group(function () {
        Route::get('/', [SltrApplicationController::class, 'index'])->name('sltrapplication.index');
    });

    Route::prefix('printlabel')->group(function () {
        Route::get('/', [PrintLabelController::class, 'index'])->name('printlabel.index');

        // API endpoints for grouping-driven label generation
        Route::get('/api/batches/options', [PrintLabelController::class, 'getBatchOptions'])->name('printlabel.api.batch-options');
        Route::get('/api/batches/search', [PrintLabelController::class, 'searchBatches'])->name('printlabel.api.batch-search');
        Route::get('/api/batches/preview', [PrintLabelController::class, 'previewBatch'])->name('printlabel.api.preview');
        Route::post('/api/batches/assign', [PrintLabelController::class, 'assignBatch'])->name('printlabel.api.assign');
        Route::get('/api/statistics', [PrintLabelController::class, 'getStatistics'])->name('printlabel.api.statistics');
        Route::get('/api/grouping/preview', [PrintLabelController::class, 'previewGroupingBatch'])->name('printlabel.api.grouping-preview');
        Route::get('/api/registry-batches', [PrintLabelController::class, 'searchRegistryBatches'])->name('printlabel.api.registry-batches');
        Route::get('/api/rack-label/status', [PrintLabelController::class, 'getRackLabelStatus'])->name('printlabel.api.rack-label-status');
        Route::post('/api/override/lookup', [PrintLabelController::class, 'lookupFileNumbers'])->name('printlabel.api.override-lookup');
    });



    Route::prefix('instrument_registration')->group(function () {
        Route::get('/', [InstrumentRegistrationController::class, 'InstrumentRegistration'])->name('instrument_registration.index');

        // Fix generate route to point to generate method, not save
        Route::get('/generate/{id}', [InstrumentRegistrationController::class, 'generate'])->name('instrument_registration.generate');

        // Save route (POST) 
        Route::post('/save', [InstrumentRegistrationController::class, 'save'])->name('instrument_registration.save');

        // View route is correct but make sure it's accessible
        Route::get('/view/{id}', [InstrumentRegistrationController::class, 'view'])->name('instrument_registration.view');

        // Delete route
        Route::delete('/delete/{id}', [InstrumentRegistrationController::class, 'deleteInstrument'])->name('instrument_registration.delete');

        // New endpoints for direct Instrument registration
        Route::get('/get-batch-data', [InstrumentRegistrationController::class, 'getBatchData'])->name('instrument_registration.get-batch-data');
        Route::get('/get-next-serial', [InstrumentRegistrationController::class, 'getNextSerialNumber'])->name('instrument_registration.get-next-serial');
        Route::get('/batch-print-sessions', [InstrumentRegistrationController::class, 'getBatchPrintSessions'])->name('instrument_registration.batch-print-sessions');
        Route::post('/batch-generate-rds', [InstrumentRegistrationController::class, 'batchGenerateRds'])->name('instrument_registration.batch-generate-rds');
        Route::post('/batch-download-rds-pdf', [InstrumentRegistrationController::class, 'batchDownloadRdsPdf'])->name('instrument_registration.batch-download-rds-pdf');
        Route::post('/batch-generate-cor', [InstrumentRegistrationController::class, 'batchGenerateCor'])->name('instrument_registration.batch-generate-cor');
        Route::post('/batch-download-cor-pdf', [InstrumentRegistrationController::class, 'batchDownloadCorPdf'])->name('instrument_registration.batch-download-cor-pdf');
        Route::post('/batch-complete-print-session', [InstrumentRegistrationController::class, 'completeBatchPrintSession'])->name('instrument_registration.batch-complete-print-session');
        Route::get('/batch-print-pages', [InstrumentRegistrationController::class, 'batchPrintPages'])->name('instrument_registration.batch-print-pages');
        Route::post('/register-single', [InstrumentRegistrationController::class, 'registerSingle'])->name('instrument_registration.register-single');
        Route::post('/register-batch', [InstrumentRegistrationController::class, 'registerBatch'])->name('instrument_registration.register-batch');
        Route::post('/batch-print-runner', [InstrumentRegistrationController::class, 'batchPrintRunner'])->name('instrument_registration.batch-print-runner');
        Route::post('/decline', [InstrumentRegistrationController::class, 'declineRegistration'])->name('instrument_registration.decline');

        // New route for checking registration status
        Route::get('/check-registration-status', [InstrumentRegistrationController::class, 'checkRegistrationStatus'])->name('instrument_registration.check-registration-status');

        // New route for checking overall completion status
        Route::get('/overall-completion-status', [InstrumentRegistrationController::class, 'getOverallCompletionStatus'])->name('instrument_registration.overall-completion-status');

        // RDS (Registered Document Sheet) routes
        Route::post('/generate-rds/{id}', [RDSController::class, 'generateRDS'])->name('rds.generate');
        Route::get('/view-rds/{id}', [RDSController::class, 'viewRDS'])->name('rds.view');
        Route::get('/print-rds/{id}', [RDSController::class, 'printRDS'])->name('rds.print');
        Route::get('/rds-status/{id}', [RDSController::class, 'getRDSStatus'])->name('rds.status');
        Route::delete('/delete-rds/{id}', [RDSController::class, 'deleteRDS'])->name('rds.delete');
        Route::get('/list-rds', [RDSController::class, 'listRDS'])->name('rds.list');

        // Debug route
        Route::get('/debug', [InstrumentRegistrationController::class, 'debug'])->name('instrument_registration.debug');
    });

    // Instrument Types API
    Route::resource('instrument-types', App\Http\Controllers\InstrumentTypeController::class);

    Route::prefix('sltrapproval')->group(function () {
        Route::get('/deeds', [SLTRApprovalController::class, 'deeds'])->name('sltrapproval.deeds');
    });


    Route::prefix('sltrdeedsreg')->group(function () {
        Route::get('/', [SLTRDeedsRegController::class, 'index'])->name('sltrdeedsreg.index');
    });


    Route::prefix('coroi')->group(function () {
        Route::get('/', [App\Http\Controllers\CoroiController::class, 'index'])->name('coroi.index');
        Route::post('/generate/{id}', [App\Http\Controllers\CoroiController::class, 'generate'])->name('coroi.generate');
    });

    // Add any additional routes here
    Route::prefix('sltr_application_form')->group(function () {
        Route::get('/residential', [ApplicationFormController::class, 'residential'])->name('sltr_application_form.residential');
    });
    Route::prefix('sltr_field_data')->group(function () {
        Route::get('/', [SltrFieldDataController::class, 'FieldData'])->name('sltr_field_data.index');
    });

    Route::prefix('survey_plan_extraction')->group(function () {
        Route::get('/', [SurveyPlanExtractionController::class, 'index'])->name('survey_plan_extraction.index');
    });

    Route::prefix('recertification')->group(function () {
        Route::get('/', [RecertificationController::class, 'index'])->name('recertification.index');
    });

});
// Additional attribution routes
Route::get('/attribution/edit/{id}', [App\Http\Controllers\AttributionController::class, 'edit'])->name('attribution.edit');
Route::put('/attribution/update/{id}', [App\Http\Controllers\AttributionController::class, 'update'])->name('attribution.update');
Route::get('/attribution/view-plan/{id}', [App\Http\Controllers\AttributionController::class, 'viewPlan'])->name('attribution.view-plan');
Route::delete('/attribution/{id}', [App\Http\Controllers\AttributionController::class, 'destroy'])->name('attribution.destroy');
