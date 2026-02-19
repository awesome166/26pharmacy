# Accounting Module for Laravel

A comprehensive, production-ready accounting module designed for Hospital Management Systems but adaptable for any enterprise application. Built on `nwidart/laravel-modules`.

## Features
- **Chart of Accounts**: Hierarchical structure with standard accounting types (Asset, Liability, Equity, Revenue, Expense).
- **Double-Entry Journal**: Ensures books are always balanced. Supports posting and voiding workflows.
- **Accounts Payable**: Vendor invoice tracking, aging reports, and payment processing.
- **Budgeting**: Fiscal year budgets with variance analysis against actuals.
- **Bank Reconciliation**: Multi-bank support with transaction matching.
- **Reporting**: Auto-generated Balance Sheet, Income Statement, and Cash Flow.

## Installation & Setup

### 1. Requirements
- Laravel 10+
- `nwidart/laravel-modules` package installed.

### 2. Add Module
Copy the `Accounting` folder into your `Modules/` directory.

### 3. Register Autoloading
In your project's `composer.json`, add the module namespace to `autoload.psr-4`:

```json
"autoload": {
    "psr-4": {
        "App\\": "app/",
        "Modules\\Accounting\\": "Modules/Accounting/app/",
        "Modules\\Accounting\\Database\\Factories\\": "Modules/Accounting/database/factories/",
        "Modules\\Accounting\\Database\\Seeders\\": "Modules/Accounting/database/seeders/"
    }
}
```

Run `composer dump-autoload`.

### 4. Install Dependencies
Enable the module and run migrations:

```bash
php artisan module:enable Accounting
php artisan module:migrate Accounting
```

### 5. Seed Initial Data
Populate the Chart of Accounts, Cost Centers, and sample data:

```bash
php artisan accounting:seed
```

## Usage Guide

### API Endpoints
All routes are prefixed with `/api/accounting/` and guarded by `auth:sanctum`.

#### 1. Managing Accounts
Retrieve the Chart of Accounts:
`GET /api/accounting/accounts`

Create a new account:
`POST /api/accounting/accounts`
```json
{
    "code": "1150",
    "name": "Petty Cash",
    "type": "Asset",
    "parent_id": 2
}
```

#### 2. Posting Transactions
Create a Journal Entry:
`POST /api/accounting/journal-entries`
```json
{
    "date": "2024-02-15",
    "description": "Office Supplies",
    "details": [
        { "chart_of_account_id": 15, "debit": 100, "credit": 0 }, // Expense
        { "chart_of_account_id": 3, "debit": 0, "credit": 100 }   // Cash
    ]
}
```

Post the entry (updates balances):
`POST /api/accounting/journal-entries/{id}/post`

#### 3. Generating Reports
Get Balance Sheet:
`GET /api/accounting/reports/balance-sheet?date=2024-02-15`

Get Income Statement:
`GET /api/accounting/reports/income-statement?start_date=2024-01-01&end_date=2024-01-31`

## Reuse & Extension

### Integrating with other Modules
The module uses **Polymorphic Relationships** for flexibility.
- **Journal Entries**: Can link to any model (Patient, User, InventoryItem) via `reference_type` and `reference_id`.
- **Accounts Payable**: Can link to any vendor entity (Supplier, Doctor) via `vendor_type` and `vendor_id`.

### Customizing Configuration
Publish the configuration file (if any specific config is added later):
`php artisan vendor:publish --provider="Modules\Accounting\Providers\AccountingServiceProvider"`

### Events
- `JournalEntryPosted`: Listen for this event to trigger side effects (e.g., notify Finance Manager).

### Hybrid Responses (JSON vs Inertia)
Controllers are configured to handle both API and Web requests:
- **API Request** (`Accept: application/json`): Returns raw JSON data.
- **Web Request**: Returns `Inertia::render()` with data passed as props to Vue components.

Example `ChartOfAccountController`:
```php
if (request()->wantsJson()) {
    return $accounts;
}
return Inertia::render('Accounting/Accounts/Index', ['accounts' => $accounts]);
```

### Web Routes
Prefix: `/accounting`
Name Prefix: `accounting.`

- `GET /accounting` -> Dashboard
- `GET /accounting/accounts` -> Accounts List
- `GET /accounting/journal-entries` -> Journals List
- `GET /accounting/reports/balance-sheet` -> Balance Sheet View

## permissions
Ensure your Role-Based Access Control (RBAC) system assigns the following permissions to appropriate roles:
- `accounting.view`: View reports and accounts.
- `accounting.create`: Create draft entries.
- `accounting.approve`: Post journal entries and approve budgets.
