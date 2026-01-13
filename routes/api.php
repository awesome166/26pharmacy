<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\TenantController;
use App\Http\Controllers\Api\v1\BranchController;
use App\Http\Controllers\Api\v1\DeviceController;
use App\Http\Controllers\Api\v1\SaleController;
use App\Http\Controllers\Api\v1\InventoryController;
use App\Http\Controllers\Api\v1\TransferController;
use App\Http\Controllers\Api\v1\SyncController;
use App\Http\Controllers\Api\v1\DeviceHealthController;
use App\Http\Controllers\Api\v1\ReportController;
use App\Http\Controllers\Api\v1\AuditController;
use App\Http\Controllers\Api\v1\UserController;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // --- Public / Device Registration ---
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/devices/register', [DeviceController::class, 'register']);

    // --- Contextual Routes (Requires Authentication & Tenant/Branch Awareness) ---
    Route::middleware(['auth:sanctum'])->group(function () {

        // Identity & Trust
        Route::apiResource('tenants', TenantController::class);
        Route::get('tenants/{tenant}/branches', [BranchController::class, 'index']); // Specific for listing by tenant
        Route::apiResource('branches', BranchController::class);
        Route::apiResource('users', UserController::class);
        Route::delete('devices/{device}', [DeviceController::class, 'revoke']);

        // POS Operations
        Route::apiResource('sales', SaleController::class)->only(['index', 'show']);
        Route::post('sales/finalize', [SaleController::class, 'finalizeSale']);
        Route::post('sales/reverse', [SaleController::class, 'reverseSale']);

        Route::prefix('inventory')->group(function () {
            Route::get('/search', [InventoryController::class, 'search']);
            Route::get('/branch/{branch}', [InventoryController::class, 'index']);
            Route::post('/adjust', [InventoryController::class, 'adjustStock']);
            Route::post('/transfer', [TransferController::class, 'initiateTransfer']);
        });

        // Sync & Offline Support
        Route::prefix('sync')->group(function () {
            Route::post('/push', [SyncController::class, 'push']);
            Route::post('/pull', [SyncController::class, 'pull']);
        });

        // Device Health
        Route::post('/devices/heartbeat', [DeviceHealthController::class, 'heartbeat']);

        // Reporting & Compliance
        Route::prefix('reports')->group(function () {
            Route::get('/daily-sales', [ReportController::class, 'dailySales']);
            Route::get('/export-ledger', [ReportController::class, 'exportLedger']);
        });

        Route::get('/audit-trail', [AuditController::class, 'index']);
    });
});
