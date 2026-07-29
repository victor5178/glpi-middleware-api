<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManualController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/manual', [ManualController::class, 'create'])->name('manual.create');
Route::post('/manual', [ManualController::class, 'store'])->name('manual.store');

Route::get('/audit/{auditId}/result/{resultId}', [DashboardController::class, 'show'])
    ->whereNumber('auditId')
    ->whereNumber('resultId')
    ->name('scanned.show');
