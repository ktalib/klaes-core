<?php

use App\Http\Controllers\Api\Mobile\MobileTrackerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum,web')->group(function () {
    Route::post('tracker/scan-and-log', [MobileTrackerController::class, 'scanAndLog']);
    Route::post('tracker/{id}/send-sms', [MobileTrackerController::class, 'sendSms'])
        ->whereNumber('id');
    Route::get('tracker/{id}/sms-preview', [MobileTrackerController::class, 'smsPreview'])
        ->whereNumber('id');
});
