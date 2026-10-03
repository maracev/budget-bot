@extends('layouts.app')

@section('title', 'Reportes')

@section('content')
    <div class="flex justify-between items-center mb">
        <h1>Reportes</h1>
    </div>

    <div class="card">
        <form method="GET" action="{{ route('reports.index') }}">
            <div class="form-grid">
                <div>
                    <label for="date_from">Fecha desde</label>
                    <input type="date" id="date_from" name="date_from" value="{{ $dateFrom }}">
                </div>
                <div>
                    <label for="date_to">Fecha hasta</label>
                    <input type="date" id="date_to" name="date_to" value="{{ $dateTo }}">
                </div>
            </div>
            <div class="flex gap">
                <button type="submit" class="btn btn-primary">Consultar</button>
                <a href="{{ route('reports.index') }}" class="btn">Hoy/Este mes</a>
            </div>
        </form>
    </div>

    <div class="grid grid-3">
        <div class="stat">
            <div class="stat-label">Ingresos</div>
            <div class="stat-value text-success">{{ number_format($incomeTotal, 0, ',', '.') }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Gastos</div>
            <div class="stat-value text-danger">{{ number_format(abs($expenseTotal), 0, ',', '.') }}</div>
        </div>
        <div class="stat">
            <div class="stat-label">Balance del período</div>
            <div class="stat-value {{ $balance >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($balance, 0, ',', '.') }}</div>
        </div>
    </div>

    @if ($periodCompare)
        <div class="card">
            <h3 class="mb">Comparación de períodos</h3>
            <div class="grid grid-3">
                <div class="stat">
                    <div class="stat-label">Ingresos ({{ $periodCompare['prev_from'] }} a {{ $periodCompare['prev_to'] }})</div>
                    <div class="stat-value text-success">{{ number_format($periodCompare['income_prev'], 0, ',', '.') }}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Gastos (período anterior)</div>
                    <div class="stat-value text-danger">{{ number_format(abs($periodCompare['expense_prev']), 0, ',', '.') }}</div>
                </div>
                <div class="stat">
                    <div class="stat-label">Balance (período anterior)</div>
                    <div class="stat-value {{ $periodCompare['balance_prev'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($periodCompare['balance_prev'], 0, ',', '.') }}</div>
                </div>
            </div>
        </div>
    @endif

    <div class="card table-responsive">
        <h3 class="mb">Gastos por categoría</h3>
        <table>
            <thead>
                <tr>
                    <th>Categoría</th>
                    <th>Transacciones</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($byCategory as $row)
                    <tr>
                        <td>{{ $row->category }}</td>
                        <td>{{ $row->count }}</td>
                        <td class="{{ $row->total >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($row->total, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-muted">Sin datos en el período.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card table-responsive">
        <h3 class="mb">Evolución de gastos por mes (últimos 12 meses)</h3>
        <table>
            <thead>
                <tr>
                    <th>Mes</th>
                    <th>Ingresos</th>
                    <th>Gastos</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($monthlyEvolution as $m)
                    @php
                        $bal = ($m->income ?? 0) + ($m->expense ?? 0);
                    @endphp
                    <tr>
                        <td>{{ $m->month }}</td>
                        <td class="text-success">{{ number_format($m->income ?? 0, 0, ',', '.') }}</td>
                        <td class="text-danger">{{ number_format(abs($m->expense ?? 0), 0, ',', '.') }}</td>
                        <td class="{{ $bal >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($bal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
