<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\Api\v1\TenantController;
use App\Http\Controllers\Api\v1\BranchController;
use App\Http\Controllers\Api\v1\UserController;
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



// --- Pharmacy POS Web/Inertia Routes ---
Route::middleware(['auth'])->prefix('app')->group(function () {

    // Identity & Trust
    Route::resource('tenants', TenantController::class);
    Route::resource('branches', BranchController::class);
    Route::resource('users', UserController::class);

    Route::resource('drugs', DrugController::class);

    // Sales & Inventory
    Route::resource('sales', SaleController::class)->only(['index', 'show', 'store']);
    Route::get('store', [StoreController::class, 'index'])->name('store.index');
    Route::resource('inventory', InventoryController::class)->only(['index']);
    // Route::resource('inventory', InventoryController::class)->only(['index']);
    Route::get('inventory/search', [InventoryController::class, 'search'])->name('inventory.search');
    Route::post('inventory/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust');
    Route::post('inventory/transfer', [\App\Http\Controllers\Api\v1\TransferController::class, 'initiateTransfer'])->name('inventory.transfer');

    // Reports & Compliance
    Route::get('reports/daily-sales', [ReportController::class, 'dailySales'])->name('reports.daily-sales');
    Route::get('reports/export', [ReportController::class, 'exportLedger'])->name('reports.export');
    Route::get('audit-trail', [AuditController::class, 'index'])->name('audit.index');

    // Sync Operations
    Route::post('sync/push', [SyncController::class, 'push'])->name('sync.push');
    Route::post('sync/pull', [SyncController::class, 'pull'])->name('sync.pull');
    Route::post('sync/receive', [CloudSyncController::class, 'receiveBatch'])->name('sync.receive');
    Route::get('sync/serve', [CloudSyncController::class, 'serveBatch'])->name('sync.serve');
});
