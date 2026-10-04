<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportIndexRequest;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(ReportIndexRequest $request)
    {
        $filters = $request->validated();

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->endOfMonth()->toDateString();

        $baseQuery = Transaction::query()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        $totals = (clone $baseQuery)
            ->selectRaw("
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income_total,
                SUM(CASE WHEN type = 'outgo' THEN amount ELSE 0 END) as expense_total
            ")
            ->first();

        $incomeTotal = (int) ($totals->income_total ?? 0);
        $expenseTotal = (int) ($totals->expense_total ?? 0);
        $balance = $incomeTotal + $expenseTotal;

        $byCategory = (clone $baseQuery)
            ->where('type', 'outgo')
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc(DB::raw('SUM(amount)'))
            ->get();

        $monthlyEvolution = Transaction::query()
            ->whereDate('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->whereDate('created_at', '<=', now()->endOfMonth())
            ->selectRaw("{$this->monthExpression()} as month, SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income, SUM(CASE WHEN type='outgo' THEN amount ELSE 0 END) as expense")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $periodCompare = $this->compareWithPreviousPeriod($dateFrom, $dateTo);

        return view('reports.index', compact(
            'dateFrom',
            'dateTo',
            'incomeTotal',
            'expenseTotal',
            'balance',
            'byCategory',
            'monthlyEvolution',
            'periodCompare'
        ));
    }

    /**
     * Totals of the period immediately preceding the one being reported.
     *
     * @return array{prev_from: string, prev_to: string, income_prev: int, expense_prev: int, balance_prev: int}
     */
    private function compareWithPreviousPeriod(string $dateFrom, string $dateTo): array
    {
        $from = Carbon::parse($dateFrom);
        $diffDays = $from->diffInDays(Carbon::parse($dateTo)) + 1;
        $prevFrom = $from->copy()->subDays($diffDays)->toDateString();
        $prevTo = $from->copy()->subDay()->toDateString();

        $prev = Transaction::query()
            ->whereDate('created_at', '>=', $prevFrom)
            ->whereDate('created_at', '<=', $prevTo)
            ->selectRaw("
                SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income_total,
                SUM(CASE WHEN type = 'outgo' THEN amount ELSE 0 END) as expense_total
            ")
            ->first();

        $incomePrev = (int) ($prev->income_total ?? 0);
        $expensePrev = (int) ($prev->expense_total ?? 0);

        return [
            'prev_from' => $prevFrom,
            'prev_to' => $prevTo,
            'income_prev' => $incomePrev,
            'expense_prev' => $expensePrev,
            'balance_prev' => $incomePrev + $expensePrev,
        ];
    }

    /**
     * Year-month grouping is spelled differently per driver, and the test
     * suite runs on SQLite while production uses MySQL.
     */
    private function monthExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', created_at)",
            default => "DATE_FORMAT(created_at, '%Y-%m')",
        };
    }
}
