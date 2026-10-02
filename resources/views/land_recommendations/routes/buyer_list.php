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
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    
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
