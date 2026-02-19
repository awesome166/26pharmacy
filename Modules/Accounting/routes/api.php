<?php

use Illuminate\Support\Facades\Route;
use Modules\Accounting\Http\Controllers\ChartOfAccountController;
use Modules\Accounting\Http\Controllers\JournalEntryController;
use Modules\Accounting\Http\Controllers\AccountsPayableController;
use Modules\Accounting\Http\Controllers\BudgetController;
use Modules\Accounting\Http\Controllers\BankController;
use Modules\Accounting\Http\Controllers\ReportController;
use Modules\Accounting\Http\Controllers\CostCenterController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware(['auth:sanctum'])->prefix('accounting')->group(function () {

    // Chart of Accounts
    Route::apiResource('accounts', ChartOfAccountController::class);

    // Journal Entries
    Route::post('journal-entries/{id}/post', [JournalEntryController::class, 'post']);
    Route::post('journal-entries/{id}/void', [JournalEntryController::class, 'void']);
    Route::apiResource('journal-entries', JournalEntryController::class);

    // Accounts Payable
    Route::post('accounts-payable/{id}/payment', [AccountsPayableController::class, 'addPayment']);
    Route::apiResource('accounts-payable', AccountsPayableController::class);

    // Budgets
    Route::post('budgets/{id}/details', [BudgetController::class, 'addDetail']);
    Route::apiResource('budgets', BudgetController::class);

    // Banks
    Route::get('banks/{id}/transactions', [BankController::class, 'transactions']);
    Route::apiResource('banks', BankController::class);

    // Cost Centers
    Route::apiResource('cost-centers', CostCenterController::class);

    // Reports
    Route::get('reports/balance-sheet', [ReportController::class, 'balanceSheet']);
    Route::get('reports/income-statement', [ReportController::class, 'incomeStatement']);
    Route::get('reports/trial-balance', [ReportController::class, 'trialBalance']);
});
