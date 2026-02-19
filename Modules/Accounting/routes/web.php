<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;
use Modules\Accounting\Http\Controllers\JournalEntryController;
use Modules\Accounting\Http\Controllers\AccountsPayableController;
use Modules\Accounting\Http\Controllers\BudgetController;
use Modules\Accounting\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth', 'verified'])->prefix('accounting')->name('accounting.')->group(function () {

    // Dashboard (Placeholer)
    Route::get('/', function () {
        return Inertia\Inertia::render('Accounting/Dashboard');
    })->name('dashboard');

    // Chart of Accounts
    Route::resource('accounts', ChartOfAccountController::class);

    // Journal Entry Management
    Route::resource('journal-entries', JournalEntryController::class);
    Route::post('journal-entries/{id}/post', [JournalEntryController::class, 'post'])->name('journal-entries.post');
    Route::post('journal-entries/{id}/void', [JournalEntryController::class, 'void'])->name('journal-entries.void');

    // Accounts Payable
    // Note: AccountsPayableController needs update to support Inertia similar to others
    // For now mapping resource, assumption is controller will be updated or API used via Vue
    Route::resource('accounts-payable', AccountsPayableController::class);

    // Budgets
    Route::resource('budgets', BudgetController::class);

    // Reports
    Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('reports/income-statement', [ReportController::class, 'incomeStatement'])->name('reports.income-statement');
});
