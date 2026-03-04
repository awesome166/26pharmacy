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
use App\Http\Controllers\Api\v1\CustomerController;
use App\Http\Controllers\Accounting\AccountingDashboardController;
use App\Http\Controllers\Accounting\ChartOfAccountController;
use App\Http\Controllers\Accounting\JournalEntryController;
use App\Http\Controllers\Accounting\AccountingReportController;
use App\Http\Controllers\Accounting\BookClosingController;
use App\Http\Controllers\Accounting\CashMovementController;


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

Route::middleware(['auth', 'accounting.enabled'])->prefix('accounting')->group(function () {
    Route::get('/', [AccountingDashboardController::class, 'index'])->name('accounting.dashboard')->middleware('can:accounting.dashboard.view');

    Route::get('/accounts', [ChartOfAccountController::class, 'index'])->name('accounting.accounts.index')->middleware('can:accounting.accounts.manage');
    Route::post('/accounts', [ChartOfAccountController::class, 'store'])->name('accounting.accounts.store')->middleware('can:accounting.accounts.manage');
    Route::put('/accounts/{account}', [ChartOfAccountController::class, 'update'])->name('accounting.accounts.update')->middleware('can:accounting.accounts.manage');
    Route::delete('/accounts/{account}', [ChartOfAccountController::class, 'destroy'])->name('accounting.accounts.destroy')->middleware('can:accounting.accounts.manage');

    Route::get('/journal-entries', [JournalEntryController::class, 'index'])->name('accounting.journal-entries.index')->middleware('can:accounting.journal.manage');
    Route::post('/journal-entries', [JournalEntryController::class, 'store'])->name('accounting.journal-entries.store')->middleware('can:accounting.journal.manage');
    Route::post('/journal-entries/{entry}/post', [JournalEntryController::class, 'post'])->name('accounting.journal-entries.post')->middleware('can:accounting.journal.manage');
    Route::post('/journal-entries/{entry}/void', [JournalEntryController::class, 'void'])->name('accounting.journal-entries.void')->middleware('can:accounting.journal.manage');
    Route::post('/cash-movements', [CashMovementController::class, 'store'])->name('accounting.cash-movements.store')->middleware('can:accounting.cash.manage');

    Route::get('/reports/balance-sheet', [AccountingReportController::class, 'balanceSheet'])->name('accounting.reports.balance-sheet')->middleware('can:accounting.reports.view');
    Route::get('/reports/income-statement', [AccountingReportController::class, 'incomeStatement'])->name('accounting.reports.income-statement')->middleware('can:accounting.reports.view');
    Route::get('/reports/trial-balance', [AccountingReportController::class, 'trialBalance'])->name('accounting.reports.trial-balance')->middleware('can:accounting.reports.view');

    Route::post('/close-books', [BookClosingController::class, 'close'])->name('accounting.close-books')->middleware('can:accounting.close.manage');
});

