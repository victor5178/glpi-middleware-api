<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('/audit/{auditId}/result/{resultId}', [DashboardController::class, 'show'])
    ->whereNumber('auditId')
    ->whereNumber('resultId')
    ->name('scanned.show');
