@extends('layouts.app')

@section('title', $source->source_name)

@section('navbar_context')
    {{ $source->source_name }}
@endsection

@section('content')
    <div class="mx-auto mb-4 max-w-3xl">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Источники заказов', 'url' => route('management.sources.index')],
                ['label' => $source->source_name . ' (ID ' . $source->source_id . ')', 'url' => null],
            ]"
        />
    </div>

    <div class="mx-auto max-w-3xl">
        <x-ui.card>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h3 class="m-0 text-base font-semibold">Редактирование источника</h3>
                <x-ui.button href="{{ route('management.sources.index') }}" variant="secondary" class="shrink-0 gap-2">
                    {!! icon('back') !!}
                    Назад
                </x-ui.button>
            </div>

            <form method="POST" action="{{ route('management.sources.update', $source->source_id) }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label class="form-label">Название *</label>
                    <input type="text" name="source_name" class="form-input" value="{{ old('source_name', $source->source_name) }}" required>
                    @error('source_name')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <x-input-phone-ru name="source_phone" id="sourcePhone" label="Телефон линии" :value="old('source_phone', $source->source_phone)" />
                    @error('source_phone')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Формат</label>
                    <select name="source_format" class="form-input">
                        <option value="">Не задан</option>
                        <option value="{{ \App\Models\Source::FORMAT_ONLINE }}" {{ old('source_format', $source->source_format) === \App\Models\Source::FORMAT_ONLINE ? 'selected' : '' }}>Онлайн</option>
                        <option value="{{ \App\Models\Source::FORMAT_OFFLINE }}" {{ old('source_format', $source->source_format) === \App\Models\Source::FORMAT_OFFLINE ? 'selected' : '' }}>Офлайн</option>
                    </select>
                    @error('source_format')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                @include('management.sources._kind-fields', [
                    'sourceKind' => old('source_kind', $source->source_kind ?? \App\Models\Source::KIND_FLYER),
                    'useSourceUrl' => old('use_source_url', $source->use_source_url),
                    'sourceUrl' => old('source_url', $source->source_url),
                    'availableForSuperpart' => old('available_for_superpart', $source->available_for_superpart),
                ])

                <div class="form-group">
                    <label class="form-label">Город</label>
                    <select name="city_id" class="form-input">
                        <option value="">Не привязан</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->city_id }}" {{ (string) old('city_id', $source->city_id) === (string) $city->city_id ? 'selected' : '' }}>
                                {{ $city->city_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('city_id')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group" id="flyerMaketGroup">
                    <label class="form-label">Макет листовки</label>
                    <select name="flyer_maket_id" class="form-input">
                        <option value="">Не выбран</option>
                        @foreach ($flyerMakets as $maket)
                            <option value="{{ $maket->flyer_maket_id }}" {{ (string) old('flyer_maket_id', $source->flyer_maket_id) === (string) $maket->flyer_maket_id ? 'selected' : '' }}>
                                {{ $maket->flyer_maket_name }}@if (! $maket->is_active) (неактивен) @endif
                            </option>
                        @endforeach
                    </select>
                    @error('flyer_maket_id')
                        <small class="text-destructive">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label">ID партнёра SuperPart</label>
                    <p class="form-input m-0 bg-muted/50 text-muted-foreground">{{ $source->superpart_partner_id ?? '—' }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">Значение задаётся из SuperPart (API), в CRM не редактируется.</p>
                </div>

                <div class="form-group">
                    <label class="filter-checkbox inline-flex cursor-pointer select-none items-center gap-2">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $source->is_active ? '1' : '0') ? 'checked' : '' }}>
                        <span>Активный источник</span>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap justify-between gap-3">
                    @if (auth()->user()->hasAnyRole(['developer', 'general_director', 'senior_dispatcher']))
                        <button type="button" class="btn btn-danger gap-2" onclick="confirmDeleteSource()">
                            {!! icon('delete') !!}
                            Удалить
                        </button>
                    @else
                        <span></span>
                    @endif
                    <div class="flex flex-wrap gap-3">
                        <x-ui.button href="{{ route('management.sources.index') }}" variant="secondary">
                            Отмена
                        </x-ui.button>
                        <x-ui.button type="submit" class="gap-2">
                            {!! icon('save') !!}
                            Сохранить
                        </x-ui.button>
                    </div>
                </div>
            </form>

            @if (($attachedOrders ?? collect())->isNotEmpty())
                <div class="mt-6 border-t border-border pt-4">
                    <h4 class="mb-3 text-sm font-semibold">Привязанные заказы ({{ $attachedOrders->count() }})</h4>
                    <div class="overflow-x-auto rounded-md border border-border">
                        <table class="table w-full text-sm">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Статус</th>
                                    <th>Город</th>
                                    <th>Время заявки</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($attachedOrders as $attachedOrder)
                                    <tr>
                                        <td>
                                            <a href="{{ route('orders.show', $attachedOrder->order_id) }}" class="text-primary hover:underline" target="_blank" rel="noopener">
                                                №{{ $attachedOrder->order_id }}
                                            </a>
                                        </td>
                                        <td>{{ \App\Models\Order::getStatusLabels()[$attachedOrder->order_status] ?? $attachedOrder->order_status }}</td>
                                        <td>{{ $attachedOrder->address?->city?->city_name ?? '—' }}</td>
                                        <td>{{ $attachedOrder->datetime_order?->format('d.m.Y H:i') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        При удалении источника он будет снят с этих заказов (поле «источник» станет пустым).
                    </p>
                </div>
            @endif

            @if (auth()->user()->hasAnyRole(['developer', 'general_director', 'senior_dispatcher']))
                <form id="delete-source-form" action="{{ route('management.sources.destroy', $source->source_id) }}" method="POST" class="hidden">
                    @csrf
                    @method('DELETE')
                </form>
            @endif
        </x-ui.card>
    </div>
@endsection

@if (auth()->user()->hasAnyRole(['developer', 'general_director', 'senior_dispatcher']))
    @push('scripts')
        @php
            $deleteOrderIds = ($attachedOrders ?? collect())->pluck('order_id')->all();
        @endphp
        <script>
            function confirmDeleteSource() {
                const ordersCount = {{ (int) ($attachedOrders ?? collect())->count() }};
                const orderIds = @json($deleteOrderIds);
                let message = @json('Удалить источник «' . $source->source_name . '»? Это действие нельзя отменить.');
                if (ordersCount > 0) {
                    message += '\n\nК источнику привязано заказов: ' + ordersCount + '.';
                    message += '\nИсточник будет снят с этих заказов.';
                    if (orderIds.length > 0) {
                        message += '\n\nЗаказы: ' + orderIds.map(function (id) { return '№' + id; }).join(', ');
                    }
                }
                if (confirm(message)) {
                    document.getElementById('delete-source-form').submit();
                }
            }
        </script>
    @endpush
@endif
