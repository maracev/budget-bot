<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionIndexRequest;
use App\Http\Requests\TransactionUpdateRequest;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;

class TransactionController extends Controller
{
    public function index(TransactionIndexRequest $request)
    {
        $transactions = $this->filteredQuery($request)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('transactions.index', [
            'transactions' => $transactions,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function show(Transaction $transaction)
    {
        return view('transactions.show', compact('transaction'));
    }

    public function edit(Transaction $transaction)
    {
        return view('transactions.edit', [
            'transaction' => $transaction,
            'categories' => $this->categoryOptions(),
        ]);
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

    private function filteredQuery(TransactionIndexRequest $request): Builder
    {
        $filters = $request->validated();

        return Transaction::query()
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('notes', 'like', "%{$search}%")
                        ->orWhere('subcategory', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            });
    }

    private function categoryOptions()
    {
        return Transaction::query()
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }
}