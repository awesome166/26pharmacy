<?php

namespace Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Accounting\Services\ReportGenerator;

use Inertia\Inertia;

class ReportController extends Controller
{
    protected $reportGenerator;

    public function __construct(ReportGenerator $reportGenerator)
    {
        $this->reportGenerator = $reportGenerator;
    }

    public function balanceSheet(Request $request)
    {
        $data = $this->reportGenerator->generateBalanceSheet($request->date);

        if ($request->wantsJson()) {
            return $data;
        }

        return Inertia::render('Accounting/Reports/BalanceSheet', [
            'reportData' => $data,
            'filters' => $request->all()
        ]);
    }

    public function incomeStatement(Request $request)
    {
        $data = [];
        if ($request->has(['start_date', 'end_date'])) {
             $data = $this->reportGenerator->generateIncomeStatement($request->start_date, $request->end_date);
        }

        if ($request->wantsJson()) {
             $request->validate(['start_date' => 'required|date', 'end_date' => 'required|date']);
             return $data;
        }

        return Inertia::render('Accounting/Reports/IncomeStatement', [
            'reportData' => $data,
            'filters' => $request->all()
        ]);
    }

    public function trialBalance()
    {
        return $this->reportGenerator->generateTrialBalance();
    }
}
