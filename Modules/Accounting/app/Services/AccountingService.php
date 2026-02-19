<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Models\JournalEntry;
use Modules\Accounting\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Exception;

class AccountingService
{
    /**
     * Post a journal entry and update account balances.
     */
    public function postEntry(JournalEntry $entry)
    {
        if ($entry->status !== 'draft') {
            throw new Exception("Entry is already {$entry->status}");
        }

        DB::transaction(function () use ($entry) {
            foreach ($entry->details as $detail) {
                $account = $detail->chartOfAccount;

                // Asset/Expense: Debit increases, Credit decreases
                // Liability/Equity/Revenue: Credit increases, Debit decreases

                $balanceChange = 0;

                if (in_array($account->type, ['Asset', 'Expense'])) {
                    $balanceChange = $detail->debit - $detail->credit;
                } else {
                    $balanceChange = $detail->credit - $detail->debit;
                }

                $account->current_balance += $balanceChange;
                $account->save();

                // Update parent balances recursively
                $this->updateParentBalances($account->parent, $balanceChange);
            }

            $entry->status = 'posted';
            $entry->posted_at = now();
            $entry->save();
        });

        return $entry;
    }

    /**
     * Void a journal entry and reverse balances.
     */
    public function voidEntry(JournalEntry $entry)
    {
        if ($entry->status !== 'posted') {
            throw new Exception("Only posted entries can be voided");
        }

        DB::transaction(function () use ($entry) {
            foreach ($entry->details as $detail) {
                $account = $detail->chartOfAccount;

                $balanceChange = 0;

                // Reverse the effect
                if (in_array($account->type, ['Asset', 'Expense'])) {
                    $balanceChange = -($detail->debit - $detail->credit);
                } else {
                    $balanceChange = -($detail->credit - $detail->debit);
                }

                $account->current_balance += $balanceChange;
                $account->save();

                $this->updateParentBalances($account->parent, $balanceChange);
            }

            $entry->status = 'voided';
            $entry->save();
        });

        return $entry;
    }

    private function updateParentBalances(?ChartOfAccount $parent, float $amount)
    {
        if (!$parent) return;

        $parent->current_balance += $amount;
        $parent->save();

        $this->updateParentBalances($parent->parent, $amount);
    }
}
