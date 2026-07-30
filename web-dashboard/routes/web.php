<?php

use App\Http\Controllers\AssetReviewController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManualController;
use Illuminate\Support\Facades\Route;

// --- Auth (GLPI login) ---
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- Protected app ---
Route::middleware('auth.glpi')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/manual', [ManualController::class, 'create'])->name('manual.create');
    Route::post('/manual', [ManualController::class, 'store'])->name('manual.store');
    Route::get('/manual/check-duplicate', [ManualController::class, 'checkDuplicate'])->name('manual.check');

    Route::get('/scan', [ManualController::class, 'scan'])->name('scan');

    Route::get('/asset-review', [AssetReviewController::class, 'index'])->name('asset-review');

    Route::get('/audit/{auditId}/result/{resultId}', [DashboardController::class, 'show'])
        ->whereNumber('auditId')
        ->whereNumber('resultId')
        ->name('scanned.show');

    Route::get('/audit/{auditId}/result/{resultId}/edit', [DashboardController::class, 'edit'])
        ->whereNumber('auditId')
        ->whereNumber('resultId')
        ->name('scanned.edit');

    Route::put('/audit/{auditId}/result/{resultId}', [DashboardController::class, 'update'])
        ->whereNumber('auditId')
        ->whereNumber('resultId')
        ->name('scanned.update');

    Route::delete('/audit/{auditId}/result/{resultId}', [DashboardController::class, 'destroy'])
        ->whereNumber('auditId')
        ->whereNumber('resultId')
        ->name('scanned.destroy');

    Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail');
});
