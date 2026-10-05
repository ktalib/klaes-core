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
use App\Http\Controllers\MissingFileController;
use App\Http\Controllers\PageTypeManagementController;
use App\Http\Controllers\FileSearchController;
use App\Http\Controllers\RelatedFileNumberController;
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
use App\Http\Controllers\MasterDcivLinkController;
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
use App\Http\Controllers\Deeds\ParcelUpdate\PlotSeparationController;
use App\Http\Controllers\Deeds\ParcelUpdate\DuplexParcelUpdateController;
use App\Http\Controllers\Deeds\ParcelUpdate\PlotMergerController;
use App\Http\Controllers\ChangeOfPurpose\ChangeOfPurposeController;
use App\Http\Controllers\PhysicalPlanning\MasterJsiController;
use App\Http\Controllers\TitleStatus\TitleStatusController;
use App\Http\Controllers\RegrantController;
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
use App\Http\Controllers\Phs\PhsAdminController;

/*
|--------------------------------------------------------------------------
| App3 Routes
|--------------------------------------------------------------------------
|
| Additional application routes for Final Conveyance and other features
|
*/

// ── Legal Search Online Portal (public-facing, no auth required) ─────────
Route::get('/legal-search/online', [\App\Http\Controllers\LegalSearchOnlineController::class, 'index'])->name('legal_search.online');
Route::post('/legal-search/online/search', [\App\Http\Controllers\LegalSearchOnlineController::class, 'publicSearch'])->name('legalsearch.online.search');
Route::post('/legal-search/online/payment/verify', [\App\Http\Controllers\LegalSearchOnlineController::class, 'verifyPayment'])->name('legal_search.online.payment.verify');
Route::get('/legal-search/online/auth/check', [\App\Http\Controllers\LegalSearchOnlineController::class, 'authCheck'])->name('legal_search.online.auth.check');
Route::post('/legal-search/online/auth/pend', [\App\Http\Controllers\LegalSearchOnlineController::class, 'pendSearch'])->name('legal_search.online.auth.pend');

// The Recommendation capture screen's own account of itself — batch mode changes,
// rows loaded, draft saves and restores, submits, script errors, unloads. Outside
// the auth group on purpose: a session that dropped part-way through a long batch
// capture is one of the things this trace exists to prove, and an authenticated-only
// endpoint would bounce exactly that beacon. See
// LandRecommendationDiagnosticsController for what it will and will not accept.
Route::post('land-recommendations/client-log', [\App\Http\Controllers\LandRecommendationDiagnosticsController::class, 'clientLog'])
    ->name('land-recommendations.client-log');

