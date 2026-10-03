@if ($paginator->hasPages())
    <nav class="flex items-center justify-between" aria-label="Paginación">
        <span class="text-muted">
            Mostrando {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
            de {{ $paginator->total() }}
        </span>

        <div class="flex gap">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm" aria-disabled="true">Anterior</span>
            @else
                <a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente</a>
            @else
                <span class="btn btn-sm" aria-disabled="true">Siguiente</span>
            @endif
        </div>
    </nav>
@endif