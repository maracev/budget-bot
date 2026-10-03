@extends('layouts.app')

@section('title', 'Editar movimiento')

@section('content')
    <div class="flex justify-between items-center mb">
        <h1>Editar movimiento</h1>
        <a href="{{ route('transactions.index') }}" class="btn">Volver</a>
    </div>

    @if ($errors->any())
        <div class="alert" style="background:#fff5f5;border:1px solid #fed7d7;color:#c53030;">
            <ul style="padding-left:1.25rem;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('transactions.update', $transaction) }}">
            @csrf
            @method('PUT')
            <div class="form-grid">
                <div>
                    <label for="type">Tipo</label>
                    <select id="type" name="type" required>
                        <option value="income" {{ old('type', $transaction->type) === 'income' ? 'selected' : '' }}>Ingreso</option>
                        <option value="outgo" {{ old('type', $transaction->type) === 'outgo' ? 'selected' : '' }}>Gasto</option>
                    </select>
                </div>
                <div>
                    <label for="amount">Importe</label>
                    <input type="number" id="amount" name="amount" step="1" value="{{ old('amount', $transaction->amount) }}" required>
                </div>
                <div>
                    <label for="category">Categoría</label>
                    <input type="text" id="category" name="category" list="categories" value="{{ old('category', $transaction->category) }}" required>
                    <datalist id="categories">
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}">
                        @endforeach
                    </datalist>
                </div>
                <div>
                    <label for="subcategory">Subcategoría</label>
                    <input type="text" id="subcategory" name="subcategory" value="{{ old('subcategory', $transaction->subcategory) }}">
                </div>
                <div style="grid-column: 1/-1;">
                    <label for="notes">Notas / Descripción</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="500">{{ old('notes', $transaction->notes) }}</textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </form>
    </div>
@endsection