Route::middleware(['auth'])->group(function () {

    // Mortgage Table Routes
    Route::prefix('mortgages')->name('mortgages.')->group(function () {
        Route::get('/', [MortgageController::class, 'index'])->name('index');
        Route::get('/data', [MortgageController::class, 'getData'])->name('data');
        Route::get('/{id}/edit', [MortgageController::class, 'edit'])->name('edit');
        Route::put('/{id}', [MortgageController::class, 'update'])->name('update');
    });

    // Surrender & Release Table Routes
    Route::prefix('surrender-release')->name('surrender-release.')->group(function () {
        Route::get('/', [SurrenderReleaseController::class, 'index'])->name('index');
        Route::get('/data', [SurrenderReleaseController::class, 'getData'])->name('data');
        Route::get('/{id}/edit', [SurrenderReleaseController::class, 'edit'])->name('edit');
        Route::put('/{id}', [SurrenderReleaseController::class, 'update'])->name('update');
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
        Route::post('/reassign/siblings', [ScanUploadsController::class, 'reassignSiblings'])->name('reassign.siblings');
        Route::get('/scan-file-info', [ScanUploadsController::class, 'getScanFileInfo'])->name('scan-file-info');
        // Every scan on one file — lets Page Typing and Doc-WARE open the same
        // reassignment dialog, which is keyed on scan ids rather than the file.
        Route::get('/file-scans', [ScanUploadsController::class, 'fileScans'])->name('file-scans');
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

        // Edit mode: save changes to an already-commissioned ST record. File numbers
        // are never changed here — only applicant, gender and location details.
        Route::post('/{id}/update', [CommissionNewSTController::class, 'updateRecord'])
            ->whereNumber('id')->name('update');
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
        // Serial initialization moved to System Admin → Configurable Entries →
        // Serial Initialization (ConfigurableEntriesController::initializeSerial).
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
        // Serial initialization moved to System Admin → Configurable Entries →
        // Serial Initialization (ConfigurableEntriesController::initializeSerial).
        Route::get('/related-files/{fileNumber}', [DcivGenerationController::class, 'getRelatedFiles'])->name('related-files');
        Route::get('/edms-files/{fileNumber}', [DcivGenerationController::class, 'getEdmsFiles'])->name('edms-files');
    });

    Route::prefix('dciv/master-links')->name('master-dciv-links.')->group(function () {
        Route::get('/', [MasterDcivLinkController::class, 'index'])->name('index');
        Route::get('/data', [MasterDcivLinkController::class, 'data'])->name('data');
    });

    // OP Verification (Deeds) — verify an OP by serial number against the OPs Dashboard record set
    Route::prefix('op-verifications')->name('op-verifications.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OpVerificationController::class, 'index'])->name('index');
        Route::get('/table', [\App\Http\Controllers\OpVerificationController::class, 'table'])->name('table');
        Route::get('/dashboard', [\App\Http\Controllers\OpVerificationController::class, 'dashboard'])->name('dashboard');
        Route::post('/', [\App\Http\Controllers\OpVerificationController::class, 'store'])->name('store');
    });

    Route::get('/oss-verifications', [\App\Http\Controllers\OssVerificationController::class, 'index'])->name('oss-verifications.index');
    Route::post('/oss-verifications/{id}/verify', [\App\Http\Controllers\OssVerificationController::class, 'verify'])->name('oss-verifications.verify');

    // Move a commissioned file between the Land and OSS commissioning registries.
    // Serves both lists, so it sits outside either module's prefix.
    Route::post('/commissioning-registry/move', [\App\Http\Controllers\CommissioningRegistryController::class, 'move'])->name('commissioning-registry.move');

    Route::prefix('lands-one-stop-shop')->name('lands-one-stop-shop.')->group(function () {
        // Specific routes first (before {id} wildcard)
        Route::get('/applications/op-resettlement', [OpResettlementApplicationController::class, 'index'])->name('applications.index');
        Route::put('/applications/op-resettlement/{id}/update-land-use', [OpResettlementApplicationController::class, 'updateLandUse'])->name('applications.update-land-use')->where('id', '[0-9]+');
        Route::put('/applications/op-resettlement/{id}/update-details', [OpResettlementApplicationController::class, 'updateDetails'])->name('applications.update-details')->where('id', '[0-9]+|pra-[0-9]+|ic-[0-9]+');
        Route::get('/applications/op-resettlement/pra-transactions', [OpResettlementApplicationController::class, 'praTransactions'])->name('applications.pra-transactions');
        Route::get('/applications/op-resettlement/op-batch-records', [OpResettlementApplicationController::class, 'opBatchRecords'])->name('applications.op-batch-records');
        Route::get('/applications/op-resettlement/op-search-by-serial', [OpResettlementApplicationController::class, 'opSearchBySerial'])->name('applications.op-search-by-serial');
        Route::post('/applications/op-resettlement/op-match-existing', [OpResettlementApplicationController::class, 'opMatchExisting'])->name('applications.op-match-existing');
        Route::post('/applications/op-resettlement/op-capture-and-link', [OpResettlementApplicationController::class, 'opCaptureAndLink'])->name('applications.op-capture-and-link');
        Route::post('/applications/op-resettlement/op-check-duplicates', [OpResettlementApplicationController::class, 'opCheckDuplicates'])->name('applications.op-check-duplicates');
        Route::get('/applications/op-resettlement/op-commissioned-file', [OpResettlementApplicationController::class, 'opCommissionedFileLookup'])->name('applications.op-commissioned-file');
        Route::post('/applications/op-resettlement/op-capture-commissioned', [OpResettlementApplicationController::class, 'opCaptureForCommissionedFile'])->name('applications.op-capture-commissioned');
        Route::post('/applications/op-resettlement/op-batch-capture', [OpResettlementApplicationController::class, 'opBatchCapture'])->name('applications.op-batch-capture');
        Route::get('/applications/op-resettlement/op-uncommissioned-batches', [OpResettlementApplicationController::class, 'opUncommissionedBatches'])->name('applications.op-uncommissioned-batches');
        Route::get('/applications/op-resettlement/op-uncommissioned-batch-records', [OpResettlementApplicationController::class, 'opUncommissionedBatchRecords'])->name('applications.op-uncommissioned-batch-records');
        Route::post('/applications/op-resettlement/op-batch-delete-record', [OpResettlementApplicationController::class, 'opBatchDeleteRecord'])->name('applications.op-batch-delete-record');
        Route::post('/applications/op-resettlement/op-match-tot-batch', [OpResettlementApplicationController::class, 'matchTotBatchToOps'])->name('applications.op-match-tot-batch');
        Route::post('/applications/op-resettlement/op-link-commissioned', [OpResettlementApplicationController::class, 'linkOpBatchToCommissioned'])->name('applications.op-link-commissioned');
        Route::get('/applications/op-resettlement/op-next-temp-fileno', [OpResettlementApplicationController::class, 'opNextTempFileno'])->name('applications.op-next-temp-fileno');
        Route::get('/applications/op-resettlement/op-districts', [OpResettlementApplicationController::class, 'opDistricts'])->name('applications.op-districts');
        Route::get('/applications/op-resettlement/op-lgas', [OpResettlementApplicationController::class, 'opLgas'])->name('applications.op-lgas');
        Route::post('/applications/op-resettlement/op-update-tot', [OpResettlementApplicationController::class, 'opUpdateTot'])->name('applications.op-update-tot');
        Route::get('/applications/op-resettlement/{id}/capture-edit', [OpResettlementApplicationController::class, 'captureEdit'])->name('applications.capture-edit')->where('id', '[0-9]+|pra-[0-9]+|ic-[0-9]+');
        Route::post('/applications/op-resettlement/{id}/passport', [OpResettlementApplicationController::class, 'updatePassport'])->name('applications.update-passport')->where('id', '[0-9]+|pra-[0-9]+|ic-[0-9]+');
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
        Route::get('/applications/change-of-ownership-status', [ApplicationController::class, 'changeOfOwnershipStatus'])->name('all-applications.change-of-ownership-status');
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
        // Re-open a captured application — chiefly to add plots the layout turned out to
        // need. POST rather than PUT: the form carries file uploads, so it is multipart.
        Route::post('/{id}/update', [PlotSubdivisionController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::get('/find-by-file/{fileNumber}', [PlotSubdivisionController::class, 'findByFileNo'])->name('find-by-file');
        Route::delete('/{id}', [PlotSubdivisionController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    Route::prefix('plot-separation')->name('plot-separation.')->group(function () {
        Route::get('/', [PlotSeparationController::class, 'index'])->name('index');
        Route::post('/', [PlotSeparationController::class, 'store'])->name('store');
        Route::post('/{id}/approve', [PlotSeparationController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/reject', [PlotSeparationController::class, 'reject'])->name('reject')->where('id', '[0-9]+');
        Route::post('/{id}/generate-application', [PlotSeparationController::class, 'generateApplication'])->name('generate-application')->where('id', '[0-9]+');
        Route::get('/{id}/print-application', [PlotSeparationController::class, 'printApplication'])->name('print-application')->where('id', '[0-9]+');
        Route::post('/{id}/generate-recommendation', [PlotSeparationController::class, 'generateRecommendation'])->name('generate-recommendation')->where('id', '[0-9]+');
        Route::get('/{id}/print-recommendation', [PlotSeparationController::class, 'printRecommendation'])->name('print-recommendation')->where('id', '[0-9]+');
        Route::post('/{id}/knupda', [PlotSeparationController::class, 'updateKnupda'])->name('knupda')->where('id', '[0-9]+');
        Route::get('/{id}', [PlotSeparationController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::get('/find-by-file/{fileNumber}', [PlotSeparationController::class, 'findByFileNo'])->name('find-by-file');
        Route::delete('/{id}', [PlotSeparationController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    Route::prefix('plot-merger')->name('plot-merger.')->group(function () {
        Route::get('/', [PlotMergerController::class, 'index'])->name('index');
        Route::post('/', [PlotMergerController::class, 'store'])->name('store');
        Route::get('/{id}', [PlotMergerController::class, 'show'])->name('show')->where('id', '[0-9]+');
        // Re-open a captured application — chiefly to add plots to the merger. POST
        // rather than PUT: the form carries file uploads, so it is multipart.
        Route::post('/{id}/update', [PlotMergerController::class, 'update'])->name('update')->where('id', '[0-9]+');
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

    // Duplex Parcel Update - several parcel updates carried as ONE instruction, on a
    // page of its own. The Land confirm/reject step lives here too, so nothing is
    // added to the existing commissioning screen. A one-stage duplex is valid.
    Route::prefix('duplex-parcel-update')->name('duplex-parcel-update.')->group(function () {
        Route::get('/', [DuplexParcelUpdateController::class, 'index'])->name('index');
        Route::post('/', [DuplexParcelUpdateController::class, 'store'])->name('store');
        Route::get('/{id}', [DuplexParcelUpdateController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::post('/{id}/stages/{stageId}', [DuplexParcelUpdateController::class, 'saveStage'])->name('save-stage')->where(['id' => '[0-9]+', 'stageId' => '[0-9]+']);
        Route::post('/{id}/stages/{stageId}/reject', [DuplexParcelUpdateController::class, 'rejectStage'])->name('reject-stage')->where(['id' => '[0-9]+', 'stageId' => '[0-9]+']);
        Route::get('/{id}/stages/{stageId}/holding-preview', [DuplexParcelUpdateController::class, 'holdingPreview'])->name('holding-preview')->where(['id' => '[0-9]+', 'stageId' => '[0-9]+']);
        // Land may correct WHICH parcels a Change of Purpose renames, never how many — see updateSelection().
        Route::post('/{id}/stages/{stageId}/selection', [DuplexParcelUpdateController::class, 'updateSelection'])->name('selection')->where(['id' => '[0-9]+', 'stageId' => '[0-9]+']);
        Route::post('/{id}/site-plan', [DuplexParcelUpdateController::class, 'uploadSitePlan'])->name('site-plan')->where('id', '[0-9]+');
        Route::delete('/{id}/site-plan', [DuplexParcelUpdateController::class, 'deleteSitePlan'])->name('site-plan.destroy')->where('id', '[0-9]+');
        Route::post('/{id}/knupda', [DuplexParcelUpdateController::class, 'updateKnupda'])->name('knupda')->where('id', '[0-9]+');
        Route::post('/{id}/approve', [DuplexParcelUpdateController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/reject', [DuplexParcelUpdateController::class, 'reject'])->name('reject')->where('id', '[0-9]+');
        Route::post('/{id}/generate-application', [DuplexParcelUpdateController::class, 'generateApplication'])->name('generate-application')->where('id', '[0-9]+');
        Route::get('/{id}/print-application', [DuplexParcelUpdateController::class, 'printApplication'])->name('print-application')->where('id', '[0-9]+');
        Route::post('/{id}/generate-recommendation', [DuplexParcelUpdateController::class, 'generateRecommendation'])->name('generate-recommendation')->where('id', '[0-9]+');
        Route::get('/{id}/print-recommendation', [DuplexParcelUpdateController::class, 'printRecommendation'])->name('print-recommendation')->where('id', '[0-9]+');
        Route::post('/{id}/generate-conveyance', [DuplexParcelUpdateController::class, 'generateConveyance'])->name('generate-conveyance')->where('id', '[0-9]+');
        Route::get('/{id}/print-conveyance', [DuplexParcelUpdateController::class, 'printConveyance'])->name('print-conveyance')->where('id', '[0-9]+');
        Route::post('/{id}/send-to-land', [DuplexParcelUpdateController::class, 'sendToLand'])->name('send-to-land')->where('id', '[0-9]+');
        Route::get('/approved-list', [DuplexParcelUpdateController::class, 'approvedList'])->name('approved-list');
        Route::get('/{id}/summary', [DuplexParcelUpdateController::class, 'summary'])->name('summary')->where('id', '[0-9]+');
        Route::get('/{id}/commission', [DuplexParcelUpdateController::class, 'commissionView'])->name('commission')->where('id', '[0-9]+');
        Route::post('/{id}/commit', [DuplexParcelUpdateController::class, 'commit'])->name('commit')->where('id', '[0-9]+');
        Route::delete('/{id}', [DuplexParcelUpdateController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    /**
     * Master JSI - the Physical Planning site observation behind a parcel update.
     *
     * One register and one form for both categories: SPU (the five single
     * workflows) and APU (the duplex). An approved report is the planning clearance
     * that Generate Recommendation and Approve now rest on, in the place the
     * KAMMA/Physical Planning handshake used to hold - see App\Support\MasterJsiGate.
     *
     * Reached from the Physical Planning sidebar, and from the action menu on every
     * parcel-update listing (?subject_type=&subject_id=&return=).
     */
    Route::prefix('master-jsi')->name('master-jsi.')->group(function () {
        Route::get('/', [MasterJsiController::class, 'index'])->name('index');
        Route::get('/create', [MasterJsiController::class, 'create'])->name('create');
        Route::get('/resolve-subject', [MasterJsiController::class, 'resolveSubject'])->name('resolve-subject');
        Route::get('/coordinates', [MasterJsiController::class, 'coordinates'])->name('coordinates');
        Route::get('/search-subjects', [MasterJsiController::class, 'searchSubjects'])->name('search-subjects');
        Route::post('/', [MasterJsiController::class, 'store'])->name('store');
        Route::get('/{id}', [MasterJsiController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::get('/{id}/edit', [MasterJsiController::class, 'edit'])->name('edit')->where('id', '[0-9]+');
        Route::put('/{id}', [MasterJsiController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::post('/{id}/generate', [MasterJsiController::class, 'generate'])->name('generate')->where('id', '[0-9]+');
        Route::post('/{id}/submit', [MasterJsiController::class, 'submit'])->name('submit')->where('id', '[0-9]+');
        Route::post('/{id}/approve', [MasterJsiController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/reject', [MasterJsiController::class, 'reject'])->name('reject')->where('id', '[0-9]+');
        Route::post('/{id}/return', [MasterJsiController::class, 'returnReport'])->name('return')->where('id', '[0-9]+');
        Route::post('/{id}/evidence', [MasterJsiController::class, 'storeEvidence'])->name('evidence.store')->where('id', '[0-9]+');
        Route::delete('/evidence/{evidence}', [MasterJsiController::class, 'destroyEvidence'])->name('evidence.destroy')->where('evidence', '[0-9]+');
        Route::post('/{id}/send-to-deeds', [MasterJsiController::class, 'sendToDeeds'])->name('send-to-deeds')->where('id', '[0-9]+');
        Route::get('/{id}/print', [MasterJsiController::class, 'print'])->name('print')->where('id', '[0-9]+');
        Route::delete('/{id}', [MasterJsiController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    Route::prefix('loss-of-document')->name('loss-of-document.')->group(function () {
        Route::get('/', [LossOfDocumentController::class, 'index'])->name('index');
        Route::post('/', [LossOfDocumentController::class, 'store'])->name('store');
        Route::get('/{id}', [LossOfDocumentController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::put('/{id}', [LossOfDocumentController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}', [LossOfDocumentController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    // Document Verification console + its verify endpoint.
    Route::get('/information-products/verification', function () {
        return view('information_products.verification');
    })->name('information-products.verification');

    // Throttled: this endpoint answers "does this reference exist" and would
    // otherwise let anyone walk the register by brute force. Legacy QR codes
    // carry bare sequential numbers, so guessing neighbours is trivial without
    // a limit. 30/minute is far above counter use and far below enumeration.
    Route::post('/information-products/verify', [\App\Http\Controllers\DocumentVerificationController::class, 'verify'])
        ->middleware('throttle:30,1')
        ->name('information-products.verify');

    // Local QR renderer for client-side call sites.
    //
    // Server-rendered templates use the qr_data_uri() helper directly; this
    // exists for the handful of places that build the <img src> in JavaScript
    // and so cannot call a PHP helper. Same-origin and behind auth — it
    // replaces api.qrserver.com, which received the payload as a query string
    // on a third-party host and failed outright without outbound internet.
    Route::get('/qr/svg', function (\Illuminate\Http\Request $request) {
        $svg = app(\App\Services\DocumentQr\QrRenderer::class)->svg(
            (string) $request->query('data', ''),
            (int) $request->query('size', 140)
        );

        abort_if($svg === null, 404);

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    })->name('qr.svg');

    // Title Status
    Route::prefix('title-status')->name('title-status.')->group(function () {
        Route::get('/', [TitleStatusController::class, 'index'])->name('index');
        Route::post('/', [TitleStatusController::class, 'store'])->name('store');
        Route::post('/generate-remark', [TitleStatusController::class, 'generateRemark'])->name('generate-remark');
        Route::get('/file-info', [TitleStatusController::class, 'fileInfo'])->name('file-info');
        Route::get('/{id}/certificate-revocation', [TitleStatusController::class, 'printCertificate'])->name('certificate-revocation')->where('id', '[0-9]+');
        Route::post('/{id}/approve', [TitleStatusController::class, 'approve'])->name('approve')->where('id', '[0-9]+');
        Route::post('/{id}/reject', [TitleStatusController::class, 'reject'])->name('reject')->where('id', '[0-9]+');
        Route::get('/{id}', [TitleStatusController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::put('/{id}', [TitleStatusController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::delete('/{id}', [TitleStatusController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
    });

    // Re-grant management — register of all Re-grants + files whose term has expired.
    Route::prefix('regrant')->name('regrant.')->group(function () {
        Route::get('/', [RegrantController::class, 'index'])->name('index');
        Route::post('/raise', [RegrantController::class, 'raise'])->name('raise');
        // Correcting a mis-captured Re-grant: swap the two file numbers round, or
        // withdraw it entirely. The preview is read-only and is what the dialog shows.
        Route::get('/{id}/correction-preview', [RegrantController::class, 'correctionPreview'])->name('correction-preview')->where('id', '[0-9]+');
        Route::post('/{id}/correct', [RegrantController::class, 'correct'])->name('correct')->where('id', '[0-9]+');
    });

    Route::prefix('change-of-purpose')->name('change-of-purpose.')->group(function () {
        Route::get('/', [ChangeOfPurposeController::class, 'index'])->name('index');
        Route::post('/', [ChangeOfPurposeController::class, 'store'])->name('store');
        Route::get('/search', [ChangeOfPurposeController::class, 'searchFileNumbers'])->name('search');
        // Applicant phone/address for a picked file — registry first, then the last
        // parcel update, then commissioning. See applicantDetails().
        Route::get('/applicant-details', [ChangeOfPurposeController::class, 'applicantDetails'])->name('applicant-details');
        Route::get('/search-approved', [ChangeOfPurposeController::class, 'searchApproved'])->name('search-approved');
        // Multi-plot Change of Purpose: one file covering several plots, which
        // commissioning fans out into that many new numbers.
        Route::get('/{id}/plots', [ChangeOfPurposeController::class, 'plots'])->name('plots')->where('id', '[0-9]+');
        Route::post('/{id}/commission-plots', [ChangeOfPurposeController::class, 'commissionBatch'])->name('commission-plots')->where('id', '[0-9]+');
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
        Route::get('/search-holding-file', [ManualFileLinkageController::class, 'searchHoldingFile'])->name('search-holding-file');
        // Bulk CSV import for Subdivision child plots / Merger source plots
        Route::get('/csv-template', [ManualFileLinkageController::class, 'downloadCsvTemplate'])->name('csv-template');
        Route::post('/csv-import', [ManualFileLinkageController::class, 'bulkImportCsv'])->name('csv-import');
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
        Route::get('/folder-files', [\App\Http\Controllers\Api\CadastralFilesController::class, 'index'])->name('folder-files');
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
        Route::get('/client-details', [LegalSearchTokenController::class, 'clientDetails'])->name('client-details');
        Route::post('/client-details', [LegalSearchTokenController::class, 'updateClientDetails'])->name('client-details.update');
        Route::post('/use', [LegalSearchTokenController::class, 'useToken'])->name('use');
        Route::post('/{id}/reset', [LegalSearchTokenController::class, 'resetToken'])->name('reset');
        Route::delete('/{id}', [LegalSearchTokenController::class, 'destroy'])->name('destroy');
    });

    // PHS staff administration
    Route::prefix('system-admin/phs')->name('system-admin.phs.')->group(function () {
        Route::get('/', [PhsAdminController::class, 'index'])->name('index');
        Route::get('/invoices', [PhsAdminController::class, 'invoices'])->name('invoices');
        Route::get('/pending-invoices', [PhsAdminController::class, 'pendingInvoices'])->name('pending-invoices');
        Route::get('/invoices/{txnId}/print', [PhsAdminController::class, 'transactionInvoice'])->name('invoices.print');
        Route::post('/invoices/{txnId}/approve', [PhsAdminController::class, 'approveInvoice'])->name('invoices.approve');
        Route::get('/usage', [PhsAdminController::class, 'usage'])->name('usage');
        Route::get('/searches/{id}/slip', [PhsAdminController::class, 'searchSlip'])->name('searches.slip');
        Route::get('/topups', [PhsAdminController::class, 'topups'])->name('topups');
        Route::post('/topups/{txnId}/approve', [PhsAdminController::class, 'approveTopup'])->name('topups.approve');
        Route::post('/topups/{txnId}/reject', [PhsAdminController::class, 'rejectTopup'])->name('topups.reject');
        Route::get('/wallets', [PhsAdminController::class, 'wallets'])->name('wallets');

        // Feedback / complaints about incomplete or wrong transactions
        Route::get('/feedback', [PhsAdminController::class, 'feedback'])->name('feedback');
        Route::post('/feedback/{id}', [PhsAdminController::class, 'updateFeedback'])->name('feedback.update');

        // Edit requests — members reporting a wrong search result. Returning one
        // authorises that member's single free re-run, so the action lives behind
        // the same admin gate as the rest of this group.
        Route::get('/edit-requests', [\App\Http\Controllers\Phs\PhsEditRequestAdminController::class, 'index'])
            ->name('edit-requests');
        Route::get('/edit-requests/{id}/preview', [\App\Http\Controllers\Phs\PhsEditRequestAdminController::class, 'preview'])
            ->name('edit-requests.preview');
        Route::post('/edit-requests/{id}/return', [\App\Http\Controllers\Phs\PhsEditRequestAdminController::class, 'returnForRerun'])
            ->name('edit-requests.return');
        Route::post('/edit-requests/{id}/decline', [\App\Http\Controllers\Phs\PhsEditRequestAdminController::class, 'decline'])
            ->name('edit-requests.decline');

        // Token package management (CRUD)
        Route::get('/packages', [PhsAdminController::class, 'packages'])->name('packages.index');
        Route::post('/packages', [PhsAdminController::class, 'storePackage'])->name('packages.store');
        Route::put('/packages/{id}', [PhsAdminController::class, 'updatePackage'])->name('packages.update');
        Route::delete('/packages/{id}', [PhsAdminController::class, 'destroyPackage'])->name('packages.destroy');

        Route::get('/institutions/{id}', [PhsAdminController::class, 'show'])->name('institutions.show');
        Route::post('/institutions/{id}/tokens', [PhsAdminController::class, 'allocateTokens'])->name('institutions.tokens');
        Route::post('/institutions/{id}/suspend', [PhsAdminController::class, 'suspend'])->name('institutions.suspend');
        Route::post('/institutions/{id}/activate', [PhsAdminController::class, 'activate'])->name('institutions.activate');
        // Master delete — purges the organization and its entire PHS footprint.
        Route::delete('/institutions/{id}', [PhsAdminController::class, 'destroy'])->name('institutions.destroy');

        // Onboarding requests
        Route::prefix('requests')->name('requests.')->group(function () {
            Route::get('/', [PhsAdminController::class, 'onboardingRequests'])->name('index');
            Route::get('{id}', [PhsAdminController::class, 'showRequest'])->name('show');
            Route::get('{id}/invoice', [PhsAdminController::class, 'requestInvoice'])->name('invoice');
            Route::post('{id}/verify-payment', [PhsAdminController::class, 'verifyRequestPayment'])->name('verify-payment');
            Route::post('{id}/approve', [PhsAdminController::class, 'approveRequest'])->name('approve');
            Route::post('{id}/final-approve', [PhsAdminController::class, 'finalApproveRequest'])->name('final-approve');
            Route::post('{id}/reject', [PhsAdminController::class, 'rejectRequest'])->name('reject');
            Route::post('{id}/resend-sla', [PhsAdminController::class, 'resendSlaLink'])->name('resend-sla');
            Route::post('{id}/resend-onboarding', [PhsAdminController::class, 'resendOnboardingLink'])->name('resend-onboarding');
        });

        // Legal department
        Route::prefix('legal')->name('legal.')->group(function () {
            Route::get('/', [PhsAdminController::class, 'legalDashboard'])->name('index');
            Route::get('{id}', [PhsAdminController::class, 'legalShowRequest'])->name('show');
            Route::post('{id}/approve-docs', [PhsAdminController::class, 'legalApproveDocuments'])->name('approve-docs');
            Route::post('{id}/approve-sla', [PhsAdminController::class, 'legalApproveSla'])->name('approve-sla');
            Route::post('{id}/reject', [PhsAdminController::class, 'legalRejectRequest'])->name('reject');
        });
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
        Route::get('/records/property-by-file/{fileNumber}', [PraRecordController::class, 'propertyByFile'])->name('records.property-by-file')->withoutMiddleware(['auth']);
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
        // Dropdown values for the Advanced Search panel, read from the rows that
        // actually exist (cached) rather than from a hand-kept list.
        Route::get('/filter-options', [IndexedFileTableController::class, 'filterOptions'])->name('filter-options');
        Route::post('/{id}/mark-duplicate', [IndexedFileTableController::class, 'markAsDuplicate'])->name('mark-duplicate');
        Route::get('/{id}/indexing-duplicate-preview', [IndexedFileTableController::class, 'previewIndexingDuplicateMove'])->name('indexing-duplicate-preview');
        Route::post('/{id}/move-to-indexing-duplicates', [IndexedFileTableController::class, 'moveToIndexingDuplicates'])->name('move-to-indexing-duplicates');
        // Master Delete — Supper Admin only, re-checked in the controller.
        Route::delete('/{id}/master-delete', [IndexedFileTableController::class, 'masterDelete'])->name('master-delete');
        Route::post('/{id}/set-temp-file', [IndexedFileTableController::class, 'setTempFile'])->name('set-temp-file');
        Route::post('/{id}/match-correspondence', [IndexedFileTableController::class, 'matchCorrespondence'])->name('match-correspondence');
        Route::post('/{id}/unmatch-correspondence', [IndexedFileTableController::class, 'unmatchCorrespondence'])->name('unmatch-correspondence');
        Route::post('/{id}/match-physical-planning', [IndexedFileTableController::class, 'matchPhysicalPlanning'])->name('match-physical-planning');
        Route::post('/{id}/unmatch-physical-planning', [IndexedFileTableController::class, 'unmatchPhysicalPlanning'])->name('unmatch-physical-planning');
        Route::get('/related-files/{id}', [IndexedFileTableController::class, 'getRelatedFiles'])->name('related-files');
        Route::put('/related-files/{id}', [IndexedFileTableController::class, 'updateRelatedFile'])->name('related-files.update');
        // {id} here is the file_indexings id (the parent), not a link row id — the row to
        // unlink is identified by the file_number in the payload.
        Route::delete('/{id}/related-files', [IndexedFileTableController::class, 'unlinkRelatedFile'])->name('related-files.unlink');
        Route::put('/{id}/coordinates', [IndexedFileTableController::class, 'updateCoordinates'])->name('update-coordinates');
        Route::get('/edms-files/{id}', [IndexedFileTableController::class, 'getEdmsFiles'])->name('edms-files');
        Route::post('/{id}/update-placeholder', [IndexedFileTableController::class, 'updateKangisPlaceholder'])->name('update-placeholder');
    });

    // Indexed files removed from the live tables as duplicates.
    Route::get('/indexing-duplicates', [App\Http\Controllers\IndexingDuplicateController::class, 'index'])
        ->name('indexing-duplicates.index');
    Route::prefix('api/indexing-duplicates')->name('indexing-duplicates.api.')->group(function () {
        Route::get('/stats', [App\Http\Controllers\IndexingDuplicateController::class, 'stats'])->name('stats');
        Route::get('/list', [App\Http\Controllers\IndexingDuplicateController::class, 'list'])->name('list');
        Route::get('/{id}', [App\Http\Controllers\IndexingDuplicateController::class, 'show'])->name('show');
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

    // Missing Files capture Routes
    Route::prefix('missing-files')->name('missing-files.')->group(function () {
        Route::get('/', [MissingFileController::class, 'index'])->name('index');
        Route::get('/data', [MissingFileController::class, 'data'])->name('data');
        Route::get('/check', [MissingFileController::class, 'check'])->name('check');
        Route::post('/', [MissingFileController::class, 'store'])->name('store');
        Route::patch('/{id}/found', [MissingFileController::class, 'markFound'])->name('found');
        Route::delete('/{id}', [MissingFileController::class, 'destroy'])->name('destroy');
    });

    // Bill Balance Routes
    Route::prefix('bill-balance')->name('bill-balance.')->group(function () {
        Route::get('/', [BillBalanceController::class, 'index'])->name('index');
        Route::get('/create', [BillBalanceController::class, 'create'])->name('create');
        Route::get('/next-reference', [BillBalanceController::class, 'nextReference'])->name('next-reference');
        Route::post('/', [BillBalanceController::class, 'store'])->name('store');
        Route::get('/{billBalance}/print', [BillBalanceController::class, 'print'])->name('print');
        Route::get('/residence-address', [BillBalanceController::class, 'getResidenceAddress'])->name('residence-address');
        Route::get('/st-fees', [BillBalanceController::class, 'stFees'])->name('st-fees');
        Route::get('/{billBalance}', [BillBalanceController::class, 'show'])->name('show');
        Route::get('/{billBalance}/edit', [BillBalanceController::class, 'edit'])->name('edit');
        Route::put('/{billBalance}', [BillBalanceController::class, 'update'])->name('update');
        Route::delete('/{billBalance}', [BillBalanceController::class, 'destroy'])->name('destroy');
    });

    // Deeds Application Routes
    Route::prefix('deeds-applications')->name('deeds-applications.')->group(function () {
        Route::get('/', [DeedsApplicationController::class, 'index'])->name('index');
        Route::get('/data', [DeedsApplicationController::class, 'data'])->name('data');
        Route::get('/create', [DeedsApplicationController::class, 'create'])->name('create');
        Route::post('/', [DeedsApplicationController::class, 'store'])->name('store');

        // Master Delete Endpoints
        Route::delete('/delete-master/{id}', [DeedsApplicationController::class, 'deleteMaster'])->name('delete-master');
        Route::delete('/delete-master-bulk', [DeedsApplicationController::class, 'deleteMasterBulk'])->name('delete-master-bulk');

        // Master Print Reset Endpoint
        Route::post('/reset-print-master/{id}', [DeedsApplicationController::class, 'resetPrintMaster'])->name('reset-print-master');

        Route::get('/{deedsApplication}', [DeedsApplicationController::class, 'show'])->name('show');
        Route::get('/{deedsApplication}/edit', [DeedsApplicationController::class, 'edit'])->name('edit');
        Route::put('/{deedsApplication}', [DeedsApplicationController::class, 'update'])->name('update');
        Route::delete('/{deedsApplication}', [DeedsApplicationController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('related-file-number')->name('related-file-number.')->group(function () {
        Route::get('/', [RelatedFileNumberController::class, 'index'])->name('index');
        Route::get('/records', [RelatedFileNumberController::class, 'apiList'])->name('records');
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

    // Deeds pipeline (Valuation -> Consent -> Registration) status, read by the
    // workflow strip on the Valuation, Consent and Instrument screens.
    Route::get('/deeds-pipeline/status', [App\Http\Controllers\DeedsPipelineController::class, 'status'])
        ->name('deeds-pipeline.status');

    // Consent Application Routes
    Route::prefix('consent-applications')->name('consent-applications.')->group(function () {
        Route::get('/', [ConsentApplicationController::class, 'index'])->name('index');
        Route::get('/lookup', [ConsentApplicationController::class, 'lookupByFileNumber'])->name('lookup');
        Route::get('/valuation-bill', [ConsentApplicationController::class, 'valuationBill'])->name('valuation-bill');
        // Must stay above the /{id} routes below, or it is swallowed as a show().
        Route::get('/preview-demo', [ConsentApplicationController::class, 'previewDemo'])->name('preview-demo');
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
        Route::post('/re-evaluate/{id}', [ValuationReportController::class, 'reEvaluate'])->name('re-evaluate');
        Route::get('/{id}', [ValuationReportController::class, 'show'])->name('show');
        Route::post('/log-print/{id}', [ValuationReportController::class, 'logPrint'])->name('log-print');
        Route::post('/reset-prints/{id}', [ValuationReportController::class, 'resetPrints'])->name('reset-prints');
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
            Route::put('/{id}', [App\Http\Controllers\VfcWorkerController::class, 'update'])->name('update');
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
            Route::get('/{id}/delete-summary', [App\Http\Controllers\ProjectController::class, 'deleteSummary'])->name('delete-summary');
            Route::delete('/{id}', [App\Http\Controllers\ProjectController::class, 'destroy'])->name('destroy');
        });

        Route::get('/{id}/edit', [App\Http\Controllers\ValuationCompensationController::class, 'edit'])->name('edit')->where('id', '[0-9]+');
        Route::put('/{id}', [App\Http\Controllers\ValuationCompensationController::class, 'update'])->name('update')->where('id', '[0-9]+');
        Route::get('/{id}', [App\Http\Controllers\ValuationCompensationController::class, 'show'])->name('show')->where('id', '[0-9]+');
        Route::delete('/{id}', [App\Http\Controllers\ValuationCompensationController::class, 'destroy'])->name('destroy')->where('id', '[0-9]+');
        Route::post('/log-print/{id}', [App\Http\Controllers\ValuationCompensationController::class, 'logPrint'])->name('log-print')->where('id', '[0-9]+');
    });

    // LAAS Portal — staff console (Director review + MLP file-number desk).
    // The applicant-facing portal itself is in routes/laas.php, outside admin auth.
    Route::prefix('laas-admin')->name('laas-admin.')
        ->controller(\App\Http\Controllers\Laas\LaasAdminController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/alerts', 'alerts')->name('alerts');

            // Portal accounts. `destroy` is irreversible and refuses any
            // applicant whose work has entered the land registry — see
            // LaasAdminController::destroyApplicant.
            Route::get('/applicants', 'applicants')->name('applicants');
            Route::post('/applicants/{id}/toggle', 'toggleApplicant')->name('applicants.toggle')->where('id', '[0-9]+');
            Route::delete('/applicants/{id}', 'destroyApplicant')->name('applicants.destroy')->where('id', '[0-9]+');
            Route::post('/alerts/{id}/read', 'markAlertRead')->name('alerts.read')->where('id', '[0-9]+');
            Route::get('/{id}', 'show')->name('show')->where('id', '[0-9]+');
            Route::post('/{id}/approve', 'approve')->name('approve')->where('id', '[0-9]+');
            Route::post('/{id}/reject', 'reject')->name('reject')->where('id', '[0-9]+');
            Route::post('/{id}/assign-file-number', 'assignFileNumber')->name('assign-file-number')->where('id', '[0-9]+');
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


    // Global: available security paper codes (used by the shared modal component)
    Route::get('/security-paper-codes/available', function () {
        $codes = \App\Services\SecurityPaperCodeService::availableQuery()
            ->select('paper_code')
            ->pluck('paper_code');
        return response()->json(['codes' => $codes]);
    })->name('security-paper-codes.available');


    // Land ROFO Routes
    Route::prefix('land-rofos')->name('land-rofos.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LandRofoController::class, 'index'])->name('index');
        Route::get('/export', [\App\Http\Controllers\LandRofoController::class, 'export'])->name('export');
        Route::get('/unprinted-json', [\App\Http\Controllers\LandRofoController::class, 'unprintedJson'])->name('unprinted-json');
        Route::get('/reissuance-search', [\App\Http\Controllers\LandRofoController::class, 'reissuanceSearch'])->name('reissuance-search');
        Route::post('/{id}/reissue', [\App\Http\Controllers\LandRofoController::class, 'reissue'])->name('reissue');
        Route::post('/batch-print', [\App\Http\Controllers\LandRofoController::class, 'batchPrint'])->name('batch-print');
        // Proofs of a whole batch — the same letters, marked WHITE COPY, minting no
        // serials and logging no prints. See batchWhiteCopy().
        Route::post('/batch-white-copy', [\App\Http\Controllers\LandRofoController::class, 'batchWhiteCopy'])->name('batch-white-copy');
        // Every RofO in a batch, for the Batches tab — unpaginated on purpose.
        Route::get('/batch/{batchId}/children', [\App\Http\Controllers\LandRofoController::class, 'batchChildren'])->name('batch-children');
        Route::post('/batch-print-log', [\App\Http\Controllers\LandRofoController::class, 'batchPrintLog'])->name('batch-print-log');
        // How far a previous split print of these ids got, so the print dialog can
        // offer to resume run 2 instead of reprinting the Originals.
        Route::post('/batch-print-status', [\App\Http\Controllers\LandRofoController::class, 'batchPrintStatus'])->name('batch-print-status');
        // MASTER DELETE — un-issues the RofO and leaves the recommendation standing.
        // Supper Admin only, and the operator has to type the file number back;
        // both are enforced in the controller, not here.
        Route::delete('/{id}/master-destroy', [\App\Http\Controllers\LandRofoController::class, 'masterDestroy'])->name('master-destroy');
        Route::post('/{id}/generate', [\App\Http\Controllers\LandRofoController::class, 'generate'])->name('generate');
        Route::post('/{id}/assign-security-paper', [\App\Http\Controllers\LandRofoController::class, 'assignSecurityPaperCode'])->name('assign-security-paper');
        Route::post('/{id}/reset-security-paper', [\App\Http\Controllers\LandRofoController::class, 'resetSecurityPaperCode'])->name('reset-security-paper');
        // The application date the letter prints as DATE OF ISSUE: read so the print
        // dialog knows whether to ask, written when it navigates instead of posting
        // the print form.
        Route::post('/issue-dates', [\App\Http\Controllers\LandRofoController::class, 'issueDates'])->name('issue-dates');
        Route::post('/issue-date', [\App\Http\Controllers\LandRofoController::class, 'saveIssueDate'])->name('issue-date');
        // A re-issued letter's "supersedes the previous one issued on …" date, set
        // from the Print Manager.
        Route::post('/{id}/original-issue-date', [\App\Http\Controllers\LandRofoController::class, 'saveOriginalIssueDate'])->name('original-issue-date');
        // Put a letter's print state back so it can be printed again. Super Admin
        // only; the check is in the controller, and the menu that calls it is only
        // rendered for them.
        Route::post('/{id}/reset-print', [\App\Http\Controllers\LandRofoController::class, 'resetPrint'])->name('reset-print');
        // The White Copy: the same letter as a black & white proof for vetting,
        // with the coat of arms, QR, security serial, copy designation and the
        // signature block taken off it. It is not an issued copy, so it never
        // touches rofo_print_count, the security codes or the print log — see
        // printWhiteCopy().
        Route::get('/{id}/white-copy', [\App\Http\Controllers\LandRofoController::class, 'printWhiteCopy'])->name('white-copy');
        Route::get('/{id}/print', [\App\Http\Controllers\LandRofoController::class, 'print'])->name('print');
        Route::post('/{id}/log-print', [\App\Http\Controllers\LandRofoController::class, 'logPrint'])->name('log-print');
    });

    // SLTR Recommendation CRUD
    Route::get('/sltr-recommendations', [\App\Http\Controllers\SltrRecommendationController::class, 'index'])->name('sltr-recommendations.index');
    Route::post('/sltr-recommendations', [\App\Http\Controllers\SltrRecommendationController::class, 'store'])->name('sltr-recommendations.store');
    // The proofing copy — see SltrRecommendationController::printWhiteCopy().
    Route::get('/sltr-recommendations/{id}/white-copy', [\App\Http\Controllers\SltrRecommendationController::class, 'printWhiteCopy'])->name('sltr-recommendations.white-copy');
    Route::get('/sltr-recommendations/{id}/print', [\App\Http\Controllers\SltrRecommendationController::class, 'printRecommendation'])->name('sltr-recommendations.print');
    Route::get('/sltr-recommendations/{id}', [\App\Http\Controllers\SltrRecommendationController::class, 'show'])->name('sltr-recommendations.show');
    Route::put('/sltr-recommendations/{id}', [\App\Http\Controllers\SltrRecommendationController::class, 'update'])->name('sltr-recommendations.update');
    Route::delete('/sltr-recommendations/{id}', [\App\Http\Controllers\SltrRecommendationController::class, 'destroy'])->name('sltr-recommendations.destroy');
    // MASTER DELETE — the recommendation AND the RofO it became, out of every
    // table. destroy() above only removes the row. Supper Admin only.
    Route::delete('/sltr-recommendations/{id}/master-destroy', [\App\Http\Controllers\SltrRecommendationController::class, 'masterDestroy'])->name('sltr-recommendations.master-destroy');
    // Open Approval on/off. Supper Admin only; the check is in the controller,
    // and the switch that calls it is only rendered for them.
    Route::post('/sltr-recommendations/approval-mode', [\App\Http\Controllers\SltrRecommendationController::class, 'setApprovalMode'])->name('sltr-recommendations.approval-mode');
    // Batch approval, capped at SltrRecommendationController::MAX_BATCH_APPROVE.
    // Must stay above /{id}/approve so 'batch-approve' is not read as an id.
    Route::post('/sltr-recommendations/batch-approve', [\App\Http\Controllers\SltrRecommendationController::class, 'batchApprove'])->name('sltr-recommendations.batch-approve');
    Route::post('/sltr-recommendations/{id}/approve', [\App\Http\Controllers\SltrRecommendationController::class, 'approve'])->name('sltr-recommendations.approve');
    Route::get('/sltr-recommendations/check-file-number', [\App\Http\Controllers\SltrRecommendationController::class, 'checkFileNumber'])->name('sltr-recommendations.check-file-number');

    // SLTR RofO Routes
    Route::prefix('sltr-rofos')->name('sltr-rofos.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SltrRofoController::class, 'index'])->name('index');
        // MASTER DELETE — un-issues the RofO and leaves the recommendation standing.
        Route::delete('/{id}/master-destroy', [\App\Http\Controllers\SltrRofoController::class, 'masterDestroy'])->name('master-destroy');
        Route::post('/{id}/generate', [\App\Http\Controllers\SltrRofoController::class, 'generate'])->name('generate');
        Route::post('/{id}/assign-security-paper', [\App\Http\Controllers\SltrRofoController::class, 'assignSecurityPaperCode'])->name('assign-security-paper');
        Route::post('/{id}/reset-security-paper', [\App\Http\Controllers\SltrRofoController::class, 'resetSecurityPaperCode'])->name('reset-security-paper');
        // The proofing copy — see SltrRofoController::printWhiteCopy().
        Route::get('/{id}/white-copy', [\App\Http\Controllers\SltrRofoController::class, 'printWhiteCopy'])->name('white-copy');
        // What the letter prints as DATE OF ISSUE: written by the White Copy card
        // before the proof opens, and by the Print Manager's Edit.
        Route::post('/issue-date', [\App\Http\Controllers\SltrRofoController::class, 'saveIssueDate'])->name('issue-date');
        Route::get('/{id}/print', [\App\Http\Controllers\SltrRofoController::class, 'print'])->name('print');
        Route::post('/{id}/log-print', [\App\Http\Controllers\SltrRofoController::class, 'logPrint'])->name('log-print');
    });

    // SLTR CofO Workflow — the ST CofO workflow copied for SLTR. See SltrCofoController.
    Route::prefix('sltr-cofo')->name('sltr-cofo.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SltrCofoController::class, 'index'])->name('index');
        Route::get('/front-page', [\App\Http\Controllers\SltrCofoController::class, 'frontPage'])->name('front-page');
        // The file number goes in the query string: it is the key, and is free text.
        Route::get('/generate', [\App\Http\Controllers\SltrCofoController::class, 'generate'])->name('generate');
        Route::post('/save', [\App\Http\Controllers\SltrCofoController::class, 'save'])->name('save');
        Route::get('/{capture}/front-page/print', [\App\Http\Controllers\SltrCofoController::class, 'printFrontPage'])->whereNumber('capture')->name('front-page.print');
        Route::post('/{capture}/tdp', [\App\Http\Controllers\SltrCofoController::class, 'storeTdp'])->whereNumber('capture')->name('tdp');
        Route::get('/{capture}/print', [\App\Http\Controllers\SltrCofoController::class, 'printComplete'])->whereNumber('capture')->name('print');
    });

    // Land Recommendation Form Routes
    Route::post('land-recommendations/batch-approve', [\App\Http\Controllers\LandRecommendationController::class, 'batchApprove'])->name('land-recommendations.batch-approve');
    // Must stay above the resource route so /export is not swallowed by /{id}
    Route::get('land-recommendations/export', [\App\Http\Controllers\LandRecommendationController::class, 'export'])->name('land-recommendations.export');
    // Duplicate check by file number — must also stay above the resource route
    Route::get('land-recommendations/check-duplicate', [\App\Http\Controllers\LandRecommendationController::class, 'checkDuplicate'])->name('land-recommendations.check-duplicate');
    // Plot Subdivision batch capture — both must stay above the resource route
    Route::get('land-recommendations/subdivision-mothers', [\App\Http\Controllers\LandRecommendationController::class, 'subdivisionMothers'])->name('land-recommendations.subdivision-mothers');
    Route::get('land-recommendations/subdivision-children', [\App\Http\Controllers\LandRecommendationController::class, 'subdivisionChildren'])->name('land-recommendations.subdivision-children');
    // Regular-files batch capture — the same table, filled from a hand-picked set
    // of file numbers instead of from one mother file's children.
    Route::get('land-recommendations/batch-files', [\App\Http\Controllers\LandRecommendationController::class, 'batchFiles'])->name('land-recommendations.batch-files');
    Route::post('land-recommendations/batch-file-details', [\App\Http\Controllers\LandRecommendationController::class, 'batchFileDetails'])->name('land-recommendations.batch-file-details');
    Route::post('land-recommendations/batch', [\App\Http\Controllers\LandRecommendationController::class, 'storeBatch'])->name('land-recommendations.store-batch');
    // Batch capture autosave — a 100+ child subdivision outlives the session that
    // keys it, so the form drafts itself here. Also above the resource route.
    Route::get('land-recommendations/batch-drafts', [\App\Http\Controllers\LandRecommendationBatchDraftController::class, 'index'])->name('land-recommendations.batch-drafts.index');
    Route::post('land-recommendations/batch-drafts', [\App\Http\Controllers\LandRecommendationBatchDraftController::class, 'store'])->name('land-recommendations.batch-drafts.store');
    Route::get('land-recommendations/batch-drafts/{draftKey}', [\App\Http\Controllers\LandRecommendationBatchDraftController::class, 'show'])->name('land-recommendations.batch-drafts.show');
    Route::delete('land-recommendations/batch-drafts/{draftKey}', [\App\Http\Controllers\LandRecommendationBatchDraftController::class, 'destroy'])->name('land-recommendations.batch-drafts.destroy');
    Route::get('land-recommendations/batch/{batchId}/print', [\App\Http\Controllers\LandRecommendationController::class, 'printBatch'])->name('land-recommendations.batch-print');
    // Proofs of a whole batch. See printBatchWhiteCopy().
    Route::get('land-recommendations/batch/{batchId}/white-copy', [\App\Http\Controllers\LandRecommendationController::class, 'printBatchWhiteCopy'])->name('land-recommendations.batch-white-copy');
    // Every child of a batch, for the Batches tab — unpaginated on purpose.
    Route::get('land-recommendations/batch/{batchId}/children', [\App\Http\Controllers\LandRecommendationController::class, 'batchChildren'])->name('land-recommendations.batch-children');
    // Read-only register of everything captured in one batch.
    Route::get('land-recommendations/batch/{batchId}/records', [\App\Http\Controllers\LandRecommendationController::class, 'batchRecords'])->name('land-recommendations.batch-records');

    // The mother's scanned recommendation. A subdivision's children inherit her
    // grant rather than earning letters of their own, so they show this one
    // document in place of a Print action. See
    // LandRecommendationBatchDocumentController for why it hangs off the batch
    // rather than off each child.
    Route::post('land-recommendations/batch/{batchId}/document', [\App\Http\Controllers\LandRecommendationBatchDocumentController::class, 'store'])->name('land-recommendations.batch-document.store');
    Route::get('land-recommendations/batch/{batchId}/document', [\App\Http\Controllers\LandRecommendationBatchDocumentController::class, 'show'])->name('land-recommendations.batch-document.show');
    Route::delete('land-recommendations/batch/{batchId}/document', [\App\Http\Controllers\LandRecommendationBatchDocumentController::class, 'destroy'])->name('land-recommendations.batch-document.destroy');
    // OP → File Property ID Matching. The FILE is the control record: the officer
    // loads its Property ID on the left, finds the Occupancy Permits that belong to it
    // on the right (by serial, because that is what is printed on the permit), and
    // Batch Match moves every ticked permit onto the file's Property ID. Separate from
    // the OP-holder Match below, which is about a missing transfer, not a scattered
    // parcel id. Every write is recorded in op_propid_matches so a batch can be undone.
    Route::prefix('op-propid-match')->name('op-propid-match.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OpPropIdMatchController::class, 'index'])->name('index');
        // The file itself is chosen through the shared Global File Number Selector, so
        // this page has no file-search endpoint of its own — only the load of the one
        // the picker returned.
        Route::get('/file', [\App\Http\Controllers\OpPropIdMatchController::class, 'fileTarget'])->name('file');
        Route::get('/ops', [\App\Http\Controllers\OpPropIdMatchController::class, 'searchOps'])->name('ops');
        Route::post('/batch', [\App\Http\Controllers\OpPropIdMatchController::class, 'batchMatch'])->name('batch');
        // The other direction, on the same page: take permits back off the parcel they
        // are on, individually or as one Group OP. Reassigning afterwards is optional.
        Route::post('/unmatch', [\App\Http\Controllers\OpPropIdMatchController::class, 'batchUnmatch'])->name('unmatch');
        Route::get('/batches', [\App\Http\Controllers\OpPropIdMatchController::class, 'recentBatches'])->name('batches');
        Route::post('/undo', [\App\Http\Controllers\OpPropIdMatchController::class, 'undoBatch'])->name('undo');
    });

    // OP Unlink / Reassignment — the other direction. Takes permits OFF a file's
    // Property ID and gives them their own (one each, or one shared "Group OP"), then
    // OPTIONALLY reassigns them to another file. Optional is the rule: unlinking is a
    // complete act on its own, and the page never demands a destination.
    //
    // Only two endpoints are new. The file picker, the OP search and the final Match
    // are the ones above — a second copy would drift from the first, and both write the
    // same prop_id. Undo is shared too: unlink batches are OPU-, matches OPM-.
    Route::prefix('op-unlink')->name('op-unlink.')->group(function () {
        Route::get('/', [\App\Http\Controllers\OpUnlinkController::class, 'index'])->name('index');
        Route::get('/associated', [\App\Http\Controllers\OpUnlinkController::class, 'associated'])->name('associated');
        Route::post('/unlink', [\App\Http\Controllers\OpUnlinkController::class, 'unlink'])->name('unlink');
        Route::get('/batches', [\App\Http\Controllers\OpUnlinkController::class, 'recentBatches'])->name('batches');
    });

    // OP-holder Match. A file whose Occupancy Permit names one holder while File
    // Indexing names another, with no transfer explaining the change, is offered a
    // Match on the capture form: it writes the missing Transfer of Title, and the
    // record then carries the ALREADY-APPROVED letter (uploaded here) instead of
    // generating a new one. Above the resource route so /{id}/approved-recommendation
    // is not read as a record show.
    Route::get('land-recommendations/op-match/check', [\App\Http\Controllers\LandRecommendationOpMatchController::class, 'check'])->name('land-recommendations.op-match.check');
    Route::post('land-recommendations/op-match', [\App\Http\Controllers\LandRecommendationOpMatchController::class, 'match'])->name('land-recommendations.op-match.store');
    Route::post('land-recommendations/{id}/approved-recommendation', [\App\Http\Controllers\LandRecommendationOpMatchController::class, 'storeDocument'])->name('land-recommendations.approved-recommendation.store')->whereNumber('id');
    Route::get('land-recommendations/{id}/approved-recommendation', [\App\Http\Controllers\LandRecommendationOpMatchController::class, 'showDocument'])->name('land-recommendations.approved-recommendation.show')->whereNumber('id');
    Route::delete('land-recommendations/{id}/approved-recommendation', [\App\Http\Controllers\LandRecommendationOpMatchController::class, 'destroyDocument'])->name('land-recommendations.approved-recommendation.destroy')->whereNumber('id');

    // A saved batch re-opened in the capture form, and the save that comes back.
    // Above the resource route, or /batch/{id}/edit reads as a record id.
    Route::get('land-recommendations/batch/{batchId}/edit', [\App\Http\Controllers\LandRecommendationController::class, 'editBatch'])->name('land-recommendations.batch-edit');
    Route::put('land-recommendations/batch/{batchId}', [\App\Http\Controllers\LandRecommendationController::class, 'updateBatch'])->name('land-recommendations.batch-update');
    // /land-recommendations/batch is a POST endpoint with no GET side. Anything
    // that arrives here by GET — a refresh of the page the save posted to, the URL
    // typed or opened from history, a 30x that turned the POST into a GET — used to
    // fall through to the resource route below and be read as a record whose id is
    // the word "batch", which SQL Server then failed to cast to bigint: a raw
    // "Error converting data type nvarchar to bigint" in the user's face. Send them
    // back to the capture screen instead; the draft they were keying is still there
    // to resume.
    Route::get('land-recommendations/batch', function () {
        return redirect()->route('land-recommendations.create')
            ->with('warning', 'That address only accepts a batch being saved, so the page could not be reopened from it. '
                . 'Switch Batch Mode on and press Resume to pick your draft up where it left off — nothing you keyed is lost.');
    })->name('land-recommendations.batch-get');
    // Every id below is a bigint. Without this, any non-numeric segment reaching
    // {id} is passed to the query as-is and comes back as an SQL cast error rather
    // than a 404 — the same trap the GET above works around.
    // MASTER DELETE — erases the recommendation AND the RofO it became. Declared
    // ahead of the resource route so {id}/master-destroy is not read as a resource
    // segment. Supper Admin only, typed confirmation — enforced in the controller.
    Route::delete('land-recommendations/{id}/master-destroy', [\App\Http\Controllers\LandRecommendationController::class, 'masterDestroy'])->name('land-recommendations.master-destroy')->whereNumber('id');
    Route::resource('land-recommendations', \App\Http\Controllers\LandRecommendationController::class)
        ->whereNumber('land_recommendation');
    Route::post('land-recommendations/{id}/log-print', [\App\Http\Controllers\LandRecommendationController::class, 'logPrint'])->name('land-recommendations.log-print')->whereNumber('id');
    Route::post('land-recommendations/{id}/approve', [\App\Http\Controllers\LandRecommendationController::class, 'approve'])->name('land-recommendations.approve')->whereNumber('id');
    // The proofing copy: the same recommendation as a black & white draft, with the
    // coat of arms, QR, security serial and signature blocks off it and no
    // acknowledgement sheet behind it. It mints no serial and logs no print — see
    // printWhiteCopy().
    Route::get('land-recommendations/{id}/white-copy', [\App\Http\Controllers\LandRecommendationController::class, 'printWhiteCopy'])->name('land-recommendations.white-copy')->whereNumber('id');
    Route::get('land-recommendations/{id}/print', [\App\Http\Controllers\LandRecommendationController::class, 'print'])->name('land-recommendations.print')->whereNumber('id');

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

    // Online Legal Search Admin & Feedback
    Route::prefix('legal-search/online/admin')->name('legal-search-online.admin.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'admin'])->name('index');
        // Director / Deputy Director approval queue for public search requests.
        Route::get('/requests', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestsIndex'])->name('requests');
        Route::get('/requests/{id}/preview', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestPreview'])->name('requests.preview');
        // The identification (IYC) behind a request — approver-gated, and the only
        // screen that surfaces the uploaded document.
        Route::get('/requests/{id}/identification', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestIdentification'])->name('requests.identification');
        Route::get('/requests/{id}/invoice', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestInvoice'])->name('requests.invoice');
        // Editable counterpart of the preview: correct the records before approving.
        Route::get('/requests/{id}/correct', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestCorrect'])->name('requests.correct');
        Route::get('/requests/my-signature', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestSignature'])->name('requests.signature');
        Route::post('/requests/my-signature/otp', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestSignatureOtp'])->name('requests.signature.otp');
        Route::post('/requests/my-signature/verify', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestSignatureVerify'])->name('requests.signature.verify');
        Route::post('/requests/{id}/approve', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestApprove'])->name('requests.approve');
        Route::post('/requests/{id}/reject', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestReject'])->name('requests.reject');
        Route::post('/requests/{id}/resend', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'requestResend'])->name('requests.resend');

        Route::get('/feedback', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'feedbackIndex'])->name('feedback');
        Route::post('/feedback', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'feedbackStore'])->name('feedback.store');
        Route::put('/feedback/{id}', [\App\Http\Controllers\LegalSearchOnlineAdminController::class, 'feedbackUpdate'])->name('feedback.update');
    });

    // Legal Search - Print templates
    Route::get('/legal-search/print-template/official', [LegalSearchController::class, 'printTemplateOfficial'])->name('legal_search.print.official');
    Route::get('/legal-search/print-template/onpremise', [LegalSearchController::class, 'printTemplateOnpremise'])->name('legal_search.print.onpremise');
    Route::get('/legal-search/print-template/online', [LegalSearchController::class, 'printTemplateOnline'])->name('legal_search.print.online');
    Route::get('/legal-search/report-template-data', [LegalSearchController::class, 'reportTemplateData'])->name('legal_search.print.data');

    Route::prefix('legal_search')->group(function () {
        Route::get('/', [LegalSearchController::class, 'index'])->name('legal_search.index');
        Route::get('/dashboard-stats', [LegalSearchController::class, 'dashboardStats'])->name('legal_search.dashboard_stats');
        Route::post('/search', [LegalSearchController::class, 'search'])->name('legalsearch.search');
        Route::get('/report', [LegalSearchController::class, 'report'])->name('legal_search.report');
        Route::get('/legal_search_report', [LegalSearchController::class, 'legal_search_report'])->name('legal_search.legal_search_report');
        Route::get('/archive-summary', [LegalSearchController::class, 'archiveSummary'])->name('legal_search.archive-summary');

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
        // GET as well as POST: the Add Property Record dialog reads this from a
        // static JS file (public/js/pra/form-controller.js) where a plain GET
        // avoids threading a CSRF token through every module that includes it.
        Route::match(['GET', 'POST'], '/existing-records', [LegalSearchController::class, 'existingRecords'])->name('legalsearch.existingRecords');
        Route::post('/update-file-indexing', [LegalSearchController::class, 'updateFileIndexing'])->name('legalsearch.updateFileIndexing');
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
            return view('kangis_printlabel.print-file-lab');
        })->name('print-template');
        Route::get('/api/prefixes', [KangisPrintLabelController::class, 'getPrefixes'])->name('api.prefixes');
        Route::get('/api/prefix-next-range', [KangisPrintLabelController::class, 'getNextRangeForPrefix'])->name('api.prefix-next-range');
        Route::get('/api/registry-batches', [KangisPrintLabelController::class, 'getRegistryBatchNos'])->name('api.registry-batches');
        Route::get('/api/files', [KangisPrintLabelController::class, 'getAvailableFiles'])->name('api.files');
        Route::get('/api/rack-label/status', [KangisPrintLabelController::class, 'getRackLabelStatus'])->name('api.rack-label.status');
        Route::post('/api/batch', [KangisPrintLabelController::class, 'createBatch'])->name('api.batch.store');
        Route::get('/api/batches', [KangisPrintLabelController::class, 'getBatches'])->name('api.batches');
        Route::get('/api/batch-index', [KangisPrintLabelController::class, 'getBatchIndex'])->name('api.batch-index');
        Route::get('/batch-index/print', [KangisPrintLabelController::class, 'printBatchIndex'])->name('batch-index.print');
        Route::post('/api/manual-fetch', [KangisPrintLabelController::class, 'fetchManualFiles'])->name('api.manual_fetch');
        Route::post('/api/batches/backfill-sys-batch-no', [KangisPrintLabelController::class, 'backfillSysBatchNo'])->name('api.batches.backfill');
        Route::get('/api/batch/{id}/print', [KangisPrintLabelController::class, 'getBatchForPrinting'])->name('api.batch.print');
        Route::patch('/api/batch/{id}/print', [KangisPrintLabelController::class, 'markBatchAsPrinted'])->name('api.batch.mark-printed');
        Route::delete('/api/batch/{id}', [KangisPrintLabelController::class, 'deleteBatch'])->name('api.batch.delete');
    });

    // SLTR Print Labels
    Route::prefix('sltr-printlabel')->name('sltr-printlabel.')->group(function () {
        Route::get('/', [SltrPrintLabelController::class, 'index'])->name('index');
        Route::get('/print-template', function () {
            return view('sltr_printlabel.print-file-lab');
        })->name('print-template');
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
            return view('st_printlabel.print-file-lab');
        })->name('print-template');
        Route::get('/api/prefixes', [StPrintLabelController::class, 'getPrefixes'])->name('api.prefixes');
        Route::get('/api/application-types', [StPrintLabelController::class, 'getApplicationTypes'])->name('api.application-types');
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
            return view('dciv_printlabel.print-file-lab');
        })->name('print-template');
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

    // PRS Annual Progress Report (UI prototype — static sample data, no backend yet)
    Route::prefix('prs-report')->name('prs-report.')->group(function () {
        Route::get('/', [App\Http\Controllers\Prs\PrsAnnualReportController::class, 'index'])->name('index');
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
