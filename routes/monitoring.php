<?php

use App\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

Route::middleware('monitoring.token')->group(function (): void {
    Route::get('/ready', [MonitoringController::class, 'ready'])->name('monitoring.ready');
    Route::get('/ops', [MonitoringController::class, 'ops'])->name('monitoring.ops');
});