// --- Pharmacy POS Web/Inertia Routes ---
Route::middleware(['auth'])->prefix('app')->group(function () {

    // Dashboard Analytics
    Route::get('dashboard/stats', [\App\Http\Controllers\Api\v1\DashboardController::class, 'stats'])->name('dashboard.stats')->middleware('can:dashboard.view');
    Route::get('dashboard/sales-trend', [\App\Http\Controllers\Api\v1\DashboardController::class, 'salesTrend'])->name('dashboard.sales-trend')->middleware('can:dashboard.view');
    Route::get('dashboard/top-products', [\App\Http\Controllers\Api\v1\DashboardController::class, 'topProducts'])->name('dashboard.top-products')->middleware('can:dashboard.view');
    Route::get('dashboard/payment-breakdown', [\App\Http\Controllers\Api\v1\DashboardController::class, 'paymentBreakdown'])->name('dashboard.payment-breakdown')->middleware('can:dashboard.view');

    // Identity & Trust
    // Route::resource('tenants', TenantController::class);
    // Route::resource('branches', BranchController::class);
    Route::resource('users', UserController::class)->middleware('can:users.manage');
    Route::resource('roles', RoleController::class)->middleware('can:roles.manage');
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index')->middleware('can:roles.manage');

    // Account User Management Routes
    Route::get('accounts/{account}/users', [AccountController::class, 'getUsers'])->name('accounts.users.index')->middleware('can:accounts.manage');
    Route::post('accounts/{account}/users', [AccountController::class, 'attachUser'])->name('accounts.users.store')->middleware('can:accounts.manage');
    Route::delete('accounts/{account}/users/{user}', [AccountController::class, 'detachUser'])->name('accounts.users.destroy')->middleware('can:accounts.manage');

    Route::resource('accounts', AccountController::class)->middleware('can:accounts.manage');

    Route::resource('drugs', DrugController::class)->middleware('can:drugs.manage');
    Route::resource('batches', BatchController::class)->middleware('can:batches.manage');

    // Sales & Inventory
    Route::resource('sales', SaleController::class)->only(['index', 'show', 'store'])->middleware('can:sales.process');
    Route::resource('customers', CustomerController::class)->only(['index', 'update', 'destroy'])->middleware('can:customers.manage');
    Route::get('returns', [\App\Http\Controllers\Api\v1\ReturnController::class, 'index'])->name('returns.index')->middleware('can:returns.manage');
    Route::post('returns', [\App\Http\Controllers\Api\v1\ReturnController::class, 'store'])->name('returns.store')->middleware('can:returns.manage');
    Route::post('returns/{returnItem}/restock', [\App\Http\Controllers\Api\v1\ReturnController::class, 'restock'])->name('returns.restock')->middleware('can:returns.restock');
    Route::patch('sale-items/{saleItem}/dosage', [\App\Http\Controllers\Api\v1\SaleItemController::class, 'updateDosageInstructions'])->name('sale-items.dosage.update')->middleware('can:sales.process');
    Route::get('store', [StoreController::class, 'index'])->name('store.index')->middleware('can:pos.access');
    Route::get('customers/by-phone', [StoreController::class, 'findCustomerByPhone'])->name('customers.by-phone')->middleware('can:pos.access');
    Route::resource('inventory', InventoryController::class)->only(['index', 'store'])->middleware('can:inventory.manage');
    Route::get('inventory/search', [InventoryController::class, 'search'])->name('inventory.search')->middleware('can:inventory.manage');
    Route::post('inventory/adjust', [InventoryController::class, 'adjustStock'])->name('inventory.adjust')->middleware('can:inventory.adjust');
    Route::post('inventory/transfer', [\App\Http\Controllers\Api\v1\TransferController::class, 'initiateTransfer'])->name('inventory.transfer')->middleware('can:inventory.transfer');
    Route::get('inventory/expired', [InventoryController::class, 'expired'])->name('inventory.expired')->middleware('can:inventory.expired');
    Route::post('inventory/expired/process', [InventoryController::class, 'processExpired'])->name('inventory.expired.process')->middleware('can:inventory.expired');

    // Reports & Compliance
    Route::get('reports/daily-sales', [ReportController::class, 'dailySales'])->name('reports.daily-sales')->middleware('can:reports.view');
    Route::get('reports/export', [ReportController::class, 'exportLedger'])->name('reports.export')->middleware('can:reports.export');
    Route::get('audit-trail', [AuditController::class, 'index'])->name('audit.index')->middleware('can:audit_trail.view');

    // Sync Operations
    Route::post('sync/push', [SyncController::class, 'push'])->name('sync.push');
    Route::post('sync/pull', [SyncController::class, 'pull'])->name('sync.pull');
    Route::post('sync/receive', [CloudSyncController::class, 'receiveBatch'])->name('sync.receive');
    Route::post('sync/receive', [CloudSyncController::class, 'receiveBatch'])->name('sync.receive');
    Route::get('sync/serve', [CloudSyncController::class, 'serveBatch'])->name('sync.serve');

    // Configuration Routes
    Route::get('config', [SystemSettingController::class, 'index'])->name('config.index')->middleware('can:settings.manage');
    Route::post('config', [SystemSettingController::class, 'store'])->name('config.store')->middleware('can:settings.manage');
    Route::patch('config', [SystemSettingController::class, 'update'])->name('config.update')->middleware('can:settings.manage');
    Route::resource('taxes', \App\Http\Controllers\Api\v1\TaxRateController::class)->middleware('can:taxes.manage');

    // Online Devices
    Route::get('settings/online-devices', function () {
        return Inertia::render('Settings/OnlineDevices');
    })->name('settings.online-devices')->middleware('can:devices.manage');
});
