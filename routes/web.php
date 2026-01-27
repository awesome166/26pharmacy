<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
// use App\Http\Controllers\Api\v1\TenantController;
// use App\Http\Controllers\Api\v1\BranchController;
use App\Http\Controllers\Api\v1\UserController;
use App\Http\Controllers\Api\v1\AccountController;
use App\Http\Controllers\Api\v1\SaleController;
use App\Http\Controllers\Api\v1\InventoryController;
use App\Http\Controllers\Api\v1\ReportController;
use App\Http\Controllers\Api\v1\AuditController;
use App\Http\Controllers\Api\v1\SetupController;
use App\Http\Controllers\Api\v1\ActivationController;
use App\Http\Controllers\Api\v1\SyncController;
use App\Http\Controllers\Api\v1\CloudSyncController;
use App\Http\Controllers\Api\v1\StoreController;
use App\Http\Controllers\Api\v1\DrugController;
use App\Http\Controllers\Api\v1\BatchController;
use App\Http\Controllers\Api\v1\RoleController;
use App\Http\Controllers\Api\v1\PermissionController;
use App\Http\Controllers\Api\v1\SystemSettingController;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';

// --- Public / Setup Routes (Web) ---
Route::prefix('app')->group(function () {
    Route::post('/setup/activate', [SetupController::class, 'activate'])->name('app.setup.activate');
    Route::post('/activate', [ActivationController::class, 'activate'])->name('app.cloud.activate');
});



Route::middleware(['auth'])->group(function () {
    // Route::get('/select-account', [AccountSelectionController::class, 'index'])->name('auth.select-account');
    // Route::post('/select-account', [AccountSelectionController::class, 'store'])->name('auth.select-account.store');
});

// --- Pharmacy POS Web/Inertia Routes ---
Route::middleware(['auth'])->prefix('app')->group(function () {

    // Dashboard Analytics
    Route::get('dashboard/stats', [\App\Http\Controllers\Api\v1\DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('dashboard/sales-trend', [\App\Http\Controllers\Api\v1\DashboardController::class, 'salesTrend'])->name('dashboard.sales-trend');
    Route::get('dashboard/top-products', [\App\Http\Controllers\Api\v1\DashboardController::class, 'topProducts'])->name('dashboard.top-products');
    Route::get('dashboard/payment-breakdown', [\App\Http\Controllers\Api\v1\DashboardController::class, 'paymentBreakdown'])->name('dashboard.payment-breakdown');

    // Identity & Trust
    // Route::resource('tenants', TenantController::class);
    // Route::resource('branches', BranchController::class);
    Route::resource('users', UserController::class);
    Route::resource('roles', RoleController::class);
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');

    // Account User Management Routes
    Route::get('accounts/{account}/users', [AccountController::class, 'getUsers'])->name('accounts.users.index');
    Route::post('accounts/{account}/users', [AccountController::class, 'attachUser'])->name('accounts.users.store');
    Route::delete('accounts/{account}/users/{user}', [AccountController::class, 'detachUser'])->name('accounts.users.destroy');

    Route::resource('accounts', AccountController::class);

    Route::resource('drugs', DrugController::class);
    Route::resource('batches', BatchController::class);

    // Sales & Inventory
    Route::resource('sales', SaleController::class)->only(['index', 'show', 'store']);
    Route::get('returns', [\App\Http\Controllers\Api\v1\ReturnController::class, 'index'])->name('returns.index');
    Route::post('returns', [\App\Http\Controllers\Api\v1\ReturnController::class, 'store'])->name('returns.store');
    Route::post('returns/{returnItem}/restock', [\App\Http\Controllers\Api\v1\ReturnController::class, 'restock'])->name('returns.restock');
    Route::patch('sale-items/{saleItem}/dosage', [\App\Http\Controllers\Api\v1\SaleItemController::class, 'updateDosageInstructions'])->name('sale-items.dosage.update');
    Route::get('store', [StoreController::class, 'index'])->name('store.index');
    Route::resource('inventory', InventoryController::class)->only(['index', 'store']);
    Route::get('inventory/search', [InventoryController::class, 'search'])->name('inventory.search');
    Route::post('inventory/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');
    Route::post('inventory/transfer', [\App\Http\Controllers\Api\v1\TransferController::class, 'initiateTransfer'])->name('inventory.transfer');
    Route::get('inventory/expired', [InventoryController::class, 'expired'])->name('inventory.expired');
    Route::post('inventory/expired/process', [InventoryController::class, 'processExpired'])->name('inventory.expired.process');

    // Reports & Compliance
    Route::get('reports/daily-sales', [ReportController::class, 'dailySales'])->name('reports.daily-sales');
    Route::get('reports/export', [ReportController::class, 'exportLedger'])->name('reports.export');
    Route::get('audit-trail', [AuditController::class, 'index'])->name('audit.index');

    // Sync Operations
    Route::post('sync/push', [SyncController::class, 'push'])->name('sync.push');
    Route::post('sync/pull', [SyncController::class, 'pull'])->name('sync.pull');
    Route::post('sync/receive', [CloudSyncController::class, 'receiveBatch'])->name('sync.receive');
    Route::post('sync/receive', [CloudSyncController::class, 'receiveBatch'])->name('sync.receive');
    Route::get('sync/serve', [CloudSyncController::class, 'serveBatch'])->name('sync.serve');

    // Configuration Routes
    Route::get('config', [SystemSettingController::class, 'index'])->name('config.index');
    Route::post('config', [SystemSettingController::class, 'store'])->name('config.store');
    Route::patch('config', [SystemSettingController::class, 'update'])->name('config.update');
});
