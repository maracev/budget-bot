<?php

namespace App\Services;

use App\Models\MonthlyClosure;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class MonthlyClosureService
{
    public function closeMonth(int $month, int $year): MonthlyClosure
    {
        $existing = MonthlyClosure::where('month', $month)->where('year', $year)->first();

        if ($existing) {
            Log::info('Monthly closure already exists', ['' => $month, 'year' => $year]);

            return $existing;
        }

        $incomes = Transaction::where('type', 'income')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('amount');

        $outgoes = Transaction::where('type', 'outgo')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year)
            ->sum('amount');

        $balance = $incomes + $outgoes;

        return MonthlyClosure::create([
            'month' => $month,
            'year' => $year,
            'income' => $incomes,
            'outgo' => abs($outgoes),
            'balance' => $balance,
        ]);
    }
}
