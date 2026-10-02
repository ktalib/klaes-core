<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileDecommissioningController;

// File Decommissioning Routes
Route::group(['middleware' => ['auth', 'XSS'], 'prefix' => 'file-decommissioning'], function () {
    // Main decommissioning page
    Route::get('/', [FileDecommissioningController::class, 'index'])->name('file-decommissioning.index');
    
    // Decommissioned files list page
    Route::get('/decommissioned', [FileDecommissioningController::class, 'decommissionedIndex'])->name('file-decommissioning.decommissioned');
    
    // Data endpoints for DataTables
    Route::get('/active-files-data', [FileDecommissioningController::class, 'getActiveFilesData'])->name('file-decommissioning.active-files-data');
    Route::get('/decommissioned-files-data', [FileDecommissioningController::class, 'getDecommissionedFilesData'])->name('file-decommissioning.decommissioned-files-data');
    
    // File operations
    Route::get('/file-details/{id}', [FileDecommissioningController::class, 'getFileDetails'])->name('file-decommissioning.file-details');
    Route::get('/decommissioned-details/{id}', [FileDecommissioningController::class, 'getDecommissionedFileDetails'])->name('file-decommissioning.decommissioned-details');
    Route::post('/decommission', [FileDecommissioningController::class, 'decommissionFile'])->name('file-decommissioning.decommission');
    
    // Search and statistics
    Route::get('/search', [FileDecommissioningController::class, 'searchFiles'])->name('file-decommissioning.search');
    Route::get('/statistics', [FileDecommissioningController::class, 'getStatistics'])->name('file-decommissioning.statistics');
});