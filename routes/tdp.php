<?php

use App\Http\Controllers\Gis\TitleDeedPlanController;
use Illuminate\Support\Facades\Route;

/*
| KANGIS → Title Deed Plan Management: the TDP store on the GIS server
| (config/tdp.php → TDP_ROOT_PATH), one folder per Kano LGA.
|
| The plans here are the BACK PAGE of the ST Certificate of Occupancy; the
| front page is produced in KLAES/ST at programmes.certificates.
|
| Access is checked in the controller (tdp.roles: view / manage; Supper Admin
| always passes).
*/
Route::middleware(['auth'])->prefix('gis/title-deed-plans')->name('tdp.')->group(function () {
    Route::get('/', [TitleDeedPlanController::class, 'index'])->name('index');
    Route::get('/reconciliation', [TitleDeedPlanController::class, 'reconciliation'])->name('reconciliation');
    // Plans are streamed through the app; the filesystem path is never exposed.
    Route::get('/file', [TitleDeedPlanController::class, 'file'])->name('file');
    Route::post('/', [TitleDeedPlanController::class, 'store'])->name('store');
});
