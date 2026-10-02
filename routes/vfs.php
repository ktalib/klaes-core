<?php

use App\Http\Controllers\Vfs\VirtualFileExplorerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Virtual Folder System
|--------------------------------------------------------------------------
|
| A logical view over the physical EDMS. Every route here is read-only: the
| explorer assembles virtual folders from mapping data and never moves,
| renames or duplicates a scan.
|
| Registered explicitly in RouteServiceProvider — not required from web.php,
| the way routes/caveat.php and routes/mls_fileno.php are.
|
*/

// Super Admins only. The sidebar entry is gated the same way, but a hidden menu is
// not access control — without this the URL was reachable by any signed-in user.
Route::middleware(['auth', 'XSS', 'super.admin'])->prefix('vfs')->name('vfs.')->group(function () {
    // The register: find an indexed file.
    Route::get('/', [VirtualFileExplorerController::class, 'index'])->name('index');

    // The explorer: one folio's virtual folder.
    Route::get('/folios/{fileIndexing}', [VirtualFileExplorerController::class, 'show'])
        ->name('show')
        ->where('fileIndexing', '[0-9]+');

    Route::get('/folios/{fileIndexing}/documents', [VirtualFileExplorerController::class, 'documents'])
        ->name('documents')
        ->where('fileIndexing', '[0-9]+');

    Route::get('/folios/{fileIndexing}/lineage', [VirtualFileExplorerController::class, 'lineage'])
        ->name('lineage')
        ->where('fileIndexing', '[0-9]+');

    Route::get('/folios/{fileIndexing}/status-timeline', [VirtualFileExplorerController::class, 'statusTimeline'])
        ->name('status-timeline')
        ->where('fileIndexing', '[0-9]+');

    Route::get('/folios/{fileIndexing}/physical-sources', [VirtualFileExplorerController::class, 'physicalSources'])
        ->name('physical-sources')
        ->where('fileIndexing', '[0-9]+');
});
