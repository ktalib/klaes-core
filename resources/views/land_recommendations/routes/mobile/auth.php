<?php

use App\Http\Controllers\Api\Mobile\MobileAuthController;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [MobileAuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [MobileAuthController::class, 'logout']);
});
