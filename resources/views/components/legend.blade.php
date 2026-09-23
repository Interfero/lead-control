{{-- Легенда страницы: иконка-кнопка + всплывающая карточка (не сдвигает вёрстку) --}}
@props(['items' => []])

@if (!empty($items))
    <div class="relative shrink-0" data-page-legend>
        <x-ui.button
            type="button"
            variant="outline"
            size="icon"
            class="border-border font-semibold text-muted-foreground hover:text-foreground"
            data-legend-toggle
            aria-expanded="false"
            aria-haspopup="true"
            title="Легенда страницы"
        >
            i
        </x-ui.button>
        <div
            data-legend-panel
            class="hidden absolute left-0 top-full z-[200] mt-2 w-[min(22rem,calc(100vw-2rem))]"
            role="dialog"
            aria-label="Легенда страницы"
        >
            <x-ui.card padding="md" shadow="lg" class="max-h-[min(70vh,28rem)] overflow-y-auto">
                <div class="text-sm font-semibold text-foreground mb-3">Легенда страницы</div>
                <div class="flex flex-col gap-3">
                    @foreach ($items as $item)
                        @php
                            $lc = $item['legend_class'] ?? '';
                        @endphp
                        <div
                            class="flex flex-wrap items-center gap-2 text-xs @if (str_contains($lc, 'footnote')) w-full justify-end text-right text-muted-foreground @elseif (str_contains($lc, 'blink')) w-full @endif"
                        >
                            @if (!empty($item['indicator']))
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full bg-destructive animate-pulse"
                                    aria-hidden="true"
                                ></span>
                            @endif
                            @if (isset($item['badge']))
                                @php
                                    $badgeCode = $item['badge'];
                                    $orderStatuses = \App\Models\Order::allStatusCodes();
                                    $useOrderStatusStyle = in_array($badgeCode, $orderStatuses, true);
                                @endphp
                                @if ($useOrderStatusStyle)
                                    <span class="status-badge status-{{ $badgeCode }} shrink-0 rounded-md px-2 py-0.5 text-xs font-medium">
                                        {{ $item['label'] ?? $badgeCode }}
                                    </span>
                                @else
                                    <span class="badge badge-{{ $badgeCode }} shrink-0 rounded-md px-2 py-0.5 text-xs font-medium">
                                        {{ $item['label'] ?? $badgeCode }}
                                    </span>
                                @endif
                                @if (!empty($item['text']) && ($item['text'] !== ($item['label'] ?? '')))
                                    <span class="text-muted-foreground">{{ $item['text'] }}</span>
                                @endif
                            @elseif (!empty($item['warning_swatch']))
                                <span
                                    class="legend-warning-swatch h-5 w-5 shrink-0 rounded border border-border"
                                    aria-hidden="true"
                                ></span>
                                <span class="text-foreground">{{ $item['text'] ?? '' }}</span>
                            @elseif (!empty($item['color']))
                                <span
                                    class="h-5 w-5 shrink-0 rounded border border-border"
                                    style="background: {{ $item['color'] }};"
                                    aria-hidden="true"
                                ></span>
                                <span class="text-foreground">{{ $item['text'] ?? '' }}</span>
                            @elseif (!empty($item['text']))
                                <span class="text-foreground">{{ $item['text'] }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>
    </div>

    @once
        @push('scripts')
            <script>
                (function () {
                    function closeAllLegendPanels() {
                        document.querySelectorAll('[data-legend-panel]').forEach(function (panel) {
                            panel.classList.add('hidden');
                        });
                        document.querySelectorAll('[data-legend-toggle]').forEach(function (btn) {
                            btn.setAttribute('aria-expanded', 'false');
                        });
                    }

                    document.addEventListener('click', function (e) {
                        var toggle = e.target.closest('[data-legend-toggle]');
                        var root = e.target.closest('[data-page-legend]');
                        if (toggle && root) {
                            e.preventDefault();
                            e.stopPropagation();
                            var panel = root.querySelector('[data-legend-panel]');
                            if (!panel) return;
                            var willOpen = panel.classList.contains('hidden');
                            closeAllLegendPanels();
                            if (willOpen) {
                                panel.classList.remove('hidden');
                                toggle.setAttribute('aria-expanded', 'true');
                            }
                            return;
                        }
                        if (!e.target.closest('[data-page-legend]')) {
                            closeAllLegendPanels();
                        }
                    });

                    document.addEventListener('keydown', function (e) {
                        if (e.key === 'Escape') {
                            closeAllLegendPanels();
                        }
                    });
                })();
            </script>
        @endpush
    @endonce

@endif
