<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BillBalanceController;
use App\Http\Controllers\DeedsApplicationController;
use App\Http\Controllers\FinalConveyanceController;
use App\Http\Controllers\CommissionNewSTController;
use App\Http\Controllers\GroupingDashboardController;
use App\Http\Controllers\ScanUploadsController;
use App\Http\Controllers\STFileNumberController;
use App\Http\Controllers\FileIndexViewController;
use App\Http\Controllers\EntityCustomerController;
use App\Http\Controllers\Api\FileHistoryApiController;
use App\Http\Controllers\IndexedFileTableController;
use App\Http\Controllers\Api\ScanUploadsIndexedFilesController;
use App\Http\Controllers\Api\Pra\PraRecordController;
use App\Http\Controllers\TrackFileArchiveController;
use App\Http\Controllers\PageTypeManagementController;
use App\Http\Controllers\FileSearchController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\Payroll\PayrollDataController;
use App\Http\Controllers\Payroll\PayrollPeriodController;
use App\Http\Controllers\Payroll\PayrollRateController;
use App\Http\Controllers\Payroll\ManualAttendanceController;
use App\Http\Controllers\PraPicAuditController;
use App\Http\Controllers\PUAEditController;
use App\Http\Controllers\ConsentApplicationController;
use App\Http\Controllers\ValuationReportController;
use App\Http\Controllers\PrintManagerController;
use App\Http\Controllers\SurveyReportController;
use App\Http\Controllers\ForInformationController;
use App\Http\Controllers\GknGenerationController;
use App\Http\Controllers\DcivGenerationController;
use App\Http\Controllers\MlsFileNoMatchingController;
use App\Http\Controllers\LandsFileNoMatchingController;
use App\Http\Controllers\StFileNoMatchingController;
use App\Http\Controllers\SltrFileNoMatchingController;
use App\Http\Controllers\ConversionPlanningRecommendationController;
use App\Http\Controllers\ConversionBillsPaymentsController;
use App\Http\Controllers\LandsOneStopShop\ApplicationController;
use App\Http\Controllers\LandsOneStopShop\OpResettlementApplicationController;
use App\Http\Controllers\LandsOneStopShop\OpResettlementBillController;

/*
|--------------------------------------------------------------------------
| App3 Routes
|--------------------------------------------------------------------------
|
| Additional application routes for Final Conveyance and other features
|
*/

