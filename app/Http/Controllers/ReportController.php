<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo = $request->date_to ?? now()->endOfMonth()->toDateString();

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
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc(DB::raw('SUM(amount)'))
            ->get();

        $monthlyEvolution = Transaction::query()
            ->whereDate('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->whereDate('created_at', '<=', now()->endOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income, SUM(CASE WHEN type='outgo' THEN amount ELSE 0 END) as expense")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $periodCompare = null;
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = \Carbon\Carbon::parse($dateFrom);
            $to = \Carbon\Carbon::parse($dateTo);
            $diffDays = $from->diffInDays($to) + 1;
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

            $periodCompare = [
                'prev_from' => $prevFrom,
                'prev_to' => $prevTo,
                'income_prev' => (int) ($prev->income_total ?? 0),
                'expense_prev' => (int) ($prev->expense_total ?? 0),
                'balance_prev' => ((int) ($prev->income_total ?? 0)) + ((int) ($prev->expense_total ?? 0)),
            ];
        }

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
}
