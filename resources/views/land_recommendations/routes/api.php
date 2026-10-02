<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\FileIndexingController;
use App\Http\Controllers\InstrumentController;
use App\Http\Controllers\InstrumentTypeController;
use App\Http\Controllers\FileNumberApiController;
use App\Http\Controllers\TrackFileArchiveController;
use App\Http\Controllers\ReferenceDataController;
use App\Http\Controllers\FileSearchController;
use App\Http\Controllers\Api\GroupingController as GroupingAnalyticsController;
use App\Http\Controllers\Api\CsvImporter\FileNumberImportController;
use App\Http\Controllers\Api\CsvImporter\FileIndexingImportController;
use App\Http\Controllers\ConsentApplicationController;
use App\Http\Controllers\Api\FileTrackerDashboardApiController;
use App\Http\Controllers\Api\OfficeController;
use App\Http\Controllers\Api\FileNumberReservationController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('csv-import/file-number')
    ->controller(FileNumberImportController::class)
    ->group(function () {
        Route::post('/upload', 'upload');
        Route::get('/preview/{sessionId}', 'preview');
        Route::post('/import/{sessionId}', 'import');
    });

Route::prefix('csv-import/file-indexing')
    ->controller(FileIndexingImportController::class)
    ->group(function () {
        Route::post('/upload', 'upload');
        Route::get('/uploads', 'uploads');
        Route::get('/status/{sessionId}', 'status');
        Route::get('/preview/{sessionId}', 'preview');
        Route::get('/preview/{sessionId}/rows', 'previewRows');
        Route::post('/import/{sessionId}', 'import');
    });

Route::get('/file-search', [FileSearchController::class, 'api'])->name('api.file-search');

// File Number Reservation API (prevent race conditions in file number generation)
Route::middleware(['auth'])->prefix('file-numbers')->group(function () {
    Route::post('/reserve', [FileNumberReservationController::class, 'reserve'])->name('api.file-numbers.reserve');
    Route::post('/reserve-batch', [FileNumberReservationController::class, 'reserveBatch'])->name('api.file-numbers.reserve-batch');
    Route::post('/confirm-reservation', [FileNumberReservationController::class, 'confirm'])->name('api.file-numbers.confirm-reservation');
    Route::post('/release-reservation', [FileNumberReservationController::class, 'release'])->name('api.file-numbers.release-reservation');
    Route::post('/extend-reservation', [FileNumberReservationController::class, 'extend'])->name('api.file-numbers.extend-reservation');
    Route::get('/reservation-status/{uuid}', [FileNumberReservationController::class, 'status'])->name('api.file-numbers.reservation-status');
    Route::get('/find-gaps', [FileNumberReservationController::class, 'findGaps'])->name('api.file-numbers.find-gaps');
});

// Offices API (web + mobile consumers)
Route::get('/offices', [OfficeController::class, 'index'])->name('api.offices.index');

// File tracker dashboard overview
Route::get('/file-tracker-dashboard/overview', [FileTrackerDashboardApiController::class, 'overview'])
    ->name('api.file-tracker-dashboard.overview');

Route::get('/file-tracker-dashboard/notifications', [FileTrackerDashboardApiController::class, 'notifications'])
    ->name('api.file-tracker-dashboard.notifications');

// API routes for application data
Route::get('/get-application-data', function (Request $request) {
    $applicationId = $request->input('application_id');

    if (!$applicationId) {
        return response()->json(['error' => 'No application ID provided'], 400);
    }

    try {
        $applicationData = DB::connection('sqlsrv')
            ->table('dbo.mother_applications')
            ->where('id', $applicationId)
            ->first();

        if ($applicationData) {
            return response()->json([
                'success' => true,
                'data' => $applicationData
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No data found for this application ID'
            ], 404);
        }
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving application data',
            'error' => $e->getMessage()
        ], 500);
    }
})->name('api.get-application-data');

