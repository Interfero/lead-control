@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Пагинация" class="inline-flex">
        <span class="inline-flex rtl:flex-row-reverse rounded-md border border-border shadow-sm">
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" aria-label="Предыдущая страница">
                    <span class="inline-flex cursor-not-allowed items-center rounded-l-md border-r border-border bg-muted px-2 py-2 text-sm text-muted-foreground" aria-hidden="true">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </span>
            @else
                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    rel="prev"
                    class="inline-flex items-center rounded-l-md border-r border-border bg-card px-2 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Предыдущая страница"
                >
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span aria-disabled="true">
                        <span class="-ml-px inline-flex cursor-default items-center border-r border-border bg-card px-4 py-2 text-sm text-muted-foreground">{{ $element }}</span>
                    </span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page">
                                <span class="-ml-px inline-flex cursor-default items-center border-r border-border bg-muted px-4 py-2 text-sm font-medium text-foreground">{{ $page }}</span>
                            </span>
                        @else
                            <a
                                href="{{ $url }}"
                                class="-ml-px inline-flex items-center border-r border-border bg-card px-4 py-2 text-sm text-foreground transition-colors hover:bg-muted"
                                aria-label="Страница {{ $page }}"
                            >{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    rel="next"
                    class="-ml-px inline-flex items-center rounded-r-md bg-card px-2 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Следующая страница"
                >
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            @else
                <span aria-disabled="true" aria-label="Следующая страница">
                    <span class="-ml-px inline-flex cursor-not-allowed items-center rounded-r-md bg-muted px-2 py-2 text-sm text-muted-foreground" aria-hidden="true">
                        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                        </svg>
                    </span>
                </span>
            @endif
        </span>
    </nav>
@else
    <nav role="navigation" aria-label="Пагинация" class="inline-flex">
        <span class="inline-flex rounded-md border border-border shadow-sm">
            <span aria-disabled="true" aria-label="Предыдущая страница">
                <span class="inline-flex cursor-not-allowed items-center rounded-l-md border-r border-border bg-muted px-2 py-2 text-sm text-muted-foreground">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </span>
            </span>
            <span aria-current="page">
                <span class="-ml-px inline-flex cursor-default items-center border-r border-border bg-muted px-4 py-2 text-sm font-medium text-foreground">1</span>
            </span>
            <span aria-disabled="true" aria-label="Следующая страница">
                <span class="-ml-px inline-flex cursor-not-allowed items-center rounded-r-md bg-muted px-2 py-2 text-sm text-muted-foreground">
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </span>
            </span>
        </span>
    </nav>
@endif
