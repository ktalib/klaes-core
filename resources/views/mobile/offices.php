<?php

use App\Http\Controllers\Api\OfficeController;
use Illuminate\Support\Facades\Route;

Route::get('offices', [OfficeController::class, 'index']);