// File Indexing API Endpoints
Route::get('/file-records', [FileIndexingController::class, 'getAllRecords']);
Route::get('/file-records/{id}', [FileIndexingController::class, 'getRecord']);
Route::post('/file-records/search', [FileIndexingController::class, 'searchRecords']);
Route::get('/file-indexings/lookup-by-number', [FileIndexingController::class, 'lookupByFileNumber']);
Route::get('/track-file-archive/label-metadata', [TrackFileArchiveController::class, 'labelMetadata'])
    ->name('api.track-file-archive.label-metadata');

// New API endpoints for CofO and Property Transaction data
Route::get('/cofo-record/{fileNo}', [FileIndexingController::class, 'getCofORecord']);
Route::get('/property-transaction', [FileIndexingController::class, 'getPropertyTransactionRecord']);

// Instruments API Routes - Not requiring authentication for now to fix the immediate issue
Route::post('/instruments/generate-particulars', [InstrumentController::class, 'generateParticulars']);

// Instrument Capture API
Route::get('/instruments/check-duplicate', [InstrumentController::class, 'checkDuplicate']);
Route::get('/instruments/capture/{id}', [InstrumentController::class, 'getCaptureRecord']);
Route::get('/instruments/op-serial-lookup', [InstrumentController::class, 'lookupByOpSerialNumber']);

// Consent Applications API
Route::get('/consent-applications/check', [ConsentApplicationController::class, 'checkForInstrument']);

// Instrument Types API Routes
Route::get('/instrument-types', [InstrumentTypeController::class, 'getAll']);

// File mortgages lookup (used by PRA form when recording Surrender/Release)
Route::get('/file-mortgages', [App\Http\Controllers\FileMortgageController::class, 'index']);

// Route for fetching sub-final-bill details
Route::get('/sub-final-bill/show/{id}', [App\Http\Controllers\SubFinalBillController::class, 'show']);

// Route for fetching application details
Route::get('/application-details/{fileId}/{fileType}', [App\Http\Controllers\ProgrammesController::class, 'getApplicationDetails']);

// Route for fetching shared utilities data
Route::get('/shared-utilities/{application_id}/{sub_application_id?}', function (Request $request, $application_id, $sub_application_id = null) {
    try {
        $query = DB::connection('sqlsrv')
            ->table('shared_utilities')
            ->where('application_id', $application_id)
            ->select([
                'id',
                'application_id',
                'sub_application_id',
                'utility_type',
                'dimension',
                'count',
                'order',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at'
            ]);

        // If sub_application_id is provided, filter by it
        if ($sub_application_id) {
            $query->where('sub_application_id', $sub_application_id);
        }

        $sharedUtilities = $query->orderBy('order')->get();

        return response()->json([
            'success' => true,
            'data' => $sharedUtilities,
            'count' => $sharedUtilities->count(),
            'application_id' => $application_id,
            'sub_application_id' => $sub_application_id
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving shared utilities data',
            'error' => $e->getMessage()
        ], 500);
    }
})->name('api.shared-utilities');

// Route for fetching existing MLS file numbers for extensions
Route::get('/get-existing-mls-files', function (Request $request) {
    try {
        // Query the fileNumber table to get existing MLS file numbers
        $mlsFiles = DB::connection('sqlsrv')
            ->table('dbo.fileNumber')
            ->whereNotNull('mlsfNo')
            ->where('mlsfNo', '!=', '')
            ->select('id', 'mlsfNo')
            ->orderBy('id', 'desc')
            ->limit(500) // Limit to prevent overwhelming the dropdown
            ->get();

        if ($mlsFiles->count() > 0) {
            return response()->json([
                'success' => true,
                'files' => $mlsFiles->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'mlsFNo' => $file->mlsfNo,
                        'file_number' => $file->mlsfNo
                    ];
                })
            ]);
        } else {
            return response()->json([
                'success' => true,
                'files' => [],
                'message' => 'No existing MLS file numbers found'
            ]);
        }
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving MLS file numbers',
            'error' => $e->getMessage()
        ], 500);
    }
})->name('api.get-existing-mls-files');

