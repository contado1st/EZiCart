@php $paginator->appends(request()->except($paginator->getPageName())); @endphp
@if ($paginator->total() > 0)
    <nav class="workspace-page-nav" aria-label="Pagination">
        <p class="workspace-page-summary">@if ($paginator->count()){{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }} records @else No records on this page. {{ number_format($paginator->total()) }} records in total. @endif</p>
        @if ($paginator->hasPages())
            <div class="workspace-page-links">
                @if ($paginator->onFirstPage())
                    <span class="workspace-page-step" aria-disabled="true">Previous</span>
                @else
                    <a class="workspace-page-step" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
                @endif
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span aria-hidden="true">{{ $element }}</span>
                    @else
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="is-current" aria-current="page" aria-label="Page {{ $page }}">{{ $page }}</span>
                            @else
                                <a href="{{ $paginator->url($page) }}" aria-label="Go to page {{ $page }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
                @if ($paginator->hasMorePages())
                    <a class="workspace-page-step" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
                @else
                    <span class="workspace-page-step" aria-disabled="true">Next</span>
                @endif
            </div>
        @endif
    </nav>
@endif
