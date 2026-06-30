<?php

use App\Http\Controllers\CheckInController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/check-ins', [CheckInController::class, 'store']);
    Route::delete('/check-ins/{checkIn}', [CheckInController::class, 'destroy']);
    Route::get('/notifications', [NotificationController::class, 'index']);
});