Route::middleware(['auth'])->group(function () {

    // Final Conveyance Routes
    Route::prefix('final-conveyance')->name('final-conveyance.')->group(function () {
        Route::get('/info/{id}', [FinalConveyanceController::class, 'show'])->name('info');
        Route::post('/generate', [FinalConveyanceController::class, 'generate'])->name('generate');

        // Buyer management routes
        Route::get('/buyers/{applicationId}', [FinalConveyanceController::class, 'getBuyers'])->name('buyers.list');
        Route::put('/buyers/{id}', [FinalConveyanceController::class, 'updateBuyer'])->name('buyers.update');
        Route::delete('/buyers/{id}', [FinalConveyanceController::class, 'deleteBuyer'])->name('buyers.delete');
    });

    // Grouping Analytics Dashboard
    Route::prefix('grouping-analytics')->name('grouping-analytics.')->group(function () {
        Route::get('/', [GroupingDashboardController::class, 'index'])->name('dashboard');
        Route::get('/data', [GroupingDashboardController::class, 'data'])->name('data');
    });

    Route::get('/file-index-view', [FileIndexViewController::class, 'index'])->name('file-index-view.index');

    // Scan Uploads routes (full CRUD + logging + debug)
    Route::prefix('scan-uploads')->name('scan-uploads.')->group(function () {
        Route::get('/', [ScanUploadsController::class, 'index'])->name('index');
        Route::get('/log', [ScanUploadsController::class, 'log'])->name('log');
        Route::get('/{scan}/download', [ScanUploadsController::class, 'download'])->name('download');
        Route::post('/upload', [ScanUploadsController::class, 'upload'])->name('upload');
        Route::post('/reorder', [ScanUploadsController::class, 'reorder'])->name('reorder');
        Route::post('/{scan}/apply-edits', [ScanUploadsController::class, 'applyEdits'])->name('apply-edits');
        Route::delete('/{scan}', [ScanUploadsController::class, 'destroy'])->name('destroy');
        Route::get('/debug', [ScanUploadsController::class, 'debug'])->name('debug');
        Route::post('/blind-scan/discover', [ScanUploadsController::class, 'discoverBlindScan'])->name('blind-scan.discover');
        Route::post('/blind-scan/transfer', [ScanUploadsController::class, 'transferBlindScan'])->name('blind-scan.transfer');
    });

    // Page Type & Subtype management
    Route::prefix('page-type-management')->name('page-type-management.')->group(function () {
        Route::get('/', [PageTypeManagementController::class, 'index'])->name('index');
        Route::get('/page-types', [PageTypeManagementController::class, 'listPageTypes'])->name('page-types.index');
        Route::post('/page-types', [PageTypeManagementController::class, 'storePageType'])->name('page-types.store');
        Route::put('/page-types/{pageType}', [PageTypeManagementController::class, 'updatePageType'])->name('page-types.update');
        Route::delete('/page-types/{pageType}', [PageTypeManagementController::class, 'deletePageType'])->name('page-types.destroy');

        Route::post('/page-subtypes', [PageTypeManagementController::class, 'storePageSubType'])->name('page-subtypes.store');
        Route::put('/page-subtypes/{pageSubType}', [PageTypeManagementController::class, 'updatePageSubType'])->name('page-subtypes.update');
        Route::delete('/page-subtypes/{pageSubType}', [PageTypeManagementController::class, 'deletePageSubType'])->name('page-subtypes.destroy');
    });

    Route::prefix('scan-uploads/api')->name('scan-uploads.api.')->group(function () {
        Route::get('/indexed-files/quick', [ScanUploadsIndexedFilesController::class, 'quick'])->name('indexed-files.quick');
    });

    Route::prefix('manual-attendance')->name('manual-attendance.')->group(function () {
        Route::get('/', [ManualAttendanceController::class, 'index'])->name('index');
        Route::get('/datatable', [ManualAttendanceController::class, 'datatable'])->name('datatable');
        Route::get('/employees', [ManualAttendanceController::class, 'employees'])->name('employees');
        Route::post('/entries', [ManualAttendanceController::class, 'store'])->name('entries.store');
        Route::post('/entries/{entry}/transition', [ManualAttendanceController::class, 'transition'])->name('entries.transition');
    });

    // Commission New ST Routes
    Route::prefix('commission-new-st')->name('commission-new-st.')->group(function () {
        Route::get('/', [CommissionNewSTController::class, 'index'])->name('index');
        Route::get('/primary-data', [CommissionNewSTController::class, 'getPrimaryData'])->name('primary.data');
        Route::get('/sua-data', [CommissionNewSTController::class, 'getSuAData'])->name('sua.data');
        Route::get('/pua-data', [CommissionNewSTController::class, 'getPuAData'])->name('pua.data');

        // File number generation endpoints
        Route::get('/next-fileno', [CommissionNewSTController::class, 'nextFileNo'])->name('next-fileno');
        Route::get('/sua-next-fileno', [CommissionNewSTController::class, 'suaNextFileNo'])->name('sua.next-fileno');
        Route::get('/pua-next-fileno', [CommissionNewSTController::class, 'puaNextFileNo'])->name('pua.next-fileno');
        Route::post('/commission', [CommissionNewSTController::class, 'commission'])->name('commission');
        Route::post('/commission-pua', [CommissionNewSTController::class, 'commissionPuA'])->name('commission-pua');
    });

    // GKN Generation Routes
    Route::prefix('gkn/generation')->name('gkn-generation.')->group(function () {
        Route::get('/', [GknGenerationController::class, 'index'])->name('index');
        Route::get('/available', [GknGenerationController::class, 'getAvailableGkn'])->name('available');
        Route::post('/store', [GknGenerationController::class, 'store'])->name('store');
        Route::get('/batch-members/{batchNo}', [GknGenerationController::class, 'getBatchMembers'])->name('batch-members');
        Route::get('/{id}/edit', [GknGenerationController::class, 'edit'])->name('edit');
        Route::put('/{id}/update', [GknGenerationController::class, 'update'])->name('update');
        Route::post('/initialize-serial', [GknGenerationController::class, 'initializeSerial'])->name('initialize-serial');
    });

    // DCIV Generation Routes
    Route::prefix('dciv/generation')->name('dciv-generation.')->group(function () {
        Route::get('/', [DcivGenerationController::class, 'index'])->name('index');
        Route::get('/data', [DcivGenerationController::class, 'data'])->name('data');
        Route::get('/available', [DcivGenerationController::class, 'getAvailableDciv'])->name('available');
        Route::post('/store', [DcivGenerationController::class, 'store'])->name('store');
        Route::get('/batch-members/{batchNo}', [DcivGenerationController::class, 'getBatchMembers'])->name('batch-members');
        Route::get('/lookup-title', [DcivGenerationController::class, 'getFileTitle'])->name('lookup-title');
        Route::get('/{id}/edit', [DcivGenerationController::class, 'edit'])->name('edit');
        Route::put('/{id}/update', [DcivGenerationController::class, 'update'])->name('update');
        Route::post('/initialize-serial', [DcivGenerationController::class, 'initializeSerial'])->name('initialize-serial');
        Route::get('/related-files/{fileNumber}', [DcivGenerationController::class, 'getRelatedFiles'])->name('related-files');
    });

    Route::prefix('lands-one-stop-shop')->name('lands-one-stop-shop.')->group(function () {
        // Specific routes first (before {id} wildcard)
        Route::get('/applications/op-resettlement', [OpResettlementApplicationController::class, 'index'])->name('applications.index');
        Route::put('/applications/op-resettlement/{id}/update-land-use', [OpResettlementApplicationController::class, 'updateLandUse'])->name('applications.update-land-use')->where('id', '[0-9]+');
        Route::get('/bill', [OpResettlementBillController::class, 'index'])->name('bill.index');
        Route::get('/bill/{id}/print', [OpResettlementBillController::class, 'printBill'])->name('bill.print')->where('id', '[0-9]+');
        Route::get('/applications/instrument-captures', [ApplicationController::class, 'searchInstrumentCaptures'])->name('all-applications.instrument-captures');
        Route::get('/applications/{id}/bill-status', [ApplicationController::class, 'billStatus'])->name('all-applications.bill-status')->where('id', '[0-9]+');
        Route::get('/applications/{id}/print-acknowledgement', [ApplicationController::class, 'printAcknowledgement'])->name('all-applications.print-acknowledgement')->where('id', '[0-9]+');
        Route::post('/applications/print-recommendation', [ApplicationController::class, 'printRecommendation'])->name('all-applications.print-recommendation');
        Route::post('/applications/save-change-of-ownership', [ApplicationController::class, 'saveChangeOfOwnership'])->name('applications.save-change-of-ownership');
        Route::post('/applications/print-change-of-ownership', [ApplicationController::class, 'printChangeOfOwnership'])->name('applications.print-change-of-ownership');
        Route::post('/applications/save-verification', [ApplicationController::class, 'saveVerification'])->name('applications.save-verification');
        Route::post('/applications/print-verification', [ApplicationController::class, 'printVerification'])->name('applications.print-verification');

        // OSS Applications CRUD
        Route::get('/applications', [ApplicationController::class, 'index'])->name('all-applications.index');
        Route::post('/applications', [ApplicationController::class, 'store'])->name('all-applications.store');
        Route::post('/applications/{id}/bill', [ApplicationController::class, 'bill'])->name('all-applications.bill')->where('id', '[0-9]+');
        Route::get('/applications/{id}', [ApplicationController::class, 'show'])->name('all-applications.show')->where('id', '[0-9]+');
        Route::put('/applications/{id}', [ApplicationController::class, 'update'])->name('all-applications.update')->where('id', '[0-9]+');
        Route::delete('/applications/{id}', [ApplicationController::class, 'destroy'])->name('all-applications.destroy')->where('id', '[0-9]+');
    });

    // Match Existing FileNo (MLSFileNo) Routes
    Route::prefix('mls-file-no-matching')->name('mls-file-no-matching.')->group(function () {
        Route::get('/', [MlsFileNoMatchingController::class, 'index'])->name('index');
        Route::get('/available', [MlsFileNoMatchingController::class, 'getAvailableMls'])->name('available');
        Route::post('/store', [MlsFileNoMatchingController::class, 'store'])->name('store');
        Route::get('/batch-members/{batchNo}', [MlsFileNoMatchingController::class, 'getBatchMembers'])->name('batch-members');
        Route::get('/get-file-details', [MlsFileNoMatchingController::class, 'getFileDetails'])->name('get-file-details');
        Route::get('/{id}/edit', [MlsFileNoMatchingController::class, 'edit'])->name('edit');
        Route::put('/{id}/update', [MlsFileNoMatchingController::class, 'update'])->name('update');
    });

    // Match Existing FileNo (Lands) Routes
    Route::prefix('lands-file-no-matching')->name('lands-file-no-matching.')->group(function () {
        Route::get('/', [LandsFileNoMatchingController::class, 'index'])->name('index');
        Route::get('/available', [LandsFileNoMatchingController::class, 'getAvailableMls'])->name('available');
        Route::get('/details', [LandsFileNoMatchingController::class, 'getFileDetails'])->name('details');
        Route::post('/store', [LandsFileNoMatchingController::class, 'store'])->name('store');
        Route::get('/batch-members/{batchNo}', [LandsFileNoMatchingController::class, 'getBatchMembers'])->name('batch-members');
        Route::get('/{id}/edit', [LandsFileNoMatchingController::class, 'edit'])->name('edit');
        Route::put('/{id}/update', [LandsFileNoMatchingController::class, 'update'])->name('update');
    });

    // Match Existing FileNo (ST) Routes
    Route::prefix('st-file-no-matching')->name('st-file-no-matching.')->group(function () {
        Route::get('/', [StFileNoMatchingController::class, 'index'])->name('index');
        Route::get('/available', [StFileNoMatchingController::class, 'getAvailableMls'])->name('available');
        Route::get('/details', [StFileNoMatchingController::class, 'getFileDetails'])->name('details');
        Route::post('/store', [StFileNoMatchingController::class, 'store'])->name('store');
        Route::get('/batch-members/{batchNo}', [StFileNoMatchingController::class, 'getBatchMembers'])->name('batch-members');
        Route::get('/{id}/edit', [StFileNoMatchingController::class, 'edit'])->name('edit');
        Route::put('/{id}/update', [StFileNoMatchingController::class, 'update'])->name('update');
    });

    // Match Existing FileNo (SLTR) Routes
    Route::prefix('sltr-file-no-matching')->name('sltr-file-no-matching.')->group(function () {
        Route::get('/', [SltrFileNoMatchingController::class, 'index'])->name('index');
        Route::get('/available', [SltrFileNoMatchingController::class, 'getAvailableMls'])->name('available');
        Route::get('/details', [SltrFileNoMatchingController::class, 'getFileDetails'])->name('details');
        Route::post('/store', [SltrFileNoMatchingController::class, 'store'])->name('store');
        Route::get('/batch-members/{batchNo}', [SltrFileNoMatchingController::class, 'getBatchMembers'])->name('batch-members');
        Route::get('/{id}/edit', [SltrFileNoMatchingController::class, 'edit'])->name('edit');
        Route::put('/{id}/update', [SltrFileNoMatchingController::class, 'update'])->name('update');
    });

    // Physical Planning Routes
    Route::prefix('physical-planning')->name('physical-planning.')->group(function () {
        Route::prefix('regular')->name('regular.')->group(function () {
            Route::get('/planning-recommendation', [ConversionPlanningRecommendationController::class, 'index'])->name('planning-recommendation');
            Route::get('/planning-recommendation/data', [ConversionPlanningRecommendationController::class, 'getData'])->name('planning-recommendation.data');
        });

        // Conversion Bills & Payments (AJAX endpoints only — views use programmes routes with ?source=pp_conversion)
        Route::prefix('conversion')->name('conversion.')->group(function () {
            Route::get('/bills/search-jsi', [ConversionBillsPaymentsController::class, 'searchJsiForBilling'])->name('bills.search-jsi');
            Route::post('/bills/calculate', [ConversionBillsPaymentsController::class, 'calculateFee'])->name('bills.calculate');
            Route::post('/bills/generate', [ConversionBillsPaymentsController::class, 'generateBill'])->name('bills.generate');
            Route::get('/bills/{id}', [ConversionBillsPaymentsController::class, 'showBill'])->name('bills.show');
            Route::post('/bills/{id}/payment', [ConversionBillsPaymentsController::class, 'recordPayment'])->name('bills.payment');
        });
    });

    // Utility Routes
    Route::get('/world-time', function () {
        return response()->json([
            'datetime' => now()->toIso8601String(),
            'timezone' => config('app.timezone')
        ]);
    })->name('world-time');

    // ST File Number API Routes
    Route::prefix('api/st-file-numbers')->name('api.st-file-numbers.')->group(function () {
        // File number generation endpoints
        Route::post('/reserve-primary', [STFileNumberController::class, 'reservePrimary'])->name('reserve-primary');
        Route::post('/reserve-sua', [STFileNumberController::class, 'reserveSUA'])->name('reserve-sua');
        Route::post('/reserve-pua', [STFileNumberController::class, 'reservePUA'])->name('reserve-pua');

        // Commission New ST endpoints
        Route::get('/primary-available', [CommissionNewSTController::class, 'getAvailablePrimaryFileNumbers'])->name('primary-available');

        // File number management endpoints
        Route::post('/confirm/{fileNumber}', [STFileNumberController::class, 'confirm'])->name('confirm');
        Route::delete('/release/{fileNumber}', [STFileNumberController::class, 'release'])->name('release');
        Route::get('/details/{fileNumber}', [STFileNumberController::class, 'getDetails'])->name('details');
        Route::get('/units/{parentFileNumber}', [STFileNumberController::class, 'getUnitsByParent'])->name('units');
        Route::get('/buyers/{parentFileNumber}', [STFileNumberController::class, 'getBuyersForParent'])->name('buyers');

        // Preview endpoints (for UI display without reserving)
        Route::post('/preview', [STFileNumberController::class, 'getNextPreview'])->name('preview');

        // Validation and search endpoints
        Route::get('/validate/{fileNumber}', [STFileNumberController::class, 'validateFileNumber'])->name('validate');
        Route::get('/search', [STFileNumberController::class, 'search'])->name('search');
    });

    // Test route for ST File Number Service
    Route::get('/test-st-file-numbers', function () {
        return view('test-st-file-numbers');
    });

    Route::get('/printlabel/print-file-lab', function () {
        return view('printlabel.print-file-lab');
    })->name('printlabel.print-template');

    // Activity Log Routes
    Route::middleware('track.activity')->prefix('activity-logs')->name('activity-logs.')->controller(\App\Http\Controllers\ActivityLogController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{id}', 'show')->name('show');
        Route::delete('/{id}', 'destroy')->name('destroy');
    });

    // Activity Log Dashboard Routes
    Route::middleware('track.activity')->prefix('activity-dashboard')->name('activity-dashboard.')->controller(\App\Http\Controllers\ActivityLogDashboardController::class)->group(function () {
        Route::get('/', 'index')->name('index');
    });

    // Advanced Analytics Dashboard Routes
    Route::middleware('track.activity')->prefix('analytics-dashboard')->name('analytics-dashboard.')->controller(\App\Http\Controllers\AnalyticsDashboardController::class)->group(function () {
        Route::get('/', 'index')->name('index');
    });

    // Report Comparison Routes
    Route::middleware('track.activity')->prefix('report-comparison')->name('report-comparison.')->controller(\App\Http\Controllers\ReportComparisonController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/compare', 'compare')->name('compare');
        Route::post('/export-pdf', 'exportPdf')->name('export-pdf');
    });

    // Activity Log Reports Routes
    Route::middleware('track.activity')->prefix('activity-reports')->name('activity.reports.')->controller(\App\Http\Controllers\ActivityLogReportsController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/generate', 'generate')->name('generate');
        Route::get('/download/{reportId}', 'download')->name('download');
        Route::delete('/{reportId}', 'destroy')->name('destroy');
        Route::post('/schedule', 'scheduleReport')->name('schedule');
        Route::delete('/schedule/{scheduleId}', 'destroySchedule')->name('destroy-schedule');
    });

    Route::middleware('track.activity')->prefix('activity-monitoring')->name('activity-monitoring.')->controller(\App\Http\Controllers\ActivityMonitoringController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/stats', 'stats')->name('stats');
        Route::get('/personal-alert', 'personalAlert')->name('personal-alert');
        Route::get('/users/{user}/module-breakdown', 'moduleBreakdown')->name('users.breakdown');
        Route::get('/users/{user}/timeline', 'userTimeline')->name('users.timeline');
        Route::get('/users/{user}/weekly', 'weeklyBreakdown')->name('users.weekly');
        Route::get('/export/csv', 'exportCsv')->name('export.csv');
        Route::get('/export/pdf', 'exportPdf')->name('export.pdf');
        Route::get('/leaderboard', 'leaderboard')->name('leaderboard');
    });

    Route::middleware('track.activity')->prefix('pra-pic-audit')->name('pra-pic-audit.')->group(function () {
        Route::get('/', [PraPicAuditController::class, 'index'])->name('index');
        Route::get('/pra/user-records', [PraPicAuditController::class, 'praUserRecords'])->name('pra.user-records');
        Route::get('/pra/string-records', [PraPicAuditController::class, 'praStringRecords'])->name('pra.string-records');
        Route::get('/pic/user-records', [PraPicAuditController::class, 'picUserRecords'])->name('pic.user-records');
        Route::get('/pic/string-records', [PraPicAuditController::class, 'picStringRecords'])->name('pic.string-records');
        Route::get('/stats', [PraPicAuditController::class, 'stats'])->name('stats');
    });

    // Entity & Customer Routes
    Route::prefix('entities')->name('entities.')->group(function () {
        Route::get('/', [EntityCustomerController::class, 'indexEntities'])->name('index');
        Route::get('/datatable', [EntityCustomerController::class, 'datatableEntities'])->name('datatable');
        Route::get('/create', [EntityCustomerController::class, 'createEntity'])->name('create');
        Route::post('/', [EntityCustomerController::class, 'storeEntity'])->name('store');
        Route::get('/{entity}', [EntityCustomerController::class, 'showEntity'])->name('show');
        Route::get('/{entity}/edit', [EntityCustomerController::class, 'editEntity'])->name('edit');
        Route::get('/{entity}/customers', [EntityCustomerController::class, 'showEntityCustomers'])->name('customers');
        Route::put('/{entity}', [EntityCustomerController::class, 'updateEntity'])->name('update');
        Route::delete('/{entity}', [EntityCustomerController::class, 'deleteEntity'])->name('destroy');
    });

    Route::prefix('customers')->name('customers.')->group(function () {
        Route::get('/', [EntityCustomerController::class, 'indexCustomers'])->name('index');
        Route::get('/datatable', [EntityCustomerController::class, 'datatableCustomers'])->name('datatable');
        Route::get('/create', [EntityCustomerController::class, 'createCustomer'])->name('create');
        Route::post('/', [EntityCustomerController::class, 'storeCustomer'])->name('store');
        Route::get('/{customer}', [EntityCustomerController::class, 'showCustomer'])->name('show');
        Route::get('/{customer}/edit', [EntityCustomerController::class, 'editCustomer'])->name('edit');
        Route::put('/{customer}', [EntityCustomerController::class, 'updateCustomer'])->name('update');
        Route::delete('/{customer}', [EntityCustomerController::class, 'deleteCustomer'])->name('destroy');
    });

    // AJAX endpoints for entity/customer utilities
    Route::prefix('api/entity-customer')->name('api.entity-customer.')->group(function () {
        Route::post('/find-similar', [EntityCustomerController::class, 'findSimilarEntities'])->name('find-similar');
        Route::post('/link-customers', [EntityCustomerController::class, 'linkCustomersToEntity'])->name('link-customers');
        Route::post('/merge-entities', [EntityCustomerController::class, 'mergeEntities'])->name('merge-entities');
        Route::get('/statistics', [EntityCustomerController::class, 'getStatistics'])->name('statistics');
        Route::get('/search', [EntityCustomerController::class, 'searchEntities'])->name('search');
        Route::get('/by-file-number/{fileNumber}', [EntityCustomerController::class, 'lookupByFileNumber'])->name('by-file-number');
        Route::get('/entity/{entity}/customers', [EntityCustomerController::class, 'getEntityCustomers'])->name('entity-customers');
    });

    Route::prefix('api/file-history')->name('api.file-history.')->group(function () {
        Route::get('', [FileHistoryApiController::class, 'index'])->name('index');
    });

    Route::prefix('api/pra/v1')->name('api.pra.v1.')->group(function () {
        // PRA endpoints
        Route::post('/records/search', [PraRecordController::class, 'search'])->name('records.search');
        Route::get('/records/by-file/{fileNumber}', [PraRecordController::class, 'showByFile'])->name('records.by-file');
        Route::get('/records/all-by-file/{fileNumber}', [PraRecordController::class, 'lookupAllByFile'])->name('records.all-by-file');
        Route::get('/records/{propId}', [PraRecordController::class, 'show'])->name('records.show');
        Route::get('/records/{propId}/history', [PraRecordController::class, 'history'])->name('records.history');
        Route::get('/records/{propId}/duplicates', [PraRecordController::class, 'duplicates'])->name('records.duplicates');

        Route::post('/records', [PraRecordController::class, 'store'])->name('records.store');
        Route::put('/records/{propId}', [PraRecordController::class, 'update'])->name('records.update');
        Route::patch('/records/{propId}', [PraRecordController::class, 'update']);
    });

    Route::prefix('indexed-files')->name('indexed-files.')->group(function () {
        Route::get('/', [IndexedFileTableController::class, 'index'])->name('index');
    });

    Route::get('/kangis/indexed-files', function () {
        return view('kangis.indexed-files');
    })->name('kangis.indexed-files');

    Route::prefix('api/indexed-files')->name('indexed-files.api.')->group(function () {
        Route::get('/stats', [IndexedFileTableController::class, 'stats'])->name('stats');
        Route::get('/list', [IndexedFileTableController::class, 'list'])->name('list');
        Route::get('/view-list', [IndexedFileTableController::class, 'viewList'])->name('view-list');
        Route::get('/related-files/{id}', [IndexedFileTableController::class, 'getRelatedFiles'])->name('related-files');
        Route::put('/related-files/{id}', [IndexedFileTableController::class, 'updateRelatedFile'])->name('related-files.update');
    });

    // Track File (Archive) Routes
    Route::get('/track-file-archive', [TrackFileArchiveController::class, 'index'])->name('track-file-archive.index');

    // Bill Balance Routes
    Route::prefix('bill-balance')->name('bill-balance.')->group(function () {
        Route::get('/', [BillBalanceController::class, 'index'])->name('index');
        Route::get('/create', [BillBalanceController::class, 'create'])->name('create');
        Route::get('/next-reference', [BillBalanceController::class, 'nextReference'])->name('next-reference');
        Route::post('/', [BillBalanceController::class, 'store'])->name('store');
        Route::get('/{billBalance}/print', [BillBalanceController::class, 'print'])->name('print');
        Route::get('/residence-address', [BillBalanceController::class, 'getResidenceAddress'])->name('residence-address');
        Route::get('/{billBalance}', [BillBalanceController::class, 'show'])->name('show');
        Route::get('/{billBalance}/edit', [BillBalanceController::class, 'edit'])->name('edit');
        Route::put('/{billBalance}', [BillBalanceController::class, 'update'])->name('update');
        Route::delete('/{billBalance}', [BillBalanceController::class, 'destroy'])->name('destroy');
    });

    // Deeds Application Routes
    Route::prefix('deeds-applications')->name('deeds-applications.')->group(function () {
        Route::get('/', [DeedsApplicationController::class, 'index'])->name('index');
        Route::get('/create', [DeedsApplicationController::class, 'create'])->name('create');
        Route::post('/', [DeedsApplicationController::class, 'store'])->name('store');
        Route::get('/{deedsApplication}', [DeedsApplicationController::class, 'show'])->name('show');
        Route::get('/{deedsApplication}/edit', [DeedsApplicationController::class, 'edit'])->name('edit');
        Route::put('/{deedsApplication}', [DeedsApplicationController::class, 'update'])->name('update');
        Route::delete('/{deedsApplication}', [DeedsApplicationController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('file-search-db')->name('file-search-db.')->group(function () {
        Route::get('/', [FileSearchController::class, 'index'])->name('index');
        Route::get('/records', [FileSearchController::class, 'apiList'])->name('records');
        Route::get('/export', [FileSearchController::class, 'export'])->name('export');
        Route::get('/debug-export', [FileSearchController::class, 'debugExport'])->name('debug-export');
        Route::get('/import/template', [FileSearchController::class, 'downloadTemplate'])->name('import-template');
        Route::post('/import', [FileSearchController::class, 'import'])->name('import');
        Route::post('/records', [FileSearchController::class, 'store'])->name('store');
        Route::get('/records/{id}', [FileSearchController::class, 'show'])->name('show');
        Route::put('/records/{id}', [FileSearchController::class, 'update'])->name('update');
        Route::delete('/records/{id}', [FileSearchController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('payroll/api')->name('payroll.api.')->group(function () {
        Route::get('/periods', [PayrollPeriodController::class, 'index'])->name('periods.index');
        Route::post('/periods', [PayrollPeriodController::class, 'store'])->name('periods.store');
        Route::get('/periods/{period}', [PayrollPeriodController::class, 'show'])
            ->where('period', '\\d{4}-(0[1-9]|1[0-2])')
            ->name('periods.show');
        Route::post('/periods/{period}/lock', [PayrollPeriodController::class, 'lock'])
            ->where('period', '\\d{4}-(0[1-9]|1[0-2])')
            ->name('periods.lock');

        Route::get('/attendance', [PayrollDataController::class, 'attendance'])->name('attendance.index');
        Route::get('/daily-logins', [PayrollDataController::class, 'dailyLogins'])->name('attendance.daily-logins');
        Route::post('/periods/{period}/attendance/regenerate', [PayrollDataController::class, 'regenerateAttendance'])
            ->where('period', '\\d{4}-(0[1-9]|1[0-2])')
            ->name('attendance.regenerate');
        Route::post('/periods/{period}/attendance/sync-users', [PayrollDataController::class, 'syncAttendanceUsers'])
            ->where('period', '\\d{4}-(0[1-9]|1[0-2])')
            ->name('attendance.sync-users');

        Route::get('/salaries', [PayrollDataController::class, 'salaries'])->name('salaries.index');
        Route::post('/periods/{period}/salaries/recalculate', [PayrollDataController::class, 'recalculateSalaries'])
            ->where('period', '\\d{4}-(0[1-9]|1[0-2])')
            ->name('salaries.recalculate');

        Route::post('/adjustments', [PayrollDataController::class, 'applyAdjustment'])->name('salaries.adjust');

        Route::get('/summary', [PayrollDataController::class, 'summary'])->name('summary.index');

        Route::get('/employees', [PayrollDataController::class, 'employees'])->name('employees.index');

        Route::get('/rates', [PayrollRateController::class, 'index'])->name('rates.index');
        Route::post('/rates', [PayrollRateController::class, 'store'])->name('rates.store');
        Route::match(['put', 'patch'], '/rates/{rate}', [PayrollRateController::class, 'update'])
            ->where('rate', '\\d+')
            ->name('rates.update');
    });

    // Payroll Management Routes
    Route::prefix('payroll')->name('payroll.')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::get('/create', [PayrollController::class, 'create'])->name('create');
        Route::post('/', [PayrollController::class, 'store'])->name('store');
        Route::get('/{id}', [PayrollController::class, 'show'])->name('show');
        Route::get('/{id}/edit', [PayrollController::class, 'edit'])->name('edit');
        Route::put('/{id}', [PayrollController::class, 'update'])->name('update');
        Route::delete('/{id}', [PayrollController::class, 'destroy'])->name('destroy');
    });

    // PUA Edit Routes
    Route::prefix('pua')->name('pua.')->group(function () {
        Route::get('/edit/{id}', [PUAEditController::class, 'edit'])->name('edit');
        Route::put('/update/{id}', [PUAEditController::class, 'update'])->name('update');
    });

    // Consent Application Routes
    Route::prefix('consent-applications')->name('consent-applications.')->group(function () {
        Route::get('/', [ConsentApplicationController::class, 'index'])->name('index');
        Route::get('/lookup', [ConsentApplicationController::class, 'lookupByFileNumber'])->name('lookup');
        Route::post('/', [ConsentApplicationController::class, 'store'])->name('store');
        Route::put('/{id}', [ConsentApplicationController::class, 'update'])->name('update');
        Route::get('/{id}', [ConsentApplicationController::class, 'show'])->name('show');
        Route::post('/log-print/{id}', [ConsentApplicationController::class, 'logPrint'])->name('log-print');
    });

    // Valuation Report Routes
    Route::prefix('valuation-reports')->name('valuation-reports.')->group(function () {
        Route::get('/', [ValuationReportController::class, 'index'])->name('index');
        Route::get('/export', [ValuationReportController::class, 'exportReports'])->name('export');
        Route::get('/create', [ValuationReportController::class, 'create'])->name('create');
        Route::post('/', [ValuationReportController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [ValuationReportController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ValuationReportController::class, 'update'])->name('update');
        Route::get('/{id}', [ValuationReportController::class, 'show'])->name('show');
        Route::post('/log-print/{id}', [ValuationReportController::class, 'logPrint'])->name('log-print');
    });

    // Lands 12 - Request for Survey Report
    Route::prefix('survey-report')->name('survey-report.')->group(function () {
        Route::get('/', [SurveyReportController::class, 'index'])->name('index');
        Route::get('/create', [SurveyReportController::class, 'create'])->name('create');
        Route::post('/', [SurveyReportController::class, 'store'])->name('store');
        Route::post('/send-land-officer-otp', [SurveyReportController::class, 'sendLandOfficerOtp'])->name('send-land-officer-otp');
        Route::get('/{id}/print', [SurveyReportController::class, 'print'])->name('print');
        Route::get('/{id}/edit', [SurveyReportController::class, 'edit'])->name('edit');
        Route::put('/{id}', [SurveyReportController::class, 'update'])->name('update');
        Route::get('/{id}', [SurveyReportController::class, 'show'])->name('show');
        Route::delete('/{id}', [SurveyReportController::class, 'destroy'])->name('destroy');
    });

    // For Information (LAND 16)
    Route::prefix('for-information')->name('for-information.')->group(function () {
        Route::get('/', [ForInformationController::class, 'index'])->name('index');
        Route::get('/create', [ForInformationController::class, 'create'])->name('create');
        Route::post('/', [ForInformationController::class, 'store'])->name('store');
        Route::get('/{id}/print', [ForInformationController::class, 'print'])->name('print');
        Route::get('/{id}/edit', [ForInformationController::class, 'edit'])->name('edit');
        Route::put('/{id}', [ForInformationController::class, 'update'])->name('update');
        Route::get('/{id}', [ForInformationController::class, 'show'])->name('show');
        Route::delete('/{id}', [ForInformationController::class, 'destroy'])->name('destroy');
    });


    // Land ROFO Routes
    Route::prefix('land-rofos')->name('land-rofos.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LandRofoController::class, 'index'])->name('index');
        Route::post('/{id}/generate', [\App\Http\Controllers\LandRofoController::class, 'generate'])->name('generate');
        Route::post('/{id}/assign-security-paper', [\App\Http\Controllers\LandRofoController::class, 'assignSecurityPaperCode'])->name('assign-security-paper');
        Route::get('/{id}/print', [\App\Http\Controllers\LandRofoController::class, 'print'])->name('print');
        Route::post('/{id}/log-print', [\App\Http\Controllers\LandRofoController::class, 'logPrint'])->name('log-print');
    });

    // Land Recommendation Form Routes
    Route::resource('land-recommendations', \App\Http\Controllers\LandRecommendationController::class);
    Route::post('land-recommendations/{id}/log-print', [\App\Http\Controllers\LandRecommendationController::class, 'logPrint'])->name('land-recommendations.log-print');
    Route::post('land-recommendations/{id}/approve', [\App\Http\Controllers\LandRecommendationController::class, 'approve'])->name('land-recommendations.approve');
    Route::get('land-recommendations/{id}/print', [\App\Http\Controllers\LandRecommendationController::class, 'print'])->name('land-recommendations.print');

    Route::prefix('print-manager')->name('print-manager.')->group(function () {
        Route::post('/log', [PrintManagerController::class, 'log'])->name('log');
        Route::post('/batch-log', [PrintManagerController::class, 'batchLog'])->name('batch-log');
        Route::get('/status', [PrintManagerController::class, 'checkStatus'])->name('status');
    });
});
