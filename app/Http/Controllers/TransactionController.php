<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionUpdateRequest;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::query()->orderByDesc('created_at');

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('notes', 'like', "%{$search}%")
                  ->orWhere('subcategory', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $transactions = $query->paginate(50)->withQueryString();

        $categories = Transaction::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('transactions.index', compact('transactions', 'categories'));
    }

    public function show(Transaction $transaction)
    {
        return view('transactions.show', compact('transaction'));
    }

    public function edit(Transaction $transaction)
    {
        $categories = Transaction::select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('transactions.edit', compact('transaction', 'categories'));
    }

    public function update(TransactionUpdateRequest $request, Transaction $transaction)
    {
        $transaction->update($request->validated());

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('status', 'Movimiento actualizado correctamente.');
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return redirect()
            ->route('transactions.index')
            ->with('status', 'Movimiento eliminado correctamente.');
    }
}
