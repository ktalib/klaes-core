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
use App\Http\Controllers\LegalSearchController;
use App\Http\Controllers\OnPremiseController;
use App\Http\Controllers\LegalsearchreportsController;
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
use App\Http\Controllers\LandsOneStopShop\PlotExtensionController;
use App\Http\Controllers\LandsOneStopShop\LossOfDocumentController;
use App\Http\Controllers\LandsOneStopShop\TemporaryFileController;
use App\Http\Controllers\Deeds\ParcelUpdate\PlotSubdivisionController;
use App\Http\Controllers\Deeds\ParcelUpdate\PlotMergerController;
use App\Http\Controllers\ChangeOfPurpose\ChangeOfPurposeController;
use App\Http\Controllers\ChangeOfName\ChangeOfNameController;
use App\Http\Controllers\OpsDashboardController;
use App\Http\Controllers\RofoStagingDashboardController;
use App\Http\Controllers\KangisPrintLabelController;
use App\Http\Controllers\SltrPrintLabelController;
use App\Http\Controllers\StPrintLabelController;
use App\Http\Controllers\DcivPrintLabelController;
use App\Http\Controllers\LegalSearchTokenController;
use App\Http\Controllers\MortgageController;
use App\Http\Controllers\SurrenderReleaseController;
use App\Http\Controllers\ManualFileLinkageController;

/*
|--------------------------------------------------------------------------
| App3 Routes
|--------------------------------------------------------------------------
|
| Additional application routes for Final Conveyance and other features
|
*/

