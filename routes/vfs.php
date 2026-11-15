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

// The register (finding any indexed file) stays Super Admins only. The sidebar
// entry is gated the same way, but a hidden menu is not access control.
Route::middleware(['auth', 'XSS', 'super.admin'])->prefix('vfs')->name('vfs.')->group(function () {
    Route::get('/', [VirtualFileExplorerController::class, 'index'])->name('index');
});

// One folio, read-only, for any signed-in user (Kunle, 2026-11-14: "Open Folio" on
// the action menus and every "View Scanned File" opens the VFS). They reach it from
// a record they can already see on a table.
Route::middleware(['auth', 'XSS'])->prefix('vfs')->name('vfs.')->group(function () {
    // A file number from a table row (FC, Cadastral) to its folio.
    Route::get('/open', [VirtualFileExplorerController::class, 'open'])->name('open');

    // The explorer: one folio's virtual folder.
    Route::get('/folios/{fileIndexing}', [VirtualFileExplorerController::class, 'show'])
        ->name('show')
        ->where('fileIndexing', '[0-9]+');

    Route::get('/folios/{fileIndexing}/documents', [VirtualFileExplorerController::class, 'documents'])
        ->name('documents')
        ->where('fileIndexing', '[0-9]+');

    // Commissioning sheet / LGA confirmation sheet, drawn read-only (CR 22102026 #32).
    Route::get('/folios/{fileIndexing}/system/{key}', [VirtualFileExplorerController::class, 'systemDocument'])
        ->name('system-document')
        ->where(['fileIndexing' => '[0-9]+', 'key' => 'commissioning-sheet|lga-confirmation']);

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
