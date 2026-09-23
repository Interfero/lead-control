@extends('layouts.app')

@section('title', 'UI Kit — Lead Control')

@section('content')
    <div class="space-y-10 max-w-6xl">
        <div>
            <h1 class="text-2xl font-bold text-foreground mb-2">Витрина дизайн-системы</h1>
            <p class="text-muted-foreground text-sm mb-4">
                Страница для проверки токенов и компонентов (роль developer). Документация: <code class="text-primary">docs/DESIGN_SYSTEM.md</code>
            </p>
            <div class="flex flex-wrap gap-2">
                <x-ui.button type="button" variant="outline" size="sm" id="ui-kit-theme-toggle">
                    Переключить светлую/тёмную тему
                </x-ui.button>
            </div>
        </div>

        {{-- Палитра (полный набор токенов @theme) --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-1">Семантические цвета</h2>
            <p class="text-xs text-muted-foreground mb-4">Соответствие теме Deep Purple / tweakcn; исключения — <code class="text-primary">docs/DESIGN_SYSTEM.md</code> §8.</p>

            <h3 class="text-sm font-medium text-foreground mb-2">Фон и обводки</h3>
            @php
                $swatchBg = [
                    ['token' => 'background', 'class' => 'bg-background'],
                    ['token' => 'foreground', 'class' => 'bg-foreground'],
                    ['token' => 'card', 'class' => 'bg-card'],
                    ['token' => 'primary', 'class' => 'bg-primary'],
                    ['token' => 'secondary', 'class' => 'bg-secondary'],
                    ['token' => 'muted', 'class' => 'bg-muted'],
                    ['token' => 'accent', 'class' => 'bg-accent'],
                    ['token' => 'destructive', 'class' => 'bg-destructive'],
                    ['token' => 'border', 'class' => 'bg-border'],
                    ['token' => 'input', 'class' => 'bg-input'],
                    ['token' => 'ring', 'class' => 'bg-ring'],
                    ['token' => 'popover', 'class' => 'bg-popover'],
                ];
                $swatchPairs = [
                    ['token' => 'card + card-foreground', 'class' => 'bg-card text-card-foreground'],
                    ['token' => 'popover + popover-foreground', 'class' => 'bg-popover text-popover-foreground'],
                    ['token' => 'primary + primary-foreground', 'class' => 'bg-primary text-primary-foreground'],
                    ['token' => 'secondary + secondary-foreground', 'class' => 'bg-secondary text-secondary-foreground'],
                    ['token' => 'muted + muted-foreground', 'class' => 'bg-muted text-muted-foreground'],
                    ['token' => 'accent + accent-foreground', 'class' => 'bg-accent text-accent-foreground'],
                    ['token' => 'destructive + destructive-foreground', 'class' => 'bg-destructive text-destructive-foreground'],
                ];
                $swatchTextOnPage = [
                    ['token' => 'foreground на фоне страницы', 'class' => 'bg-background text-foreground'],
                ];
                $chartSwatches = [
                    ['token' => 'chart-1', 'class' => 'bg-chart-1'],
                    ['token' => 'chart-2', 'class' => 'bg-chart-2'],
                    ['token' => 'chart-3', 'class' => 'bg-chart-3'],
                    ['token' => 'chart-4', 'class' => 'bg-chart-4'],
                    ['token' => 'chart-5', 'class' => 'bg-chart-5'],
                ];
                $sidebarPairs = [
                    ['token' => 'sidebar + sidebar-foreground', 'class' => 'bg-sidebar text-sidebar-foreground'],
                    ['token' => 'sidebar-primary + sidebar-primary-foreground', 'class' => 'bg-sidebar-primary text-sidebar-primary-foreground'],
                    ['token' => 'sidebar-accent + sidebar-accent-foreground', 'class' => 'bg-sidebar-accent text-sidebar-accent-foreground'],
                ];
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 gap-3">
                @foreach ($swatchBg as $sw)
                    <div class="text-center">
                        <div class="h-14 rounded-md border border-border shadow-sm mb-1 {{ $sw['class'] }}"></div>
                        <div class="text-xs text-muted-foreground break-words">{{ $sw['token'] }}</div>
                    </div>
                @endforeach
            </div>

            <h3 class="text-sm font-medium text-foreground mb-2 mt-6">Текст на типичном фоне</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @foreach ($swatchTextOnPage as $sw)
                    <div class="text-center">
                        <div class="h-14 rounded-md border border-border shadow-sm mb-1 flex items-center justify-center text-sm font-medium {{ $sw['class'] }}">Aa</div>
                        <div class="text-xs text-muted-foreground">{{ $sw['token'] }}</div>
                    </div>
                @endforeach
            </div>

            <h3 class="text-sm font-medium text-foreground mb-2 mt-6">Пары «фон + текст»</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @foreach ($swatchPairs as $sw)
                    <div class="text-center">
                        <div class="h-14 rounded-md border border-border shadow-sm mb-1 flex items-center justify-center text-sm font-medium {{ $sw['class'] }}">Aa</div>
                        <div class="text-xs text-muted-foreground break-words">{{ $sw['token'] }}</div>
                    </div>
                @endforeach
            </div>

            <h3 class="text-sm font-medium text-foreground mb-2 mt-6">chart-1 … chart-5</h3>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                @foreach ($chartSwatches as $sw)
                    <div class="text-center">
                        <div class="h-14 rounded-md border border-border shadow-sm mb-1 {{ $sw['class'] }}"></div>
                        <div class="text-xs text-muted-foreground">{{ $sw['token'] }}</div>
                    </div>
                @endforeach
            </div>

            <h3 class="text-sm font-medium text-foreground mb-2 mt-6">sidebar / навбар</h3>
            <p class="text-xs text-muted-foreground mb-3">Токены <code class="text-primary">--sidebar*</code> в светлой теме заданы под тёмную верхнюю панель (не как белый сайдбар в экспорте tweakcn).</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                @foreach ($sidebarPairs as $sw)
                    <div class="text-center">
                        <div class="h-14 rounded-md border border-border shadow-sm mb-1 flex items-center justify-center text-sm font-medium {{ $sw['class'] }}">Navbar</div>
                        <div class="text-xs text-muted-foreground break-words">{{ $sw['token'] }}</div>
                    </div>
                @endforeach
                <div class="text-center">
                    <div class="h-14 rounded-md mb-1 bg-card border-4 border-sidebar-border"></div>
                    <div class="text-xs text-muted-foreground">sidebar-border</div>
                </div>
                <div class="text-center">
                    <div class="h-14 rounded-md mb-1 bg-card shadow-sm outline outline-2 outline-sidebar-ring outline-offset-2"></div>
                    <div class="text-xs text-muted-foreground">sidebar-ring</div>
                </div>
            </div>

            <h3 class="text-sm font-medium text-foreground mb-2 mt-6">Legacy (не в @theme)</h3>
            <p class="text-xs text-muted-foreground mb-2">Для <code class="text-primary">.alert-*</code> в layout; при необходимости <code class="text-primary">var(--success)</code> и т.д.</p>
            <div class="grid grid-cols-3 gap-3 max-w-md">
                <div class="text-center">
                    <div class="h-14 rounded-md border border-border mb-1" style="background: var(--success)"></div>
                    <div class="text-xs text-muted-foreground">--success</div>
                </div>
                <div class="text-center">
                    <div class="h-14 rounded-md border border-border mb-1" style="background: var(--warning)"></div>
                    <div class="text-xs text-muted-foreground">--warning</div>
                </div>
                <div class="text-center">
                    <div class="h-14 rounded-md border border-border mb-1" style="background: var(--danger)"></div>
                    <div class="text-xs text-muted-foreground">--danger</div>
                </div>
            </div>
        </x-ui.card>

        {{-- Радиусы --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Радиусы</h2>
            <p class="text-sm text-muted-foreground mb-4">База <code class="text-primary">--radius: 1rem</code>; шкала Tailwind v4.</p>
            <div class="flex flex-wrap gap-6 items-end">
                @foreach (['rounded-sm', 'rounded-md', 'rounded-lg', 'rounded-xl'] as $radiusClass)
                    <div class="text-center">
                        <div class="w-16 h-16 bg-card border border-border {{ $radiusClass }} mb-1"></div>
                        <div class="text-xs text-muted-foreground font-mono">{{ $radiusClass }}</div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Тени --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Тени</h2>
            <p class="text-sm text-muted-foreground mb-4">От <code class="text-primary">shadow-2xs</code> до <code class="text-primary">shadow-2xl</code>.</p>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach (['shadow-2xs', 'shadow-xs', 'shadow-sm', 'shadow', 'shadow-md', 'shadow-lg', 'shadow-xl', 'shadow-2xl'] as $shadowClass)
                    <div class="text-center">
                        <div class="h-20 rounded-lg bg-card border border-border {{ $shadowClass }} mb-1 flex items-center justify-center text-xs text-muted-foreground px-1">{{ $shadowClass }}</div>
                    </div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Трекинг --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Межбуквенный интервал (tracking)</h2>
            <p class="text-sm text-muted-foreground mb-4">База <code class="text-primary">--tracking-normal</code> задана на <code class="text-primary">body</code> в <code class="text-primary">app.css</code>.</p>
            <div class="space-y-2 text-foreground text-base">
                <p class="tracking-tighter">tracking-tighter — The quick brown fox</p>
                <p class="tracking-tight">tracking-tight — The quick brown fox</p>
                <p class="tracking-normal">tracking-normal — The quick brown fox</p>
                <p class="tracking-wide">tracking-wide — The quick brown fox</p>
                <p class="tracking-wider">tracking-wider — The quick brown fox</p>
                <p class="tracking-widest">tracking-widest — The quick brown fox</p>
            </div>
        </x-ui.card>

        {{-- Типографика --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Типографика</h2>
            <p class="text-xs text-foreground mb-1">text-xs</p>
            <p class="text-sm text-foreground mb-1">text-sm</p>
            <p class="text-base text-foreground mb-1">text-base</p>
            <p class="text-lg font-semibold text-foreground mb-1">text-lg semibold</p>
            <p class="text-2xl font-bold text-foreground mb-1">text-2xl bold</p>
            <p class="font-mono text-sm text-muted-foreground">font-mono JetBrains Mono</p>
        </x-ui.card>

        {{-- Кнопки --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Кнопки</h2>
            <div class="flex flex-wrap gap-2 items-center">
                <x-ui.button variant="primary" size="sm">Primary sm</x-ui.button>
                <x-ui.button variant="primary" size="md">Primary md</x-ui.button>
                <x-ui.button variant="primary" size="lg">Primary lg</x-ui.button>
                <x-ui.button variant="secondary" size="md">Secondary</x-ui.button>
                <x-ui.button variant="outline" size="md">Outline</x-ui.button>
                <x-ui.button variant="ghost" size="md">Ghost</x-ui.button>
                <x-ui.button variant="destructive" size="md">Destructive</x-ui.button>
                <x-ui.button variant="primary" size="icon" title="Иконка">⚙</x-ui.button>
                <x-ui.button variant="primary" size="md" disabled>Disabled</x-ui.button>
                <x-ui.button variant="primary" size="md" class="inline-flex items-center gap-2 rounded-md px-4 py-1.5 text-sm">Компакт primary (как «Создать»)</x-ui.button>
            </div>
        </x-ui.card>

        {{-- Хлебные крошки и легенда --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Хлебные крошки и легенда</h2>
            <p class="text-sm text-muted-foreground mb-3">
                <code class="text-primary">x-breadcrumbs</code> — навигация;
                <code class="text-primary">x-legend</code> — иконка + карточка поверх контента (список заказов).
            </p>
            <div class="mb-4">
                <x-breadcrumbs :items="[
                    ['label' => 'Главная', 'url' => '#'],
                    ['label' => 'Пример раздела', 'url' => null],
                ]" />
            </div>
            <div class="flex items-center gap-2">
                <x-legend :items="[
                    ['text' => 'Просроченное время встречи', 'legend_class' => 'legend-blink', 'indicator' => true],
                    ['label' => 'Новая', 'badge' => 'pending'],
                    ['label' => 'В работе', 'badge' => 'in_progress'],
                ]" />
                <span class="text-xs text-muted-foreground">Откройте легенду по иконке</span>
            </div>
        </x-ui.card>

        {{-- Поля --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Поля ввода и форма</h2>
            <div class="grid md:grid-cols-2 gap-6 max-w-3xl">
                <x-ui.form-group label="Filter input (sm, таблица)" name="demo_filter">
                    <x-ui.filter-input name="demo_filter" placeholder="Фильтр + лупа" size="sm" />
                </x-ui.form-group>
                <x-ui.form-group label="Текст (md)" name="demo_text">
                    <x-ui.input name="demo_text" placeholder="Placeholder" />
                </x-ui.form-group>
                <x-ui.form-group label="Маленький (sm)" name="demo_sm">
                    <x-ui.input size="sm" name="demo_sm" placeholder="filter" />
                </x-ui.form-group>
                <x-ui.form-group label="Селект" name="demo_sel">
                    <x-ui.select name="demo_sel">
                        <option value="">—</option>
                        <option value="1">Один</option>
                    </x-ui.select>
                </x-ui.form-group>
                <x-ui.form-group label="Текстовое поле" name="demo_area">
                    <x-ui.textarea name="demo_area" rows="3">Пример</x-ui.textarea>
                </x-ui.form-group>
                <div class="md:col-span-2">
                    <x-ui.checkbox name="demo_cb" label="Чекбокс с подписью" />
                </div>
            </div>
        </x-ui.card>

        {{-- Алерты --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Алерты</h2>
            <div class="space-y-2">
                <x-ui.alert type="success" class="!mb-2">Успех: операция выполнена.</x-ui.alert>
                <x-ui.alert type="error" class="!mb-2">Ошибка: проверьте поля.</x-ui.alert>
                <x-ui.alert type="warning" class="!mb-2">Предупреждение.</x-ui.alert>
                <x-ui.alert type="info">Информация.</x-ui.alert>
            </div>
        </x-ui.card>

        {{-- Карточки --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Карточки (padding)</h2>
            <div class="grid md:grid-cols-3 gap-4">
                <x-ui.card padding="none" class="p-3 text-sm text-muted-foreground">none + p-3</x-ui.card>
                <x-ui.card padding="sm" class="text-sm text-muted-foreground">sm</x-ui.card>
                <x-ui.card padding="lg" :shadow="true" class="text-sm text-muted-foreground">lg + shadow</x-ui.card>
            </div>
        </x-ui.card>

        {{-- Бейджи --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Бейджи (статусы)</h2>
            <div class="flex flex-wrap gap-2 items-center">
                <span class="inline-flex items-center rounded-md bg-chart-3/20 text-chart-3 px-2 py-0.5 text-xs font-medium">Активен</span>
                <span class="inline-flex items-center rounded-md bg-chart-2/20 text-chart-2 px-2 py-0.5 text-xs font-medium">В работе</span>
                <span class="inline-flex items-center rounded-md bg-destructive/20 text-destructive px-2 py-0.5 text-xs font-medium">Отмена</span>
                <span class="inline-flex items-center rounded-md bg-muted text-muted-foreground px-2 py-0.5 text-xs font-medium">Черновик</span>
            </div>
        </x-ui.card>

        {{-- Таблица --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Таблица</h2>
            <div class="overflow-x-auto rounded-md border border-border">
                <table class="w-full text-sm text-left">
                    <thead class="bg-muted text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">ID</th>
                            <th class="px-4 py-2 font-medium">Название</th>
                            <th class="px-4 py-2 font-medium">Статус</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border bg-card text-foreground">
                        @foreach (range(1, 5) as $i)
                            <tr class="hover:bg-muted/50">
                                <td class="px-4 py-2 font-mono">{{ 100 + $i }}</td>
                                <td class="px-4 py-2">Строка демо {{ $i }}</td>
                                <td class="px-4 py-2">
                                    <span class="rounded bg-primary/15 text-primary px-2 py-0.5 text-xs">OK</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        {{-- Пагинация --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Пагинация (Bootstrap 5)</h2>
            @php
                $uiKitPage = (int) request('ui_page', 1);
                $uiKitAllRows = collect(range(1, 18))->map(fn ($num) => ['id' => 100 + $num, 'name' => 'Строка ' . $num]);
                $uiKitPerPage = 5;
                $uiKitRows = $uiKitAllRows->forPage($uiKitPage, $uiKitPerPage)->values()->all();
                $uiKitPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                    $uiKitRows,
                    $uiKitAllRows->count(),
                    $uiKitPerPage,
                    $uiKitPage,
                    [
                        'path' => url()->current(),
                        'pageName' => 'ui_page',
                        'query' => request()->except('ui_page'),
                    ]
                );
            @endphp
            <div class="text-sm text-muted-foreground mb-3">
                Страница {{ $uiKitPaginator->currentPage() }} из {{ $uiKitPaginator->lastPage() }}
            </div>
            {{ $uiKitPaginator->links() }}
        </x-ui.card>

        {{-- График (демо столбцы) --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">График (демо)</h2>
            <p class="text-sm text-muted-foreground mb-4">Цвета из токенов chart-1 … chart-5</p>
            <div class="flex items-end gap-3 h-40 px-2">
                @foreach ([40, 65, 45, 80, 55] as $idx => $h)
                    @php
                        $chartVar = ['--chart-1', '--chart-2', '--chart-3', '--chart-4', '--chart-5'][$idx];
                    @endphp
                    <div
                        class="flex-1 rounded-t-md min-h-[20px]"
                        style="height: {{ $h }}%; background: var({{ $chartVar }})"
                        title="Серия {{ $idx + 1 }}"
                    ></div>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Календарь (сетка) --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Календарь (демо-сетка)</h2>
            <div class="text-sm font-medium text-foreground mb-2">Апрель 2026</div>
            <div class="grid grid-cols-7 gap-1 text-center text-xs text-muted-foreground mb-1">
                @foreach (['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'] as $d)
                    <div class="py-1 font-medium">{{ $d }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-sm">
                @foreach (range(1, 28) as $day)
                    <button
                        type="button"
                        class="aspect-square rounded-md border border-border bg-card text-foreground hover:bg-muted transition-colors {{ $day === 14 ? 'ring-2 ring-ring bg-primary/10' : '' }}"
                    >
                        {{ $day }}
                    </button>
                @endforeach
            </div>
        </x-ui.card>

        {{-- Вкладки (демо) --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-4">Вкладки (демо)</h2>
            <div class="inline-flex rounded-md border border-border bg-muted p-1 gap-1">
                <span class="px-3 py-1.5 rounded bg-card text-foreground text-sm shadow-sm">Активная</span>
                <span class="px-3 py-1.5 rounded text-muted-foreground text-sm hover:text-foreground cursor-pointer">Вторая</span>
                <span class="px-3 py-1.5 rounded text-muted-foreground text-sm hover:text-foreground cursor-pointer">Третья</span>
            </div>
        </x-ui.card>

        {{-- Паттерны (ориентир tweakcn / shadcn) — статичные демо --}}
        <x-ui.card padding="md">
            <h2 class="text-lg font-semibold text-foreground mb-1">Паттерны</h2>
            <p class="text-sm text-muted-foreground mb-6">Эталонные блоки в духе галереи tweakcn; разметка на токенах темы, без отдельных Blade-компонентов до повторного использования в продукте.</p>

            <h3 class="text-sm font-medium text-foreground mb-3">Карточки метрик</h3>
            <div class="grid sm:grid-cols-2 gap-4 mb-8">
                <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                    <p class="text-sm font-medium text-muted-foreground">Выручка</p>
                    <p class="text-2xl font-bold text-foreground mt-1 tabular-nums">15 231,89 ₽</p>
                    <p class="text-xs text-chart-3 font-medium mt-2">+20.1% к прошлому месяцу</p>
                </div>
                <div class="rounded-lg border border-border bg-card p-4 shadow-sm">
                    <p class="text-sm font-medium text-muted-foreground">Подписки</p>
                    <p class="text-2xl font-bold text-foreground mt-1 tabular-nums">2 350</p>
                    <p class="text-xs text-chart-2 font-medium mt-2">+180.1% к прошлому месяцу</p>
                </div>
            </div>

            <h3 class="text-sm font-medium text-foreground mb-3">Список с аватарами (инициалы)</h3>
            <ul class="rounded-lg border border-border divide-y divide-border bg-card max-w-md mb-8">
                @foreach ([['СД', 'София Д.', 'm@example.com', 'Владелец'], ['ДЛ', 'Джексон Л.', 'p@example.com', 'Разработчик']] as $row)
                    <li class="flex items-center gap-3 p-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/15 text-primary text-xs font-semibold">{{ $row[0] }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-foreground truncate">{{ $row[1] }}</p>
                            <p class="text-xs text-muted-foreground truncate">{{ $row[2] }}</p>
                        </div>
                        <span class="text-xs text-muted-foreground shrink-0">{{ $row[3] }}</span>
                    </li>
                @endforeach
            </ul>

            <h3 class="text-sm font-medium text-foreground mb-3">Radio-группа (тарифы)</h3>
            <div class="grid gap-3 max-w-lg mb-8">
                <label class="flex gap-3 items-start p-4 rounded-lg border border-border bg-card cursor-pointer has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-ring">
                    <input type="radio" name="ui_kit_plan" value="starter" class="mt-1 accent-primary shrink-0" checked />
                    <div>
                        <div class="font-medium text-foreground">Starter</div>
                        <div class="text-sm text-muted-foreground">Для небольших команд.</div>
                    </div>
                </label>
                <label class="flex gap-3 items-start p-4 rounded-lg border border-border bg-card cursor-pointer has-[:checked]:border-primary has-[:checked]:ring-2 has-[:checked]:ring-ring">
                    <input type="radio" name="ui_kit_plan" value="pro" class="mt-1 accent-primary shrink-0" />
                    <div>
                        <div class="font-medium text-foreground">Pro</div>
                        <div class="text-sm text-muted-foreground">Больше функций и объёма.</div>
                    </div>
                </label>
            </div>

            <h3 class="text-sm font-medium text-foreground mb-3">Progress</h3>
            <div class="max-w-md mb-2">
                <div class="flex justify-between text-xs text-muted-foreground mb-1">
                    <span>Прогресс</span>
                    <span>62%</span>
                </div>
                <div class="h-2 rounded-full bg-muted overflow-hidden">
                    <div class="h-full w-[62%] rounded-full bg-primary"></div>
                </div>
            </div>
            <p class="text-xs text-muted-foreground mb-8">Полоса: <code class="text-primary">bg-muted</code> + <code class="text-primary">bg-primary</code>.</p>

            <h3 class="text-sm font-medium text-foreground mb-3">Разделитель</h3>
            <div class="max-w-md space-y-3 mb-8">
                <p class="text-sm text-foreground">Блок выше</p>
                <hr class="border-0 border-t border-border" />
                <p class="text-sm text-foreground">Блок ниже</p>
            </div>

            <h3 class="text-sm font-medium text-foreground mb-3">Оболочка модалки (статично)</h3>
            <p class="text-xs text-muted-foreground mb-3">Без focus trap и ARIA — только визуальный эталон.</p>
            <div class="relative h-64 rounded-lg border border-border bg-muted/50 overflow-hidden">
                <div class="absolute inset-0 bg-foreground/40" aria-hidden="true"></div>
                <div class="absolute inset-0 flex items-center justify-center p-4">
                    <x-ui.card padding="md" class="max-w-sm shadow-lg w-full relative z-10">
                        <h3 class="text-lg font-semibold text-foreground mb-2">Пример диалога</h3>
                        <p class="text-sm text-muted-foreground mb-4">Контент модального окна на <code class="text-primary">x-ui.card</code>.</p>
                        <div class="flex gap-2 justify-end flex-wrap">
                            <x-ui.button variant="outline" size="sm" type="button">Отмена</x-ui.button>
                            <x-ui.button variant="primary" size="sm" type="button">Подтвердить</x-ui.button>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        </x-ui.card>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('ui-kit-theme-toggle');
    const root = document.documentElement;
    btn?.addEventListener('click', () => {
        root.classList.toggle('dark');
        try {
            localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
        } catch (e) {}
    });
});
</script>
@endpush
