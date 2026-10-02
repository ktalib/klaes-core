<?php

/**
 * Allocation List Entry Routes
 *
 * CRUD operations for the allocation_list_stage table.
 * Route naming convention: allocation-list.*
 */

use App\Http\Controllers\AllocationListEntryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('allocation-list')->name('allocation-list.')->group(function () {

    // Index / main view
    Route::get('/', [AllocationListEntryController::class, 'index'])->name('index');

    // All entries as JSON (DataTable source)
    Route::get('/data', [AllocationListEntryController::class, 'getAllEntries'])->name('data');

    // Single row info for edit modal
    Route::get('/fetchrowinfo/{id}', [AllocationListEntryController::class, 'fetchrowinfo'])->name('fetchrowinfo');

    // Dashboard headline numbers and breakdowns
    Route::get('/stats', [AllocationListEntryController::class, 'stats'])->name('stats');

    // Resolve file title / location / year for a selected file number
    Route::get('/file-lookup', [AllocationListEntryController::class, 'lookupFile'])->name('file-lookup');

    // Capture one existing allocation (file number based capture form)
    Route::post('/existing', [AllocationListEntryController::class, 'storeExisting'])->name('store-existing');

    // Add entry
    Route::post('/', [AllocationListEntryController::class, 'store'])->name('store');

    // Update entry
    Route::put('/{id}', [AllocationListEntryController::class, 'update'])->name('update');

    // Delete entry
    Route::delete('/{id}', [AllocationListEntryController::class, 'destroy'])->name('destroy');

    // CSV import (max 100 records)
    Route::post('/import-csv', [AllocationListEntryController::class, 'importCsv'])->name('import.csv');

    // Report export: filtered rows for the register preview / CSV / PDF
    Route::get('/export', [AllocationListEntryController::class, 'exportData'])->name('export');

    // Distinct years / LGAs / districts that populate the export filters
    Route::get('/export-filters', [AllocationListEntryController::class, 'exportFilters'])->name('export.filters');

    // Download CSV template
    Route::get('/template', [AllocationListEntryController::class, 'downloadTemplate'])->name('template');

    // Get unallocated entries for MLS Generator
    Route::get('/unallocated-entries', [AllocationListEntryController::class, 'getUnallocatedEntries'])->name('unallocated');
});
