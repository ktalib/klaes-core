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

    // Add entry
    Route::post('/', [AllocationListEntryController::class, 'store'])->name('store');

    // Update entry
    Route::put('/{id}', [AllocationListEntryController::class, 'update'])->name('update');

    // Delete entry
    Route::delete('/{id}', [AllocationListEntryController::class, 'destroy'])->name('destroy');

    // CSV import (max 100 records)
    Route::post('/import-csv', [AllocationListEntryController::class, 'importCsv'])->name('import.csv');

    // Download CSV template
    Route::get('/template', [AllocationListEntryController::class, 'downloadTemplate'])->name('template');

    // Get unallocated entries for MLS Generator
    Route::get('/unallocated-entries', [AllocationListEntryController::class, 'getUnallocatedEntries'])->name('unallocated');
});
