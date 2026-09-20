<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\v1\AuthController;
// use App\Http\Controllers\Api\v1\TenantController;
// use App\Http\Controllers\Api\v1\BranchController;
use App\Http\Controllers\Api\v1\DeviceController;
use App\Http\Controllers\Api\v1\DrugController;
use App\Http\Controllers\Api\v1\CustomerController;
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
use App\Http\Controllers\Api\v1\BranchController;

/*
|--------------------------------------------------------------------------
| API Routes - Version 1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {

    // --- Public / Device Registration & Activation ---
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:auth');

    // Local Setup (Client Side)
    Route::post('/setup/activate', [\App\Http\Controllers\Api\v1\SetupController::class, 'activate'])->middleware('throttle:auth');

    // Cloud Activation (Server Side - For Mock/Dual Purpose)
    Route::post('/activate', [\App\Http\Controllers\Api\v1\ActivationController::class, 'activate'])->middleware('throttle:auth');

    // --- Contextual Routes (Requires Authentication & Tenant/Branch Awareness) ---
    Route::middleware(['auth:sanctum', 'throttle:api', 'licensed'])->group(function () {

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
        Route::apiResource('branches', BranchController::class)->middleware('can:accounts.manage');

        // POS Operations
        Route::apiResource('sales', SaleController::class)->only(['index', 'show', 'store'])->middleware('can:sales.process');
        Route::patch('sale-items/{saleItem}/dosage', [SaleItemController::class, 'updateDosageInstructions'])->middleware('can:sales.process');
        Route::post('sales/{sale}/reverse', [SaleController::class, 'reverse'])->middleware('can:sales.reverse');

        Route::prefix('inventory')->group(function () {
            Route::get('/search', [InventoryController::class, 'search'])->middleware('can:inventory.manage');
            Route::get('/branch/{branch}', [InventoryController::class, 'index'])->middleware('can:inventory.manage');
            Route::post('/', [InventoryController::class, 'store'])->middleware('can:inventory.manage');
            Route::get('/{id}', [InventoryController::class, 'show'])->middleware('can:inventory.manage');
            Route::match(['put', 'patch'], '/{id}', [InventoryController::class, 'update'])->middleware('can:inventory.manage');
            Route::delete('/{id}', [InventoryController::class, 'destroy'])->middleware('can:inventory.manage');
            Route::post('/adjust', [InventoryController::class, 'adjustStock'])->middleware('can:inventory.adjust');
            Route::post('/transfer', [TransferController::class, 'initiateTransfer'])->middleware('can:inventory.transfer');
            Route::get('/expired', [InventoryController::class, 'expired'])->middleware('can:inventory.expired');
            Route::post('/expired/process', [InventoryController::class, 'processExpired'])->middleware('can:inventory.expired');
        });

        // Drugs
        Route::apiResource('drugs', DrugController::class)->middleware('can:drugs.manage');

        // Customers
        Route::apiResource('customers', CustomerController::class)->middleware('can:customers.manage');

        // Devices
        Route::get('devices', [DeviceController::class, 'index'])->middleware('can:devices.manage');
        Route::post('devices/register', [DeviceController::class, 'register'])->middleware('can:devices.manage');
        Route::get('devices/{device}', [DeviceController::class, 'show'])->middleware('can:devices.manage');
        Route::match(['put', 'patch'], 'devices/{device}', [DeviceController::class, 'update'])->middleware('can:devices.manage');

        // Batches
        Route::get('batches', [\App\Http\Controllers\Api\v1\BatchController::class, 'index'])->middleware('can:batches.manage');
        Route::post('batches', [\App\Http\Controllers\Api\v1\BatchController::class, 'store'])->middleware('can:batches.manage'); // Create Batch

        // Settings
        Route::get('settings', [\App\Http\Controllers\Api\v1\SystemSettingController::class, 'index'])->middleware('can:settings.manage');
        Route::post('settings', [\App\Http\Controllers\Api\v1\SystemSettingController::class, 'update'])->middleware('can:settings.manage');

        Route::post('returns', [\App\Http\Controllers\Api\v1\ReturnController::class, 'store'])->middleware('can:returns.manage');

        // Sync & Offline Support
        Route::prefix('sync')->group(function () {
            Route::post('/push', [SyncController::class, 'push']);
            Route::post('/pull', [SyncController::class, 'pull']);
            Route::post('/restore', [SyncController::class, 'restore']);
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

    // Cloud endpoints do not exist in child route caches. Device credentials do
    // not grant cloud operator privileges; they only authenticate this API.
    if (config('sync.role') === 'parent') {
        Route::prefix('sync')->middleware(['sync.role:parent', 'sync.token', 'throttle:sync'])->group(function () {
            Route::post('/receive', [\App\Http\Controllers\Api\v1\CloudSyncController::class, 'receiveBatch']);
            Route::get('/serve', [\App\Http\Controllers\Api\v1\CloudSyncController::class, 'serveBatch']);
            Route::get('/full-restore', [\App\Http\Controllers\Api\v1\CloudSyncController::class, 'fullRestore']);
        });
    }
});
