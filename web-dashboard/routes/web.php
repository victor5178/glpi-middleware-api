<?php

use App\Http\Controllers\AccessController;
use App\Http\Controllers\AssetReviewController;
use App\Http\Controllers\AuditTrailController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiscrepancyController;
use App\Http\Controllers\FormsController;
use App\Http\Controllers\ManualController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

// --- Auth (GLPI login) ---
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// --- Protected app ---
Route::middleware('auth.glpi')->group(function () {

    // Same-origin image proxy (avoids http/https mixed-content blocking). Open to
    // any authenticated user — used by both audit and forms images.
    Route::get('/media/{path}', [MediaController::class, 'upload'])
        ->where('path', 'uploads/.*')->name('media');

    // --- Audit asset records (RBAC: audit_records) ---
    Route::middleware('perm:audit_records,view')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/asset-review', [AssetReviewController::class, 'index'])->name('asset-review');
        Route::get('/audit-trail', [AuditTrailController::class, 'index'])->name('audit-trail');
        Route::get('/report', [ReportController::class, 'index'])->name('report');
        Route::get('/discrepancy', [DiscrepancyController::class, 'index'])->name('discrepancy');

        Route::get('/audit/{auditId}/result/{resultId}', [DashboardController::class, 'show'])
            ->whereNumber('auditId')->whereNumber('resultId')->name('scanned.show');
    });

    // Create / scan new audit records (RBAC: audit_records execute).
    Route::middleware('perm:audit_records,execute')->group(function () {
        Route::get('/manual', [ManualController::class, 'create'])->name('manual.create');
        Route::post('/manual', [ManualController::class, 'store'])->name('manual.store');
        Route::get('/manual/check-duplicate', [ManualController::class, 'checkDuplicate'])->name('manual.check');
        Route::get('/glpi/add-asset', [ManualController::class, 'addForm'])->name('glpi.add');
        Route::post('/glpi/add-asset', [ManualController::class, 'addStore'])->name('glpi.add.store');
        Route::get('/scan', [ManualController::class, 'scan'])->name('scan');
    });

    // Edit an existing audit record (RBAC: audit_records edit).
    Route::middleware('perm:audit_records,edit')->group(function () {
        Route::get('/audit/{auditId}/result/{resultId}/edit', [DashboardController::class, 'edit'])
            ->whereNumber('auditId')->whereNumber('resultId')->name('scanned.edit');
        Route::put('/audit/{auditId}/result/{resultId}', [DashboardController::class, 'update'])
            ->whereNumber('auditId')->whereNumber('resultId')->name('scanned.update');
    });

    // Delete an audit record (RBAC: audit_records delete).
    Route::delete('/audit/{auditId}/result/{resultId}', [DashboardController::class, 'destroy'])
        ->whereNumber('auditId')->whereNumber('resultId')
        ->middleware('perm:audit_records,delete')->name('scanned.destroy');

    // --- Forms OCR tracking (RBAC: forms) ---
    Route::get('/forms', [FormsController::class, 'index'])->middleware('perm:forms,view')->name('forms.index');
    Route::get('/forms/create', [FormsController::class, 'create'])->middleware('perm:forms,execute')->name('forms.create');
    Route::post('/forms', [FormsController::class, 'store'])->middleware('perm:forms,execute')->name('forms.store');
    Route::get('/forms/{id}', [FormsController::class, 'show'])->whereNumber('id')->middleware('perm:forms,view')->name('forms.show');
    Route::put('/forms/{id}', [FormsController::class, 'update'])->whereNumber('id')->middleware('perm:forms,edit')->name('forms.update');
    Route::post('/forms/{id}/reprocess', [FormsController::class, 'reprocess'])->whereNumber('id')->middleware('perm:forms,edit')->name('forms.reprocess');
    Route::delete('/forms/{id}', [FormsController::class, 'destroy'])->whereNumber('id')->middleware('perm:forms,delete')->name('forms.destroy');

    // --- User Access administration (Administrator only) ---
    Route::middleware('perm:admin')->group(function () {
        Route::get('/access', [AccessController::class, 'index'])->name('access.index');
        Route::post('/access/role', [AccessController::class, 'saveRole'])->name('access.role.save');
        Route::delete('/access/role', [AccessController::class, 'deleteRole'])->name('access.role.delete');
        Route::post('/access/assign', [AccessController::class, 'assignUser'])->name('access.assign');
        Route::delete('/access/assign', [AccessController::class, 'removeUser'])->name('access.remove');
    });
});