// Route for searching file numbers for property records
Route::post('/search-file-numbers', [App\Http\Controllers\PropertyRecordController::class, 'searchFileNumbers']);

// Test route to debug file number search
Route::get('/test-file-numbers', function () {
    return response()->json([
        'success' => true,
        'message' => 'API endpoint is working',
        'sample_data' => [
            [
                'id' => 'test_1',
                'fileno' => 'TEST-001',
                'description' => 'Test File Number 1',
                'plot_no' => '123',
                'lga' => 'Test LGA',
                'location' => 'Test Location',
                'source' => 'test'
            ],
            [
                'id' => 'test_2',
                'fileno' => 'TEST-002',
                'description' => 'Test File Number 2',
                'plot_no' => '456',
                'lga' => 'Test LGA 2',
                'location' => 'Test Location 2',
                'source' => 'test'
            ]
        ]
    ]);
});

// File Tracking API Routes - KLAES Lands Module File Tracker
use App\Http\Controllers\FileTrackingController;

// Main file tracking endpoints
Route::get('/file-trackings', [FileTrackingController::class, 'index']);
Route::get('/file-trackings/lookup', [FileTrackingController::class, 'lookup']);
Route::get('/file-trackings/assignment/options', [FileTrackingController::class, 'assignmentOptions']);
Route::post('/file-trackings', [FileTrackingController::class, 'store']);
Route::get('/file-trackings/{id}', [FileTrackingController::class, 'show']);
Route::put('/file-trackings/{id}', [FileTrackingController::class, 'update']);
Route::delete('/file-trackings/{id}', [FileTrackingController::class, 'destroy']);

// Assignment workflow
Route::post('/file-trackings/{id}/assign', [FileTrackingController::class, 'assign']);
Route::post('/file-trackings/{id}/accept', [FileTrackingController::class, 'acceptAssignment']);
Route::post('/file-trackings/{id}/reject', [FileTrackingController::class, 'rejectAssignment']);

// Movement tracking
Route::post('/file-trackings/{id}/move', [FileTrackingController::class, 'addMovement']);

// RFID integration endpoints
Route::post('/rfid/register', [FileTrackingController::class, 'registerRfid']);
Route::get('/rfid/scan/{tag}', [FileTrackingController::class, 'scanRfid']);
Route::get('/rfid/report', [FileTrackingController::class, 'generateReport']);

// Batch operations
Route::post('/file-trackings/batch/overdue', [FileTrackingController::class, 'batchUpdateOverdue']);
Route::get('/fileindexing/batch/{batch_id}/file-ids', [\App\Http\Controllers\FileIndexController::class, 'getBatchFileIds']);

// File Number API Routes for Global Modal
Route::get('/test-api', function () {
    return response()->json(['success' => true, 'message' => 'API is working']);
});

// Centralised File Number API (controller-based)
Route::prefix('file-numbers')->controller(FileNumberApiController::class)->group(function () {
    Route::get('/', 'index')->name('api.file-numbers.index');
    Route::post('/', 'store')->name('api.file-numbers.store');
    Route::get('/lookup', 'lookup')->name('api.file-numbers.lookup');
    Route::get('/tracking/{trackingId}', 'showByTracking')->name('api.file-numbers.show-tracking');

    // Legacy file number systems
    Route::get('/mls', 'mls');
    Route::get('/kangis', 'kangis');
    Route::get('/newkangis', 'newKangis');

    // New ST file number system
    Route::get('/st-all', 'getAllSTFileNumbers');
    Route::get('/st-stats', 'getSTFileNumberStats');
    Route::get('/st-dropdown-data', 'getSTDropdownData');
});

