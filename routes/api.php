<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\AuthController;
// use App\Http\Controllers\Api\v1\TenantController;
// use App\Http\Controllers\Api\v1\BranchController;
use App\Http\Controllers\Api\v1\DeviceController;
use App\Http\Controllers\Api\v1\SaleController;
use App\Http\Controllers\Api\v1\SaleItemController;
use App\Http\Controllers\Api\v1\InventoryController;
use App\Http\Controllers\Api\v1\TransferController;
use App\Http\Controllers\Api\v1\SyncController;
use App\Http\Controllers\Api\v1\DeviceHealthController;
use App\Http\Controllers\Api\v1\ReportController;
use App\Http\Controllers\Api\v1\AuditController;
use App\Http\Controllers\Api\v1\UserController;
use App\Http\Controllers\Api\v1\AccountController;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // --- Public / Device Registration & Activation ---
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/devices/register', [DeviceController::class, 'register']);

    // Local Setup (Client Side)
    Route::post('/setup/activate', [\App\Http\Controllers\Api\v1\SetupController::class, 'activate']);

    // Cloud Activation (Server Side - For Mock/Dual Purpose)
    Route::post('/activate', [\App\Http\Controllers\Api\v1\ActivationController::class, 'activate']);

    // --- Contextual Routes (Requires Authentication & Tenant/Branch Awareness) ---
    Route::middleware(['auth:sanctum'])->group(function () {

        // Identity & Trust
        // Route::apiResource('tenants', TenantController::class);
        // Route::get('tenants/{tenant}/branches', [BranchController::class, 'index']); // Specific for listing by tenant
        // Route::apiResource('branches', BranchController::class);
        // Route::apiResource('users', UserController::class)->names('api.users');
        // Route::apiResource('accounts', AccountController::class)->names('api.accounts');
        // Route::get('accounts/{account}/users', [AccountController::class, 'getUsers'])->name('api.accounts.users.index');
        // Route::post('accounts/{account}/users', [AccountController::class, 'attachUser'])->name('api.accounts.users.store');
        // Route::delete('accounts/{account}/users/{user}', [AccountController::class, 'detachUser'])->name('api.accounts.users.destroy');
        // Route::apiResource('roles', \App\Http\Controllers\Api\v1\RoleController::class)->names('api.roles');
        // Route::get('permissions', [\App\Http\Controllers\Api\v1\PermissionController::class, 'index']);
        Route::delete('devices/{device}', [DeviceController::class, 'revoke']);

        // POS Operations
        Route::apiResource('sales', SaleController::class)->only(['index', 'show', 'store'])->middleware('can:sales.process');
        Route::patch('sale-items/{saleItem}/dosage', [SaleItemController::class, 'updateDosageInstructions'])->middleware('can:sales.process');
        Route::post('sales/finalize', [SaleController::class, 'finalizeSale'])->middleware('can:sales.process');
        Route::post('sales/reverse', [SaleController::class, 'reverseSale'])->middleware('can:sales.reverse');

        Route::prefix('inventory')->group(function () {
            Route::get('/search', [InventoryController::class, 'search'])->middleware('can:inventory.manage');
            Route::get('/branch/{branch}', [InventoryController::class, 'index'])->middleware('can:inventory.manage');
            Route::post('/adjust', [InventoryController::class, 'adjustStock'])->middleware('can:inventory.adjust');
            Route::post('/transfer', [TransferController::class, 'initiateTransfer'])->middleware('can:inventory.transfer');
            Route::get('/expired', [InventoryController::class, 'expired'])->middleware('can:inventory.expired');
            Route::post('/expired/process', [InventoryController::class, 'processExpired'])->middleware('can:inventory.expired');
        });

        // Batches
        Route::get('batches', [\App\Http\Controllers\Api\v1\BatchController::class, 'index'])->middleware('can:batches.manage');
        Route::post('batches', [\App\Http\Controllers\Api\v1\BatchController::class, 'store'])->middleware('can:batches.manage'); // Create Batch

        // Settings
        Route::get('settings', [\App\Http\Controllers\Api\v1\SystemSettingController::class, 'index'])->middleware('can:settings.manage');
        Route::post('settings', [\App\Http\Controllers\Api\v1\SystemSettingController::class, 'update'])->middleware('can:settings.manage');

        Route::post('returns', [\App\Http\Controllers\Api\v1\ReturnController::class, 'store'])->middleware('can:returns.manage');

        // Sync & Offline Support
        Route::prefix('sync')->group(function () {
            Route::post('/push', [SyncController::class, 'push']); // Trigger Local Push
            Route::post('/pull', [SyncController::class, 'pull']); // Trigger Local Pull

            // Cloud-side Endpoints (For completeness if this app acts as cloud)
            Route::post('/receive', [\App\Http\Controllers\Api\v1\CloudSyncController::class, 'receiveBatch']);
            Route::get('/serve', [\App\Http\Controllers\Api\v1\CloudSyncController::class, 'serveBatch']);
        });

        // Device Health
        Route::post('/devices/heartbeat', [DeviceHealthController::class, 'heartbeat']);

        // Reporting & Compliance
        Route::prefix('reports')->group(function () {
            Route::get('/daily-sales', [ReportController::class, 'dailySales'])->middleware('can:reports.view');
            Route::get('/export-ledger', [ReportController::class, 'exportLedger'])->middleware('can:reports.export');
        });

        Route::get('/audit-trail', [AuditController::class, 'index'])->middleware('can:audit_trail.view');
    });
});
