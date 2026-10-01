@extends('layouts.app')

@section('title', 'Movimientos')

@section('content')
    <div class="flex justify-between items-center mb">
        <h1>Movimientos</h1>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('transactions.index') }}">
            <div class="form-grid">
                <div>
                    <label for="date_from">Fecha desde</label>
                    <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}">
                </div>
                <div>
                    <label for="date_to">Fecha hasta</label>
                    <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}">
                </div>
                <div>
                    <label for="type">Tipo</label>
                    <select id="type" name="type">
                        <option value="">Todos</option>
                        <option value="income" {{ request('type') === 'income' ? 'selected' : '' }}>Ingreso</option>
                        <option value="outgo" {{ request('type') === 'outgo' ? 'selected' : '' }}>Gasto</option>
                    </select>
                </div>
                <div>
                    <label for="category">Categoría</label>
                    <select id="category" name="category">
                        <option value="">Todas</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="grid-column: 1/-1;">
                    <label for="search">Buscar (descripción/categoría)</label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Ej: supermercado, metrogas...">
                </div>
            </div>
            <div class="flex gap">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="{{ route('transactions.index') }}" class="btn">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="card table-responsive">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Categoría</th>
                    <th>Subcategoría</th>
                    <th>Importe</th>
                    <th>Notas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $t)
                    <tr>
                        <td>{{ $t->created_at->format('d/m/Y') }}</td>
                        <td>{{ $t->type === 'income' ? 'Ingreso' : 'Gasto' }}</td>
                        <td>{{ $t->category }}</td>
                        <td>{{ $t->subcategory ?? '-' }}</td>
                        <td class="{{ $t->amount >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($t->amount, 0, ',', '.') }}</td>
                        <td class="text-muted">{{ Str::limit($t->notes ?? '', 30) }}</td>
                        <td>
                            <div class="flex gap">
                                <a href="{{ route('transactions.show', $t) }}" class="btn btn-sm">Ver</a>
                                <a href="{{ route('transactions.edit', $t) }}" class="btn btn-sm">Editar</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-muted">No hay movimientos para los filtros aplicados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        <div style="margin-top:1rem;">
            {{ $transactions->links() }}
        </div>
    </div>
@endsection
