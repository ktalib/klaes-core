<?php

use App\Http\Controllers\Api\Mobile\MobileFileSearchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum,web')->group(function () {
    // Quick Search & File Location (shared engine with web)
    Route::get('files/search', [MobileFileSearchController::class, 'search']);

    // A requester's own File Search Requests + outcomes (OFS tracking view).
    Route::get('my-file-requests', [MobileFileSearchController::class, 'mine']);

    // File Requests for the SCB Monitor
    Route::get('file-requests', [MobileFileSearchController::class, 'index']);
    Route::get('file-requests/log', [MobileFileSearchController::class, 'log']);
    Route::post('file-requests/{id}/respond', [MobileFileSearchController::class, 'respond'])
        ->whereNumber('id');
    // Revert (undo) an accidental Found / Not-Found response.
    Route::post('file-requests/{id}/revert', [MobileFileSearchController::class, 'revert'])
        ->whereNumber('id');
    Route::delete('file-requests/{id}', [MobileFileSearchController::class, 'destroy'])
        ->whereNumber('id');
});
