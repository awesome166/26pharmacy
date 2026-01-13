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

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';

// --- Pharmacy POS Web/Inertia Routes ---
Route::middleware(['auth', 'verified'])->prefix('app')->group(function () {

    // Identity & Trust
    Route::resource('tenants', TenantController::class);
    Route::resource('branches', BranchController::class);
    Route::resource('users', UserController::class);

    // Sales & Inventory
    Route::resource('sales', SaleController::class)->only(['index', 'show']);
    Route::resource('inventory', InventoryController::class)->only(['index']);
    Route::get('inventory/search', [InventoryController::class, 'search'])->name('inventory.search');

    // Reports & Compliance
    Route::get('reports/daily-sales', [ReportController::class, 'dailySales'])->name('reports.daily-sales');
    Route::get('reports/export', [ReportController::class, 'exportLedger'])->name('reports.export');
    Route::get('audit-trail', [AuditController::class, 'index'])->name('audit.index');
});
