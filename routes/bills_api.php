<?php

use Illuminate\Support\Facades\Route;

// API Routes for Bills and Application Details
Route::get('/betterment-bill/show/{id}', [App\Http\Controllers\BettermentBillController::class, 'show'])->name('betterment-bill.show');
Route::get('/sub-final-bill/show/{id}', [App\Http\Controllers\SubFinalBillController::class, 'showBill'])->name('sub-final-bill.show');
Route::get('/gisedms/application-details/{fileId}/{fileType}', [App\Http\Controllers\ProgrammesController::class, 'getApplicationDetails'])->name('programmes.application-details');

// Test Routes (can be removed later)
Route::get('/test-betterment/{id}', function($id) {
    return response()->json(['success' => true, 'message' => 'Betterment route working', 'id' => $id]);
});

Route::get('/test-final-bill/{id}', function($id) {
    return response()->json(['success' => true, 'message' => 'Final bill route working', 'id' => $id]);
});

Route::get('/test-app-details/{fileId}/{fileType}', function($fileId, $fileType) {
    return response()->json(['success' => true, 'message' => 'App details route working', 'fileId' => $fileId, 'fileType' => $fileType]);
});