Route::get('/primary-application', [App\Http\Controllers\PrimaryFormDraftController::class, 'index'])->name('primary.application.form');
Route::post('/primary-application', [App\Http\Controllers\PrimaryApplicationController::class, 'store'])->name('primary.application.store');