Route::prefix('reference')->controller(ReferenceDataController::class)->group(function () {
    Route::get('/states', 'states')->name('api.reference.states');
    Route::get('/lgas', 'lgas')->name('api.reference.lgas');
    Route::get('/districts', 'districts')->name('api.reference.districts');
    Route::get('/streets', 'streets')->name('api.reference.streets');
    Route::get('/land-uses', 'landUses')->name('api.reference.land-uses');
    Route::get('/purposes', 'purposes')->name('api.reference.purposes');
});

// Serial Status API Route
Route::get('/serial-status', function () {
    try {
        $serials = DB::connection('sqlsrv')
            ->table('land_use_serials')
            ->where('year', date('Y'))
            ->orderBy('land_use_type')
            ->get();

        return response()->json([
            'success' => true,
            'serials' => $serials
        ]);
    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
});

// Dashboard Statistics API Routes
Route::prefix('dashboard')->controller(\App\Http\Controllers\Api\DashboardController::class)->group(function () {
    Route::get('/total-applications', 'getTotalApplications');
    Route::get('/pending-approvals', 'getPendingApprovals');
    Route::get('/indexed-files', 'getIndexedFiles');
    Route::get('/blind-scans', 'getBlindScans');
    Route::get('/scan-uploads', 'getScanUploads');
});

// Grouping Analytics Fast API Routes (sample-based stats)
Route::prefix('grouping-analytics')->controller(GroupingAnalyticsController::class)->group(function () {
    Route::get('/stats', 'stats')->name('api.grouping-analytics.stats');
    Route::get('/preview', 'preview')->name('api.grouping-analytics.preview');
    Route::get('/search', 'search')->name('api.grouping-analytics.search');
    Route::get('/debug', 'debug')->name('api.grouping-analytics.debug');
    Route::delete('/cache', 'clearCache')->name('api.grouping-analytics.clear-cache');
});

// Grouping API Routes - Comprehensive Global API with PERFORMANCE OPTIMIZATIONS
Route::prefix('grouping')->controller(\App\Http\Controllers\Api\GroupingApiController::class)->group(function () {
    // Statistics and totals
    Route::get('/totals', 'totals')->name('api.grouping.totals');
    Route::get('/fast-stats', 'fastStats')->name('api.grouping.fast-stats'); // NEW: Optimized stats
    Route::get('/land-use-types', 'landUseTypes')->name('api.grouping.land-use-types');
    Route::get('/available-years', 'availableYears')->name('api.grouping.available-years');

    // Search and filtering
    Route::get('/search', 'search')->name('api.grouping.search');
    Route::get('/land-use/{landuse}', 'byLandUse')->name('api.grouping.by-land-use');
    Route::get('/awaiting/{fileno}', 'findByAwaitingFileno')->name('api.grouping.awaiting');
    Route::post('/link-mls', 'linkAwaitingToMls')->name('api.grouping.link-mls');
    Route::post('/bulk-lookup', 'bulkLookup')->name('api.grouping.bulk-lookup'); // NEW: Bulk operations

    // Standard REST operations
    Route::get('/', 'index')->name('api.grouping.index');
    Route::post('/', 'store')->name('api.grouping.store');
    Route::get('/{id}', 'show')->name('api.grouping.show');
    Route::put('/{id}', 'update')->name('api.grouping.update');
    Route::delete('/{id}', 'destroy')->name('api.grouping.destroy');
});

// Activity Log API Routes
Route::middleware('auth:sanctum')->prefix('activity-logs')->controller(\App\Http\Controllers\ActivityLogController::class)->group(function () {
    Route::get('/', 'index')->name('api.activity-logs.index');
    Route::get('/{id}', 'show')->name('api.activity-logs.show');
    Route::delete('/{id}', 'destroy')->name('api.activity-logs.destroy');
    Route::post('/bulk-delete', 'bulkDelete')->name('api.activity-logs.bulk-delete');
    Route::get('/export', 'export')->name('api.activity-logs.export');
    Route::post('/cleanup', 'cleanup')->name('api.activity-logs.cleanup');
    Route::get('/settings', 'settings')->name('api.activity-logs.settings');
    Route::post('/settings', 'settings')->name('api.activity-logs.settings.update');
});

// Activity Log Real-time AJAX Routes
Route::middleware('auth:sanctum')->prefix('activity')->controller(\App\Http\Controllers\ActivityLogDashboardController::class)->group(function () {
    // Real-time statistics
    Route::get('/online-count', 'getOnlineCount')->name('api.activity.online-count');
    Route::get('/online-users', 'getOnlineUsers')->name('api.activity.online-users');
    Route::get('/dashboard-stats', 'getDashboardStats')->name('api.activity.dashboard-stats');
    Route::get('/timeline', 'getTimeline')->name('api.activity.timeline');
    Route::get('/browser-stats', 'getBrowserStats')->name('api.activity.browser-stats');
    Route::get('/platform-stats', 'getPlatformStats')->name('api.activity.platform-stats');
    Route::get('/device-stats', 'getDeviceStats')->name('api.activity.device-stats');

    // Heartbeat
    Route::post('/heartbeat', 'recordHeartbeat')->name('api.activity.heartbeat');
});

// Activity Log Analytics Routes
Route::middleware('auth:sanctum')->prefix('analytics')->controller(\App\Http\Controllers\ActivityLogAnalyticsController::class)->group(function () {
    Route::get('/trends', 'getActivityTrends')->name('api.analytics.trends');
    Route::get('/top-users', 'getTopUsers')->name('api.analytics.top-users');
    Route::get('/session-stats', 'getUserSessionStats')->name('api.analytics.session-stats');
    Route::get('/device-analytics', 'getDeviceAnalytics')->name('api.analytics.device-analytics');
    Route::get('/peak-hours', 'getPeakHours')->name('api.analytics.peak-hours');
});

// File Tracker API Routes - Dedicated for React/Mobile Apps
Route::middleware('auth:sanctum')->prefix('file-trackers')->controller(\App\Http\Controllers\Api\FileTrackerApiController::class)->group(function () {
    // Basic CRUD operations
    Route::get('/', 'index')->name('api.file-trackers.index');
    Route::post('/', 'store')->name('api.file-trackers.store');
    Route::get('/{id}', 'show')->name('api.file-trackers.show');
    Route::put('/{id}', 'update')->name('api.file-trackers.update');
    Route::delete('/{id}', 'destroy')->name('api.file-trackers.destroy');

    // Movement operations
    Route::post('/{id}/movements', 'addMovement')->name('api.file-trackers.add-movement');
    Route::post('/{id}/complete-movement', 'completeMovement')->name('api.file-trackers.complete-movement');
    Route::post('/{id}/accept', [\App\Http\Controllers\CreateFileTrackerController::class, 'acceptAssignment'])->name('api.file-trackers.accept');
    Route::post('/{id}/reject', [\App\Http\Controllers\CreateFileTrackerController::class, 'rejectAssignment'])->name('api.file-trackers.reject');
    Route::put('/{id}/logs/{logId}', 'updateLogEntry')->name('api.file-trackers.update-log-entry');

    // Dashboard and analytics
    Route::get('/dashboard/stats', 'dashboard')->name('api.file-trackers.dashboard');

    // Search and tracking
    Route::get('/search', 'search')->name('api.file-trackers.search');
    Route::get('/track/{identifier}', 'track')->name('api.file-trackers.track');

    // Bulk operations
    Route::post('/bulk', 'bulk')->name('api.file-trackers.bulk');

    // Export functionality
    Route::get('/export', 'export')->name('api.file-trackers.export');
});

// Create File Tracker API Routes - Web-based interface endpoints
Route::middleware('auth:sanctum')->prefix('create-file-tracker')->controller(\App\Http\Controllers\CreateFileTrackerController::class)->group(function () {
    Route::get('/list', 'list')->name('api.create-file-tracker.list');
    Route::get('/search', 'search')->name('api.create-file-tracker.search');
    Route::get('/dashboard', 'dashboard')->name('api.create-file-tracker.dashboard');
});
