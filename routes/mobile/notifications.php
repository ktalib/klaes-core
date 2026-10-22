<?php

use App\Http\Controllers\Api\Mobile\MobileNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('notifications',                    [MobileNotificationController::class, 'index']);
    Route::post('notifications/mark-all-read',     [MobileNotificationController::class, 'markAllRead']);
    Route::post('notifications/{id}/read',         [MobileNotificationController::class, 'markRead']);

    // FCM device tokens for push notifications in the KLAES FTM app.
    Route::post('push/register',                   [MobileNotificationController::class, 'registerPush']);
    Route::post('push/unregister',                 [MobileNotificationController::class, 'unregisterPush']);
});