Route::middleware(['auth'])->group(function () {

    // Mortgage Table Routes
    Route::prefix('mortgages')->name('mortgages.')->group(function () {
        Route::get('/', [MortgageController::class, 'index'])->name('index');
        Route::get('/data', [MortgageController::class, 'getData'])->name('data');
    });

    // Surrender & Release Table Routes
    Route::prefix('surrender-release')->name('surrender-release.')->group(function () {
        Route::get('/', [SurrenderReleaseController::class, 'index'])->name('index');
        Route::get('/data', [SurrenderReleaseController::class, 'getData'])->name('data');
    });

    // OPs Dashboard
    Route::prefix('ops-dashboard')->name('ops-dashboard.')->group(function () {
        Route::get('/', [OpsDashboardController::class, 'index'])->name('index');
        Route::get('/stats', [OpsDashboardController::class, 'stats'])->name('stats');
        Route::get('/chart-land-use', [OpsDashboardController::class, 'chartLandUse'])->name('chart-land-use');
        Route::get('/chart-op-type', [OpsDashboardController::class, 'chartOpType'])->name('chart-op-type');
        Route::get('/table-all-ops', [OpsDashboardController::class, 'tableAllOps'])->name('table-all-ops');
        Route::get('/table-pra', [OpsDashboardController::class, 'tablePra'])->name('table-pra');
        Route::get('/table-change-of-name', [OpsDashboardController::class, 'tableChangeOfName'])->name('table-change-of-name');
        Route::get('/table-deeds', [OpsDashboardController::class, 'tableDeeds'])->name('table-deeds');
    });

    // RofO Staging Dashboard (Letter of Grant / RofO)
    Route::prefix('rofo-staging')->name('rofo-staging.')->group(function () {
        Route::get('/', [RofoStagingDashboardController::class, 'index'])->name('index');
        Route::get('/stats', [RofoStagingDashboardController::class, 'stats'])->name('stats');
        Route::get('/chart-land-use', [RofoStagingDashboardController::class, 'chartLandUse'])->name('chart-land-use');
        Route::get('/chart-instrument-type', [RofoStagingDashboardController::class, 'chartInstrumentType'])->name('chart-instrument-type');
        Route::get('/table-all', [RofoStagingDashboardController::class, 'tableAll'])->name('table-all');
    });

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

    // File indexing search (for Select2 dropdowns)
    Route::get('/file-indexing/search', [ScanUploadsController::class, 'searchFileNumbers'])->name('file-indexing.search');

    // Scan Uploads routes (full CRUD + logging + debug)
    Route::prefix('scan-uploads')->name('scan-uploads.')->group(function () {
        // Reassignment endpoints (added first to avoid {scan} wildcard capture)
        Route::post('/reassign/check', [ScanUploadsController::class, 'reassignCheck'])->name('reassign.check');
        Route::post('/reassign/check-constraints', [ScanUploadsController::class, 'reassignCheckConstraints'])->name('reassign.check-constraints');
        Route::get('/scan-file-info', [ScanUploadsController::class, 'getScanFileInfo'])->name('scan-file-info');
        Route::post('/reassign', [ScanUploadsController::class, 'reassign'])->name('reassign');

        // Standard CRUD & utilities
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
        Route::post('/commission-sua', [CommissionNewSTController::class, 'commissionSuA'])->name('commission-sua');
        Route::post('/commission-pua', [CommissionNewSTController::class, 'commissionPuA'])->name('commission-pua');
    });

    // GKN Generation Routes
    Route::prefix('gkn/generation')->name('gkn-generation.')->group(function () {
        Route::get('/', [GknGenerationController::class, 'index'])->name('index');
        Route::get('/partial', [GknGenerationController::class, 'getPartial'])->name('partial');
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
        Route::get('/edms-files/{fileNumber}', [DcivGenerationController::class, 'getEdmsFiles'])->name('edms-files');
    });

    Route::prefix('lands-one-stop-shop')->name('lands-one-stop-shop.')->group(function () {
        // Specific routes first (before {id} wildcard)
        Route::get('/applications/op-resettlement', [OpResettlementApplicationController::class, 'index'])->name('applications.index');
        Route::put('/applications/op-resettlement/{id}/update-land-use', [OpResettlementApplicationController::class, 'updateLandUse'])->name('applications.update-land-use')->where('id', '[0-9]+');
        Route::put('/applications/op-resettlement/{id}/update-details', [OpResettlementApplicationController::class, 'updateDetails'])->name('applications.update-details')->where('id', '[0-9]+|pra-[0-9]+|ic-[0-9]+');
        Route::get('/applications/op-resettlement/pra-transactions', [OpResettlementApplicationController::class, 'praTransactions'])->name('applications.pra-transactions');
        Route::get('/applications/op-resettlement/{id}/capture-edit', [OpResettlementApplicationController::class, 'captureEdit'])->name('applications.capture-edit')->where('id', '[0-9]+|pra-[0-9]+|ic-[0-9]+');
        Route::get('/applications/match-op/preview', [OpResettlementApplicationController::class, 'matchOpPreview'])->name('applications.match-op-preview');
        Route::post('/applications/match-op', [OpResettlementApplicationController::class, 'matchOp'])->name('applications.match-op');
        Route::delete('/applications/delete-master/{id}', [OpResettlementApplicationController::class, 'deleteMaster'])->name('applications.delete-master');
        Route::delete('/applications/delete-master-bulk', [OpResettlementApplicationController::class, 'deleteMasterBulk'])->name('applications.delete-master-bulk');
        Route::post('/applications/op-resettlement/pra/flag-merger', [OpResettlementApplicationController::class, 'flagMergerOp'])->name('applications.pra-flag-merger');
        Route::get('/bill', [OpResettlementBillController::class, 'index'])->name('bill.index');
        Route::get('/bill/{id}/print', [OpResettlementBillController::class, 'printBill'])->name('bill.print')->where('id', '[0-9]+');
        Route::get('/applications/instrument-captures', [ApplicationController::class, 'searchInstrumentCaptures'])->name('all-applications.instrument-captures');
        Route::get('/applications/lookup-file-indexing', [ApplicationController::class, 'lookupFileIndexing'])->name('all-applications.lookup-file-indexing');
        Route::get('/applications/{id}/bill-status', [ApplicationController::class, 'billStatus'])->name('all-applications.bill-status')->where('id', '[0-9]+');
        Route::get('/applications/verification-status', [ApplicationController::class, 'verificationStatus'])->name('all-applications.verification-status');
        Route::get('/applications/workflow-status', [ApplicationController::class, 'workflowStatus'])->name('all-applications.workflow-status');
        Route::get('/applications/recommendation-status', [ApplicationController::class, 'recommendationStatus'])->name('all-applications.recommendation-status');
        Route::post('/applications/save-recommendation', [ApplicationController::class, 'saveRecommendation'])->name('all-applications.save-recommendation');
        Route::post('/applications/save-acknowledgement', [ApplicationController::class, 'saveAcknowledgementDb'])->name('all-applications.save-acknowledgement');
        Route::get('/applications/{id}/print-acknowledgement', [ApplicationController::class, 'printAcknowledgement'])->name('all-applications.print-acknowledgement')->where('id', '[0-9]+');
        Route::get('/applications/{id}/print-verification-view', [ApplicationController::class, 'printVerificationByRecord'])->name('all-applications.print-verification-view')->where('id', '[0-9]+');
        Route::post('/applications/print-recommendation', [ApplicationController::class, 'printRecommendation'])->name('all-applications.print-recommendation');
        Route::post('/applications/save-change-of-ownership', [ApplicationController::class, 'saveChangeOfOwnership'])->name('applications.save-change-of-ownership');
        Route::post('/applications/save-ffr-change-of-name', [ApplicationController::class, 'saveFfrChangeOfName'])->name('applications.save-ffr-change-of-name');
        Route::post('/applications/capture-ffr-existing', [ApplicationController::class, 'captureFfrExisting'])->name('applications.capture-ffr-existing');
        Route::post('/applications/direct-op-capture', [ApplicationController::class, 'directOpCapture'])->name('applications.direct-op-capture');
        Route::get('/applications/lookup-temp-fileno', [ApplicationController::class, 'lookupTempFileno'])->name('applications.lookup-temp-fileno');
        Route::post('/applications/print-change-of-ownership', [ApplicationController::class, 'printChangeOfOwnership'])->name('applications.print-change-of-ownership');
        Route::post('/applications/save-verification', [ApplicationController::class, 'saveVerification'])->name('applications.save-verification');
        Route::post('/applications/print-verification', [ApplicationController::class, 'printVerification'])->name('applications.print-verification');
        Route::get('/applications/check-duplicate', [ApplicationController::class, 'checkDuplicate'])->name('all-applications.check-duplicate');

        // OSS Applications CRUD
        Route::get('/applications', [ApplicationController::class, 'index'])->name('all-applications.index');
        Route::post('/applications', [ApplicationController::class, 'store'])->name('all-applications.store');
        Route::post('/applications/{id}/bill', [ApplicationController::class, 'bill'])->name('all-applications.bill')->where('id', '[0-9]+');
        Route::get('/applications/{id}', [ApplicationController::class, 'show'])->name('all-applications.show')->where('id', '[0-9]+');
        Route::put('/applications/{id}', [ApplicationController::class, 'update'])->name('all-applications.update')->where('id', '[0-9]+');
        Route::delete('/applications/{id}', [ApplicationController::class, 'destroy'])->name('all-applications.destroy')->where('id', '[0-9]+');
    });

    Route::prefix('plot-extension')->name('plot-extension.')->group(function () {
        Route::get('/', [PlotExtensionController::class, 'index'])->name('index');
        Route::post('/', [PlotExtensionController::class, 'store'])->name('store');
        Route::post('/{id}/approve', [PlotExtensionController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/reject', [PlotExtensionController::class, 'reject'])->name('reject')->where('id', '[0-9]+');
        Route::post('/{id}/generate-application', [PlotExtensionController::class, 'generateApplication'])->name('generate-application')->where('id', '[0-9]+');
        Route::get('/{id}/print-application', [PlotExtensionController::class, 'printApplication'])->name('print-application')->where('id', '[0-9]+');
        Route::post('/{id}/generate-recommendation', [PlotExtensionController::class, 'generateRecommendation'])->name('generate-recommendation')->where('id', '[0-9]+');
        Route::get('/{id}/print-recommendation', [PlotExtensionController::class, 'printRecommendation'])->name('print-recommendation')->where('id', '[0-9]+');
        Route::post('/{id}/knupda', [PlotExtensionController::class, 'updateKnupda'])->name('knupda')->where('id', '[0-9]+');
        Route::get('/{id}', [PlotExtensionController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::put('/{id}', [PlotExtensionController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}', [PlotExtensionController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    Route::prefix('plot-subdivision')->name('plot-subdivision.')->group(function () {
        Route::get('/', [PlotSubdivisionController::class, 'index'])->name('index');
        Route::post('/', [PlotSubdivisionController::class, 'store'])->name('store');
        Route::post('/{id}/approve', [PlotSubdivisionController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/reject', [PlotSubdivisionController::class, 'reject'])->name('reject')->where('id', '[0-9]+');
        Route::post('/{id}/generate-application', [PlotSubdivisionController::class, 'generateApplication'])->name('generate-application')->where('id', '[0-9]+');
        Route::get('/{id}/print-application', [PlotSubdivisionController::class, 'printApplication'])->name('print-application')->where('id', '[0-9]+');
        Route::post('/{id}/generate-recommendation', [PlotSubdivisionController::class, 'generateRecommendation'])->name('generate-recommendation')->where('id', '[0-9]+');
        Route::get('/{id}/print-recommendation', [PlotSubdivisionController::class, 'printRecommendation'])->name('print-recommendation')->where('id', '[0-9]+');
        Route::post('/{id}/knupda', [PlotSubdivisionController::class, 'updateKnupda'])->name('knupda')->where('id', '[0-9]+');
        Route::get('/{id}', [PlotSubdivisionController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::get('/find-by-file/{fileNumber}', [PlotSubdivisionController::class, 'findByFileNo'])->name('find-by-file');
        Route::delete('/{id}', [PlotSubdivisionController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    Route::prefix('plot-merger')->name('plot-merger.')->group(function () {
        Route::get('/', [PlotMergerController::class, 'index'])->name('index');
        Route::post('/', [PlotMergerController::class, 'store'])->name('store');
        Route::get('/{id}', [PlotMergerController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::delete('/{id}', [PlotMergerController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
        Route::post('/{id}/approve', [PlotMergerController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/reject', [PlotMergerController::class, 'reject'])->name('reject')->where('id', '[0-9]+');
        Route::post('/{id}/generate-application', [PlotMergerController::class, 'generateApplication'])->name('generate-application')->where('id', '[0-9]+');
        Route::get('/{id}/print-application', [PlotMergerController::class, 'printApplication'])->name('print-application')->where('id', '[0-9]+');
        Route::post('/{id}/generate-recommendation', [PlotMergerController::class, 'generateRecommendation'])->name('generate-recommendation')->where('id', '[0-9]+');
        Route::get('/{id}/print-recommendation', [PlotMergerController::class, 'printRecommendation'])->name('print-recommendation')->where('id', '[0-9]+');
        Route::get('/find-by-file/{fileNumber}', [PlotMergerController::class, 'findByFileNo'])->name('find-by-file');
        Route::get('/approved-list', [PlotMergerController::class, 'approvedList'])->name('approved-list');
        Route::post('/{id}/knupda', [PlotMergerController::class, 'updateKnupda'])->name('knupda')->where('id', '[0-9]+');
    });

    Route::prefix('loss-of-document')->name('loss-of-document.')->group(function () {
        Route::get('/', [LossOfDocumentController::class, 'index'])->name('index');
        Route::post('/', [LossOfDocumentController::class, 'store'])->name('store');
        Route::get('/{id}', [LossOfDocumentController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::put('/{id}', [LossOfDocumentController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}', [LossOfDocumentController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    Route::prefix('change-of-purpose')->name('change-of-purpose.')->group(function () {
        Route::get('/', [ChangeOfPurposeController::class, 'index'])->name('index');
        Route::post('/', [ChangeOfPurposeController::class, 'store'])->name('store');
        Route::get('/search', [ChangeOfPurposeController::class, 'searchFileNumbers'])->name('search');
        Route::get('/search-approved', [ChangeOfPurposeController::class, 'searchApproved'])->name('search-approved');
        Route::get('/verify-commission', [ChangeOfPurposeController::class, 'verifyForCommission'])->name('verify-commission');
        Route::post('/preview', [ChangeOfPurposeController::class, 'preview'])->name('preview');
        // Record-specific actions — must appear before wildcard /{id} routes
        Route::post('/{id}/approve', [ChangeOfPurposeController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/reject', [ChangeOfPurposeController::class, 'reject'])->name('reject')->where('id', '[0-9]+');
        Route::get('/{id}/acknowledgement', [ChangeOfPurposeController::class, 'acknowledgement'])->name('acknowledgement')->where('id', '[0-9]+');
        Route::get('/{id}/acknowledgement/print', [ChangeOfPurposeController::class, 'printAcknowledgement'])->name('acknowledgement.print')->where('id', '[0-9]+');
        Route::post('/{id}/generate-application', [ChangeOfPurposeController::class, 'generateApplication'])->name('generate-application')->where('id', '[0-9]+');
        Route::get('/{id}/print-application', [ChangeOfPurposeController::class, 'printApplication'])->name('print-application')->where('id', '[0-9]+');
        Route::post('/{id}/generate-recommendation', [ChangeOfPurposeController::class, 'generateRecommendation'])->name('generate-recommendation')->where('id', '[0-9]+');
        Route::get('/{id}/print-recommendation', [ChangeOfPurposeController::class, 'printRecommendation'])->name('print-recommendation')->where('id', '[0-9]+');
        Route::post('/{id}/knupda', [ChangeOfPurposeController::class, 'updateKnupda'])->name('knupda')->where('id', '[0-9]+');
        Route::post('/{id}/commission', [ChangeOfPurposeController::class, 'commissionFileNumber'])->name('commission')->where('id', '[0-9]+');
        Route::get('/{id}', [ChangeOfPurposeController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::put('/{id}', [ChangeOfPurposeController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}', [ChangeOfPurposeController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    Route::prefix('change-of-name')->name('change-of-name.')->group(function () {
        Route::get('/', [ChangeOfNameController::class, 'index'])->name('index');
        Route::post('/', [ChangeOfNameController::class, 'store'])->name('store');
        Route::get('/search', [ChangeOfNameController::class, 'searchFileNumbers'])->name('search');
        Route::post('/{id}/approve', [ChangeOfNameController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/reject', [ChangeOfNameController::class, 'reject'])->name('reject')->where('id', '[0-9]+');
        Route::get('/{id}/acknowledgement/print', [ChangeOfNameController::class, 'printAcknowledgement'])->name('acknowledgement.print')->where('id', '[0-9]+');
        Route::get('/{id}', [ChangeOfNameController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::put('/{id}', [ChangeOfNameController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}', [ChangeOfNameController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    Route::prefix('temporary-file')->name('temporary-file.')->group(function () {
        Route::get('/', [TemporaryFileController::class, 'index'])->name('index');
        Route::post('/', [TemporaryFileController::class, 'store'])->name('store');
        Route::get('/{id}', [TemporaryFileController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::put('/{id}', [TemporaryFileController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}', [TemporaryFileController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
        Route::post('/{id}/approve', [TemporaryFileController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/generate-recommendation', [TemporaryFileController::class, 'generateRecommendation'])->name('generate-recommendation')->where('id', '[0-9]+');
        Route::get('/{id}/print-recommendation', [TemporaryFileController::class, 'printRecommendation'])->name('print-recommendation')->where('id', '[0-9]+');
    });

    // Manually Processed File Linkages Routes
    Route::prefix('manual-linkage')->name('admin.manual-linkage.')->group(function () {
        Route::get('/', [ManualFileLinkageController::class, 'index'])->name('index');
        Route::post('/', [ManualFileLinkageController::class, 'store'])->name('store');
        Route::get('/search-old-file', [ManualFileLinkageController::class, 'searchOldFile'])->name('search-old-file');
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
            Route::get('/planning-recommendation/{applicationId}/approval', [ConversionPlanningRecommendationController::class, 'approvalPage'])->name('planning-recommendation.approval');
        });

        // Conversion Bills & Payments (AJAX endpoints only — views use programmes routes with ?source=pp_conversion)
        Route::prefix('conversion')->name('conversion.')->group(function () {
            Route::get('/recommendation/{applicationId}/print', [ConversionPlanningRecommendationController::class, 'printRecommendation'])->name('recommendation.print');
            Route::get('/bills/search-jsi', [ConversionBillsPaymentsController::class, 'searchJsiForBilling'])->name('bills.search-jsi');
            Route::post('/bills/calculate', [ConversionBillsPaymentsController::class, 'calculateFee'])->name('bills.calculate');
            Route::post('/bills/generate', [ConversionBillsPaymentsController::class, 'generateBill'])->name('bills.generate');
            Route::get('/bills/{id}', [ConversionBillsPaymentsController::class, 'showBill'])->name('bills.show');
            Route::post('/bills/{id}/payment', [ConversionBillsPaymentsController::class, 'recordPayment'])->name('bills.payment');
            Route::get('/schedule-of-payment/{applicationId}/print', [ConversionBillsPaymentsController::class, 'printScheduleOfPayment'])->name('schedule-of-payment.print');
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

    // Legal Search Token Control
    Route::prefix('legal-search-tokens')->name('legal-search-tokens.')->group(function () {
        Route::get('/', [LegalSearchTokenController::class, 'index'])->name('index');
        Route::post('/store', [LegalSearchTokenController::class, 'store'])->name('store');
        Route::post('/check', [LegalSearchTokenController::class, 'checkAvailableToken'])->name('check');
        Route::post('/use', [LegalSearchTokenController::class, 'useToken'])->name('use');
        Route::delete('/{id}', [LegalSearchTokenController::class, 'destroy'])->name('destroy');
    });

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
        Route::post('/records/search', [PraRecordController::class, 'search'])->name('records.search')->withoutMiddleware(['auth']);
        Route::get('/records/by-file/{fileNumber}', [PraRecordController::class, 'showByFile'])->name('records.by-file')->withoutMiddleware(['auth']);
        Route::get('/records/all-by-file/{fileNumber}', [PraRecordController::class, 'lookupAllByFile'])->name('records.all-by-file')->withoutMiddleware(['auth']);
        Route::get('/records/{propId}', [PraRecordController::class, 'show'])->name('records.show')->withoutMiddleware(['auth']);
        Route::get('/records/{propId}/history', [PraRecordController::class, 'history'])->name('records.history')->withoutMiddleware(['auth']);
        Route::get('/records/{propId}/duplicates', [PraRecordController::class, 'duplicates'])->name('records.duplicates')->withoutMiddleware(['auth']);

        Route::post('/records', [PraRecordController::class, 'store'])->name('records.store')->withoutMiddleware(['auth']);
        Route::put('/records/{propId}', [PraRecordController::class, 'update'])->name('records.update')->withoutMiddleware(['auth']);
        Route::patch('/records/{propId}', [PraRecordController::class, 'update'])->withoutMiddleware(['auth']);
    });

    Route::prefix('indexed-files')->name('indexed-files.')->group(function () {
        Route::get('/', [IndexedFileTableController::class, 'index'])->name('index');
        Route::get('/find', [IndexedFileTableController::class, 'findFile'])->name('find');
    });

    Route::get('/kangis/indexed-files', function () {
        return view('kangis.indexed-files');
    })->name('kangis.indexed-files');

    Route::get('/sltr/indexed-files', function () {
        return view('sltr.indexed-files');
    })->name('sltr.indexed-files');

    Route::get('/cadastral/indexed-files', function () {
        return view('cadastral.indexed-files');
    })->name('cadastral.indexed-files');

    Route::prefix('api/indexed-files')->name('indexed-files.api.')->group(function () {
        Route::get('/stats', [IndexedFileTableController::class, 'stats'])->name('stats');
        Route::get('/list', [IndexedFileTableController::class, 'list'])->name('list');
        Route::get('/view-list', [IndexedFileTableController::class, 'viewList'])->name('view-list');
        Route::post('/{id}/mark-duplicate', [IndexedFileTableController::class, 'markAsDuplicate'])->name('mark-duplicate');
        Route::post('/{id}/set-temp-file', [IndexedFileTableController::class, 'setTempFile'])->name('set-temp-file');
        Route::get('/related-files/{id}', [IndexedFileTableController::class, 'getRelatedFiles'])->name('related-files');
        Route::put('/related-files/{id}', [IndexedFileTableController::class, 'updateRelatedFile'])->name('related-files.update');
        Route::get('/edms-files/{id}', [IndexedFileTableController::class, 'getEdmsFiles'])->name('edms-files');
        Route::post('/{id}/update-placeholder', [IndexedFileTableController::class, 'updateKangisPlaceholder'])->name('update-placeholder');
    });

    Route::get('/api/kangis-placeholder/check', [IndexedFileTableController::class, 'checkKangisPlaceholder'])->name('kangis-placeholder.check');

    // New KANGIS (KN Series) autocomplete for kn_awaiting_fileno
    Route::get('/api/kn-grouping/search', function (\Illuminate\Http\Request $request) {
        $q = trim($request->query('q', ''));
        if (strlen($q) < 1) {
            return response()->json(['data' => []]);
        }
        $rows = \Illuminate\Support\Facades\DB::connection('sqlsrv')
            ->table('kn_grouping')
            ->where('kn_awaiting_fileno', 'like', '%' . $q . '%')
            ->orderBy('kn_awaiting_fileno')
            ->limit(20)
            ->get(['id', 'kn_awaiting_fileno']);
        return response()->json(['data' => $rows]);
    })->name('kn-grouping.search');

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
        
        // Master Delete Endpoints
        Route::delete('/delete-master/{id}', [DeedsApplicationController::class, 'deleteMaster'])->name('delete-master');
        Route::delete('/delete-master-bulk', [DeedsApplicationController::class, 'deleteMasterBulk'])->name('delete-master-bulk');
        
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

    // Valuation for Compensation Routes
    Route::prefix('valuation-compensations')->name('valuation-compensations.')->group(function () {
        Route::get('/', [App\Http\Controllers\ValuationCompensationController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\ValuationCompensationController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\ValuationCompensationController::class, 'store'])->name('store');
        Route::post('/batch-store', [App\Http\Controllers\ValuationCompensationController::class, 'batchStore'])->name('batch-store');
        Route::get('/project-print/{projectId}', [App\Http\Controllers\ValuationCompensationController::class, 'projectPrint'])->name('project-print');

        // Workers Pool Console Routes
        Route::prefix('workers')->name('workers.')->group(function () {
            Route::get('/', [App\Http\Controllers\VfcWorkerController::class, 'index'])->name('index');
            Route::get('/next-id', [App\Http\Controllers\VfcWorkerController::class, 'getNextId'])->name('next-id');
            Route::post('/', [App\Http\Controllers\VfcWorkerController::class, 'store'])->name('store');
            Route::delete('/{id}', [App\Http\Controllers\VfcWorkerController::class, 'destroy'])->name('destroy');
        });

        // Project Manager Console Routes (Must be above wildcard routes)
        Route::prefix('projects')->name('projects.')->group(function () {
            Route::get('/', [App\Http\Controllers\ProjectController::class, 'index'])->name('index');
            Route::post('/', [App\Http\Controllers\ProjectController::class, 'store'])->name('store');
            Route::put('/{id}', [App\Http\Controllers\ProjectController::class, 'update'])->name('update');
            Route::get('/next-code', [App\Http\Controllers\ProjectController::class, 'getNextCode'])->name('next-code');
            Route::get('/selection', [App\Http\Controllers\ProjectController::class, 'getProjectsForSelection'])->name('selection');
            Route::get('/{id}/workers', [App\Http\Controllers\ProjectController::class, 'getProjectWorkers'])->name('workers');
            Route::post('/{id}/workers', [App\Http\Controllers\ProjectController::class, 'addWorkerToProject'])->name('add-worker');
            Route::delete('/{id}/workers/{workerId}', [App\Http\Controllers\ProjectController::class, 'removeWorkerFromProject'])->name('remove-worker');
            Route::get('/{id}/templates', [App\Http\Controllers\ProjectController::class, 'generateWorkerTemplates'])->name('templates');
        });

        Route::get('/{id}/edit', [App\Http\Controllers\ValuationCompensationController::class, 'edit'])->name('edit')->where('id', '[0-9]+');
        Route::put('/{id}', [App\Http\Controllers\ValuationCompensationController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::get('/{id}', [App\Http\Controllers\ValuationCompensationController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::delete('/{id}', [App\Http\Controllers\ValuationCompensationController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
        Route::post('/log-print/{id}', [App\Http\Controllers\ValuationCompensationController::class, 'logPrint'])->name('log-print')->where('id', '[0-9]+');
    });

    // Lands 12 - Request for Survey Report
    Route::prefix('survey-report')->name('survey-report.')->group(function () {
        Route::get('/', [SurveyReportController::class, 'index'])->name('index');
        Route::get('/create', [SurveyReportController::class, 'create'])->name('create');
        Route::post('/', [SurveyReportController::class, 'store'])->name('store');
        Route::post('/send-land-officer-otp', [SurveyReportController::class, 'sendLandOfficerOtp'])->name('send-land-officer-otp');
        Route::post('/verify-signature', [SurveyReportController::class, 'verifySignature'])->name('verify-signature');
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
        Route::get('/unprinted-json', [\App\Http\Controllers\LandRofoController::class, 'unprintedJson'])->name('unprinted-json');
        Route::post('/batch-print', [\App\Http\Controllers\LandRofoController::class, 'batchPrint'])->name('batch-print');
        Route::post('/batch-print-log', [\App\Http\Controllers\LandRofoController::class, 'batchPrintLog'])->name('batch-print-log');
        Route::post('/{id}/generate', [\App\Http\Controllers\LandRofoController::class, 'generate'])->name('generate');
        Route::post('/{id}/assign-security-paper', [\App\Http\Controllers\LandRofoController::class, 'assignSecurityPaperCode'])->name('assign-security-paper');
        Route::get('/{id}/print', [\App\Http\Controllers\LandRofoController::class, 'print'])->name('print');
        Route::post('/{id}/log-print', [\App\Http\Controllers\LandRofoController::class, 'logPrint'])->name('log-print');
    });

    // SLTR Recommendation CRUD
    Route::get('/sltr-recommendations', [\App\Http\Controllers\SltrRecommendationController::class, 'index'])->name('sltr-recommendations.index');
    Route::post('/sltr-recommendations', [\App\Http\Controllers\SltrRecommendationController::class, 'store'])->name('sltr-recommendations.store');
    Route::get('/sltr-recommendations/{id}/print', [\App\Http\Controllers\SltrRecommendationController::class, 'printRecommendation'])->name('sltr-recommendations.print');
    Route::get('/sltr-recommendations/{id}', [\App\Http\Controllers\SltrRecommendationController::class, 'show'])->name('sltr-recommendations.show');
    Route::put('/sltr-recommendations/{id}', [\App\Http\Controllers\SltrRecommendationController::class, 'update'])->name('sltr-recommendations.update');
    Route::delete('/sltr-recommendations/{id}', [\App\Http\Controllers\SltrRecommendationController::class, 'destroy'])->name('sltr-recommendations.destroy');
    Route::post('/sltr-recommendations/{id}/approve', [\App\Http\Controllers\SltrRecommendationController::class, 'approve'])->name('sltr-recommendations.approve');
    Route::get('/sltr-recommendations/check-file-number', [\App\Http\Controllers\SltrRecommendationController::class, 'checkFileNumber'])->name('sltr-recommendations.check-file-number');

    // SLTR RofO Routes
    Route::prefix('sltr-rofos')->name('sltr-rofos.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SltrRofoController::class, 'index'])->name('index');
        Route::post('/{id}/generate', [\App\Http\Controllers\SltrRofoController::class, 'generate'])->name('generate');
        Route::get('/{id}/print', [\App\Http\Controllers\SltrRofoController::class, 'print'])->name('print');
        Route::post('/{id}/log-print', [\App\Http\Controllers\SltrRofoController::class, 'logPrint'])->name('log-print');
    });

    // Land Recommendation Form Routes
    Route::post('land-recommendations/batch-approve', [\App\Http\Controllers\LandRecommendationController::class, 'batchApprove'])->name('land-recommendations.batch-approve');
    Route::resource('land-recommendations', \App\Http\Controllers\LandRecommendationController::class);
    Route::post('land-recommendations/{id}/log-print', [\App\Http\Controllers\LandRecommendationController::class, 'logPrint'])->name('land-recommendations.log-print');
    Route::post('land-recommendations/{id}/approve', [\App\Http\Controllers\LandRecommendationController::class, 'approve'])->name('land-recommendations.approve');
    Route::get('land-recommendations/{id}/print', [\App\Http\Controllers\LandRecommendationController::class, 'print'])->name('land-recommendations.print');

    Route::prefix('print-manager')->name('print-manager.')->group(function () {
        Route::post('/log', [PrintManagerController::class, 'log'])->name('log');
        Route::post('/batch-log', [PrintManagerController::class, 'batchLog'])->name('batch-log');
        Route::get('/status', [PrintManagerController::class, 'checkStatus'])->name('status');
    });

    // Property Records Search (unified across 4 tables)
    Route::prefix('property-search')->name('property-search.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PropertySearchController::class, 'index'])->name('index');
        Route::get('/stats', [\App\Http\Controllers\PropertySearchController::class, 'stats'])->name('stats');
        Route::get('/data', [\App\Http\Controllers\PropertySearchController::class, 'data'])->name('data');
        Route::get('/timeline', [\App\Http\Controllers\PropertySearchController::class, 'timeline'])->name('timeline');
    });

    // Legal Search - Official (for filing purpose)
    Route::get('/legal-search/online', [LegalSearchController::class, 'online'])->name('legal_search.online');
    Route::post('/legal-search/online/search', [LegalSearchController::class, 'onlineSearch'])->name('legalsearch.online.search');
    Route::get('/legal-search/print-template/official', [LegalSearchController::class, 'printTemplateOfficial'])->name('legal_search.print.official');
    Route::get('/legal-search/print-template/onpremise', [LegalSearchController::class, 'printTemplateOnpremise'])->name('legal_search.print.onpremise');
    Route::get('/legal-search/print-template/online', [LegalSearchController::class, 'printTemplateOnline'])->name('legal_search.print.online');
    Route::get('/legal-search/report-template-data', [LegalSearchController::class, 'reportTemplateData'])->name('legal_search.print.data');

    Route::prefix('legal_search')->group(function () {
        Route::get('/', [LegalSearchController::class, 'index'])->name('legal_search.index');
        Route::post('/search', [LegalSearchController::class, 'search'])->name('legalsearch.search');
        Route::get('/report', [LegalSearchController::class, 'report'])->name('legal_search.report');
        Route::get('/legal_search_report', [LegalSearchController::class, 'legal_search_report'])->name('legal_search.legal_search_report');

        // Cleanup Mode AJAX endpoints
        Route::post('/match', [LegalSearchController::class, 'match'])->name('legalsearch.match');
        Route::post('/drop', [LegalSearchController::class, 'drop'])->name('legalsearch.drop');
        Route::post('/remove', [LegalSearchController::class, 'remove'])->name('legalsearch.remove');
        Route::post('/update', [LegalSearchController::class, 'update'])->name('legalsearch.update');
        Route::post('/get-record', [LegalSearchController::class, 'getRecord'])->name('legalsearch.getRecord');
        Route::post('/detect-conflicts', [LegalSearchController::class, 'detectConflicts'])->name('legalsearch.detectConflicts');
        Route::post('/save-arrangement', [LegalSearchController::class, 'saveArrangement'])->name('legalsearch.saveArrangement');
        Route::post('/get-arrangement', [LegalSearchController::class, 'getArrangement'])->name('legalsearch.getArrangement');
        Route::get('/comments', [LegalSearchController::class, 'getComments'])->name('legalsearch.getComments');
        Route::post('/comments', [LegalSearchController::class, 'saveComment'])->name('legalsearch.saveComment');
        Route::post('/cofo-comment', [LegalSearchController::class, 'saveCofoComment'])->name('legalsearch.saveCofoComment');
        Route::post('/transfer-caveat', [LegalSearchController::class, 'transferCaveat'])->name('legalsearch.transferCaveat');
        Route::post('/create-record', [LegalSearchController::class, 'createRecord'])->name('legalsearch.createRecord');
    });

    // On-Premise - Pay-per-Search (shares views with Legal Search)
    Route::prefix('onpremise')->group(function () {
        Route::get('/', [OnPremiseController::class, 'index'])->name('onpremise.index');
        Route::post('/search', [OnPremiseController::class, 'search'])->name('onpremise.search');
        Route::get('/report', [OnPremiseController::class, 'report'])->name('onpremise.report');
        Route::get('/legal-search-report', [OnPremiseController::class, 'legal_search_report'])->name('onpremise.legal_search_report');
    });

    // Legal Search Reports
    Route::prefix('legalsearchreports')->group(function () {
        Route::get('/', [LegalsearchreportsController::class, 'index'])->name('legalsearchreports.index');
        Route::get('/data', [LegalsearchreportsController::class, 'data'])->name('legalsearchreports.data');
    });

    // KANGIS Print Labels
    Route::prefix('kangis-printlabel')->name('kangis-printlabel.')->group(function () {
        Route::get('/', [KangisPrintLabelController::class, 'index'])->name('index');
        Route::get('/print-template', function () {
            return view('kangis_printlabel.print-file-lab'); })->name('print-template');
        Route::get('/api/prefixes', [KangisPrintLabelController::class, 'getPrefixes'])->name('api.prefixes');
        Route::get('/api/prefix-next-range', [KangisPrintLabelController::class, 'getNextRangeForPrefix'])->name('api.prefix-next-range');
        Route::get('/api/files', [KangisPrintLabelController::class, 'getAvailableFiles'])->name('api.files');
        Route::get('/api/rack-label/status', [KangisPrintLabelController::class, 'getRackLabelStatus'])->name('api.rack-label.status');
        Route::post('/api/batch', [KangisPrintLabelController::class, 'createBatch'])->name('api.batch.store');
        Route::get('/api/batches', [KangisPrintLabelController::class, 'getBatches'])->name('api.batches');
        Route::get('/api/batch/{id}/print', [KangisPrintLabelController::class, 'getBatchForPrinting'])->name('api.batch.print');
        Route::patch('/api/batch/{id}/print', [KangisPrintLabelController::class, 'markBatchAsPrinted'])->name('api.batch.mark-printed');
        Route::delete('/api/batch/{id}', [KangisPrintLabelController::class, 'deleteBatch'])->name('api.batch.delete');
    });

    // SLTR Print Labels
    Route::prefix('sltr-printlabel')->name('sltr-printlabel.')->group(function () {
        Route::get('/', [SltrPrintLabelController::class, 'index'])->name('index');
        Route::get('/print-template', function () {
            return view('sltr_printlabel.print-file-lab'); })->name('print-template');
        Route::get('/api/prefixes', [SltrPrintLabelController::class, 'getPrefixes'])->name('api.prefixes');
        Route::get('/api/sub-prefixes', [SltrPrintLabelController::class, 'getSubPrefixes'])->name('api.sub-prefixes');
        Route::get('/api/prefix-next-range', [SltrPrintLabelController::class, 'getNextRangeForPrefix'])->name('api.prefix-next-range');
        Route::get('/api/files', [SltrPrintLabelController::class, 'getAvailableFiles'])->name('api.files');
        Route::get('/api/rack-label/status', [SltrPrintLabelController::class, 'getRackLabelStatus'])->name('api.rack-label.status');
        Route::post('/api/batch', [SltrPrintLabelController::class, 'createBatch'])->name('api.batch.store');
        Route::get('/api/batches', [SltrPrintLabelController::class, 'getBatches'])->name('api.batches');
        Route::get('/api/batch/{id}/print', [SltrPrintLabelController::class, 'getBatchForPrinting'])->name('api.batch.print');
        Route::patch('/api/batch/{id}/print', [SltrPrintLabelController::class, 'markBatchAsPrinted'])->name('api.batch.mark-printed');
        Route::delete('/api/batch/{id}', [SltrPrintLabelController::class, 'deleteBatch'])->name('api.batch.delete');
    });

    // ST Print Labels
    Route::prefix('st-printlabel')->name('st-printlabel.')->group(function () {
        Route::get('/', [StPrintLabelController::class, 'index'])->name('index');
        Route::get('/print-template', function () {
            return view('st_printlabel.print-file-lab'); })->name('print-template');
        Route::get('/api/prefixes', [StPrintLabelController::class, 'getPrefixes'])->name('api.prefixes');
        Route::get('/api/sub-prefixes', [StPrintLabelController::class, 'getSubPrefixes'])->name('api.sub-prefixes');
        Route::get('/api/prefix-next-range', [StPrintLabelController::class, 'getNextRangeForPrefix'])->name('api.prefix-next-range');
        Route::get('/api/files', [StPrintLabelController::class, 'getAvailableFiles'])->name('api.files');
        Route::get('/api/rack-label/status', [StPrintLabelController::class, 'getRackLabelStatus'])->name('api.rack-label.status');
        Route::post('/api/batch', [StPrintLabelController::class, 'createBatch'])->name('api.batch.store');
        Route::get('/api/batches', [StPrintLabelController::class, 'getBatches'])->name('api.batches');
        Route::get('/api/batch/{id}/print', [StPrintLabelController::class, 'getBatchForPrinting'])->name('api.batch.print');
        Route::patch('/api/batch/{id}/print', [StPrintLabelController::class, 'markBatchAsPrinted'])->name('api.batch.mark-printed');
        Route::delete('/api/batch/{id}', [StPrintLabelController::class, 'deleteBatch'])->name('api.batch.delete');
    });

    // DCIV Print Labels
    Route::prefix('dciv-printlabel')->name('dciv-printlabel.')->group(function () {
        Route::get('/', [DcivPrintLabelController::class, 'index'])->name('index');
        Route::get('/print-template', function () {
            return view('dciv_printlabel.print-file-lab'); })->name('print-template');
        Route::get('/api/prefixes', [DcivPrintLabelController::class, 'getPrefixes'])->name('api.prefixes');
        Route::get('/api/prefix-next-range', [DcivPrintLabelController::class, 'getNextRangeForPrefix'])->name('api.prefix-next-range');
        Route::get('/api/files', [DcivPrintLabelController::class, 'getAvailableFiles'])->name('api.files');
        Route::get('/api/rack-label/status', [DcivPrintLabelController::class, 'getRackLabelStatus'])->name('api.rack-label.status');
        Route::post('/api/batch', [DcivPrintLabelController::class, 'createBatch'])->name('api.batch.store');
        Route::get('/api/batches', [DcivPrintLabelController::class, 'getBatches'])->name('api.batches');
        Route::get('/api/batch/{id}/print', [DcivPrintLabelController::class, 'getBatchForPrinting'])->name('api.batch.print');
        Route::patch('/api/batch/{id}/print', [DcivPrintLabelController::class, 'markBatchAsPrinted'])->name('api.batch.mark-printed');
        Route::delete('/api/batch/{id}', [DcivPrintLabelController::class, 'deleteBatch'])->name('api.batch.delete');
    });
});

// VFC Mobile Auth & App Routes (Placed outside main auth group to avoid redirect loop)
Route::prefix('valuation-compensations/mobile')->name('valuation-compensations.mobile.')->group(function () {
    Route::get('/login', [App\Http\Controllers\ValuationMobileAuthController::class, 'loginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\ValuationMobileAuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [App\Http\Controllers\ValuationMobileAuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth'])->group(function () {
        Route::get('/', [App\Http\Controllers\ValuationMobileController::class, 'index'])->name('index');
        Route::get('/lookup', [App\Http\Controllers\ValuationMobileController::class, 'getLookupData'])->name('lookup');
        Route::get('/workers/{projectId}', [App\Http\Controllers\ValuationMobileController::class, 'getProjectWorkers'])->name('workers');
        Route::post('/save', [App\Http\Controllers\ValuationMobileController::class, 'store'])->name('save');
    });
});
