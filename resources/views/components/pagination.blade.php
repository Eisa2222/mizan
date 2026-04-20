@props(['paginator'])

@if ($paginator->hasPages())
    <nav class="mz-pagination" role="navigation" aria-label="صفحات النتائج">
        <div class="mz-pagination-info">
            عرض <strong>{{ $paginator->firstItem() }}</strong>
            إلى <strong>{{ $paginator->lastItem() }}</strong>
            من أصل <strong>{{ $paginator->total() }}</strong>
        </div>

        <div class="mz-pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="mz-page-link disabled" aria-disabled="true">→ السابق</span>
            @else
                <a class="mz-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">→ السابق</a>
            @endif

            @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="mz-page-link active" aria-current="page">{{ $page }}</span>
                @else
                    <a class="mz-page-link" href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="mz-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">التالي ←</a>
            @else
                <span class="mz-page-link disabled" aria-disabled="true">التالي ←</span>
            @endif
        </div>
    </nav>
@endif
