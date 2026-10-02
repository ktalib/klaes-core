<?php

/**
 * Buyer List Routes
 * 
 * Standalone routes for buyer list CRUD operations
 * Separated from conveyance routes for better organization
 * 
 * Route Naming Convention: buyer.*
 */

use App\Http\Controllers\BuyerListController;
use App\Http\Controllers\BuyerListDiagnosticsController;
use App\Http\Controllers\BuyerListDraftController;
use Illuminate\Support\Facades\Route;

// The buyers form's own account of the session — rows added/removed, submits,
// script errors, unloads. Outside the auth group on purpose: a dropped session is
// the leading suspect for the form emptying itself, and an authenticated-only
// endpoint would bounce the one beacon that could prove it. See
// BuyerListDiagnosticsController for what it will and will not accept.
Route::post('/buyer/client-log', [BuyerListDiagnosticsController::class, 'clientLog'])
    ->name('buyer.client.log');

Route::middleware(['auth'])->group(function () {

    // Autosaved capture, one draft per file number so a return visit updates the
    // draft that is there rather than adding another copy.
    Route::post('/buyer/draft/save', [BuyerListDraftController::class, 'save'])
        ->name('buyer.draft.save');

    Route::get('/buyer/draft/{applicationId}', [BuyerListDraftController::class, 'show'])
        ->name('buyer.draft.show');

    Route::post('/buyer/draft/close', [BuyerListDraftController::class, 'close'])
        ->name('buyer.draft.close');


    // Get buyers list for an application
    Route::get('/buyer/list/{applicationId}', [BuyerListController::class, 'getBuyersList'])
        ->name('buyer.list');
    
    // Add buyers manually (form submission)
    Route::post('/buyer/add', [BuyerListController::class, 'addBuyers'])
        ->name('buyer.update'); // Keep 'update' for backward compatibility
    
    // Import buyers from CSV
    Route::post('/buyer/import-csv', [BuyerListController::class, 'importCsv'])
        ->name('buyer.import.csv');
    
    // Update a single buyer
    Route::post('/buyer/update-single', [BuyerListController::class, 'updateBuyer'])
        ->name('buyer.update.single');
    
    // Delete a buyer
    Route::post('/buyer/delete', [BuyerListController::class, 'deleteBuyer'])
        ->name('buyer.delete');
    
    // Download CSV template
    Route::get('/buyer/template/download', [BuyerListController::class, 'downloadTemplate'])
        ->name('buyer.template.download');
});
