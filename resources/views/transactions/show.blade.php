@extends('layouts.app')

@section('title', 'Detalle de movimiento')

@section('content')
    <div class="flex justify-between items-center mb">
        <h1>Detalle del movimiento</h1>
        <div class="flex gap">
            <a href="{{ route('transactions.index') }}" class="btn">Volver</a>
            <a href="{{ route('transactions.edit', $transaction) }}" class="btn btn-primary">Editar</a>
        </div>
    </div>

    <div class="card">
        <dl>
            <div style="margin-bottom:1rem;">
                <dt class="text-muted">Fecha</dt>
                <dd>{{ $transaction->created_at->format('d/m/Y H:i') }}</dd>
            </div>
            <div style="margin-bottom:1rem;">
                <dt class="text-muted">Tipo</dt>
                <dd>{{ $transaction->type === 'income' ? 'Ingreso' : 'Gasto' }}</dd>
            </div>
            <div style="margin-bottom:1rem;">
                <dt class="text-muted">Categoría</dt>
                <dd>{{ $transaction->category }}</dd>
            </div>
            <div style="margin-bottom:1rem;">
                <dt class="text-muted">Subcategoría</dt>
                <dd>{{ $transaction->subcategory ?? '-' }}</dd>
            </div>
            <div style="margin-bottom:1rem;">
                <dt class="text-muted">Importe</dt>
                <dd class="{{ $transaction->amount >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($transaction->amount, 0, ',', '.') }}</dd>
            </div>
            <div>
                <dt class="text-muted">Notas</dt>
                <dd>{{ $transaction->notes ?? '-' }}</dd>
            </div>
        </dl>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('transactions.destroy', $transaction) }}" onsubmit="return confirm('¿Eliminar este movimiento? Esta acción no se puede deshacer.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Eliminar movimiento</button>
        </form>
    </div>
@endsection
