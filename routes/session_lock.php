<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionLockController;

/*
|--------------------------------------------------------------------------
| Session Lock Routes
|--------------------------------------------------------------------------
|
| Routes for handling session lock functionality
|
*/

Route::middleware(['auth'])->prefix('session-lock')->name('sessionlock.')->group(function () {
    Route::post('/check', [SessionLockController::class, 'checkSession'])->name('check');
    Route::post('/update-activity', [SessionLockController::class, 'updateActivity'])->name('update-activity');
    Route::post('/unlock', [SessionLockController::class, 'unlock'])->name('unlock');
    Route::post('/force-logout', [SessionLockController::class, 'forceLogout'])->name('force-logout');
});

// Test route for session lock demo (remove in production)
Route::get('/test-session-lock', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }
    
    return view('test-session-lock');
})->middleware(['auth'])->name('test.session.lock');