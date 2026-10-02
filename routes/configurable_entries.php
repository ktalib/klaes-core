<?php

use App\Http\Controllers\SystemAdmin\ConfigurableEntriesController as Entries;
use Illuminate\Support\Facades\Route;

/*
| System Admin → Configurable Entries: Revenue Items, Bill Formulas, Departments
| & Roles, Deeds Instrument Volume, Valuation → Consent → Registration, Land
| Rates, Logos, Signatories, Cadastral. Access is checked in the controller
| (System Settings role or Super Admin).
|
| The FileNo Prefix & SerialNo and Instrument Registration Pipeline routes below
| are still live but their tabs are withdrawn — see
| ConfigurableEntriesController::HIDDEN_TABS.
*/
Route::middleware(['auth'])->prefix('system-admin/configurable-entries')->name('configurable-entries.')->group(function () {
    Route::get('/', [Entries::class, 'index'])->name('index');

    Route::post('/revenue-items', [Entries::class, 'storeRevenueItem'])->name('revenue.store');
    Route::post('/revenue-items/{item}', [Entries::class, 'updateRevenueItem'])->whereNumber('item')->name('revenue.update');
    Route::post('/fee-mappings', [Entries::class, 'saveFeeMapping'])->name('fee-mappings.save');

    Route::post('/bill-formulas', [Entries::class, 'storeBillingType'])->name('billing-types.store');
    Route::post('/bill-formulas/{type}', [Entries::class, 'saveBillingType'])->whereNumber('type')->name('billing-types.update');
    Route::post('/bill-formulas/{type}/items', [Entries::class, 'saveBillingItem'])->whereNumber('type')->name('billing-items.store');
    Route::post('/bill-formulas/{type}/items/{item}', [Entries::class, 'saveBillingItem'])->whereNumber(['type', 'item'])->name('billing-items.update');

    Route::post('/schedules', [Entries::class, 'storeSchedule'])->name('schedules.store');
    Route::post('/schedules/{schedule}', [Entries::class, 'updateSchedule'])->whereNumber('schedule')->name('schedules.update');
    Route::post('/formats', [Entries::class, 'storeFormat'])->name('formats.store');
    Route::post('/formats/{format}', [Entries::class, 'updateFormat'])->whereNumber('format')->name('formats.update');
    Route::post('/formats/{format}/generate', [Entries::class, 'generateSerials'])->whereNumber('format')->name('formats.generate');

    // Serial Initialization: the starting serial for one counter of one register
    // (land | deeds | survey). One-time — the action refuses a locked counter.
    Route::post('/serials/{register}', [Entries::class, 'initializeSerial'])
        ->whereIn('register', array_keys(Entries::SERIAL_REGISTERS))
        ->name('serials.initialize');

    Route::post('/volumes/{type}', [Entries::class, 'updateVolume'])->whereNumber('type')->name('volumes.update');
    // One register (vault) directly: counters, page limit, Unified Register flag (T5).
    Route::post('/registers/{vault}', [Entries::class, 'updateVault'])->whereNumber('vault')->name('volumes.vault');

    Route::post('/pipeline', [Entries::class, 'savePipeline'])->name('pipeline.save');

    // Valuation -> Consent -> Print -> Registration: how hard each hand-off is
    // enforced. Read back by App\Services\DeedsPipelineStatus::gateMode().
    Route::post('/deeds-workflow', [Entries::class, 'saveDeedsWorkflow'])->name('deeds-workflow.save');

    // Logos: one slot per position on a screen or document (config/alaes_logos.php).
    Route::post('/logos/{slot}', [Entries::class, 'saveLogo'])->name('logos.save');
    Route::post('/logos/{slot}/reset', [Entries::class, 'resetLogo'])->name('logos.reset');


    // Signatories: the office holders printed on issued documents (Commissioner,
    // Governor, and any further ones added). Rows of signing_officers with no
    // user_id -- see App\Models\LandOfficer::scopeDocumentSignatories().
    Route::post('/signatories', [Entries::class, 'saveSignatory'])->name('signatories.store');
    Route::post('/signatories/{signatory}', [Entries::class, 'saveSignatory'])->whereNumber('signatory')->name('signatories.update');
    Route::post('/signatories/{signatory}/delete', [Entries::class, 'deleteSignatory'])->whereNumber('signatory')->name('signatories.delete');

    // Cadastral: the official fee sheet's rates and rules, the S.L.N. No. 3 of 1983
    // area schedule, transport bands, numbering, file prefixes / source registries,
    // and who holds each job post. Read back by App\Services\Cadastral\CadastralSettings.
    // Nothing here deletes: schedule rows, bands and officers are switched off.
    Route::post('/cadastral/settings/{group}', [Entries::class, 'saveCadastralSettings'])
        ->whereIn('group', array_keys(Entries::CADASTRAL_SETTING_GROUPS))
        ->name('cadastral.settings');
    Route::post('/cadastral/defaults/{what}', [Entries::class, 'importCadastralDefaults'])
        ->whereIn('what', ['area-schedule', 'transport-bands'])
        ->name('cadastral.defaults');
    Route::post('/cadastral/area-schedule', [Entries::class, 'saveCadastralScheduleRow'])->name('cadastral.schedule.store');
    Route::post('/cadastral/area-schedule/{row}', [Entries::class, 'saveCadastralScheduleRow'])->whereNumber('row')->name('cadastral.schedule.update');
    Route::post('/cadastral/transport-bands', [Entries::class, 'saveCadastralBand'])->name('cadastral.bands.store');
    Route::post('/cadastral/transport-bands/{band}', [Entries::class, 'saveCadastralBand'])->whereNumber('band')->name('cadastral.bands.update');
    Route::post('/cadastral/officers', [Entries::class, 'saveCadastralOfficer'])->name('cadastral.officers.store');
    Route::post('/cadastral/officers/{officer}', [Entries::class, 'saveCadastralOfficer'])->whereNumber('officer')->name('cadastral.officers.update');

    Route::post('/land-uses', [Entries::class, 'saveLandUse'])->name('land-uses.store');
    Route::post('/land-uses/{landUse}', [Entries::class, 'saveLandUse'])->whereNumber('landUse')->name('land-uses.update');
    Route::post('/districts/{district}/rates', [Entries::class, 'saveDistrictRates'])->whereNumber('district')->name('districts.rates');
    Route::post('/luc-parameters', [Entries::class, 'saveLucParameter'])->name('luc-parameters.store');
    Route::post('/luc-parameters/{parameter}', [Entries::class, 'saveLucParameter'])->whereNumber('parameter')->name('luc-parameters.update');
});
