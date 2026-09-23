@extends('layouts.app')

@section('title', 'Рассчитать мастера')

@section('content')
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 flex-col gap-1">
            <h1 class="m-0 text-lg font-semibold">Мастера для рассчета (по городам)</h1>
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Касса', 'url' => route('cfm.index')],
                ['label' => 'Рассчитать мастера', 'url' => null],
            ]"
        />
        </div>
        <div class="text-sm text-muted-foreground">
            Общая сумма: <strong class="text-foreground">{{ number_format($totalAmount, 0, ',', ' ') }} р.</strong>
        </div>
    </div>

    <x-ui.card padding="none" class="mb-4 min-w-0 max-w-full">
        <form method="GET" action="{{ route('cfm.master-settlement.index') }}" class="flex flex-wrap items-end gap-3 border-b border-border px-4 py-3">
            <div>
                <label class="mb-1 block text-xs text-muted-foreground">Город</label>
                <select name="city_id" class="form-input min-w-[12rem]" onchange="this.form.submit()">
                    <option value="">Все города</option>
                    @foreach ($cities as $city)
                        <option value="{{ $city->city_id }}" @selected($cityFilter === $city->city_id)>
                            {{ $city->city_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            @if ($cityFilter)
                <x-ui.button href="{{ route('cfm.master-settlement.index') }}" variant="secondary" size="sm">
                    Сбросить фильтр
                </x-ui.button>
            @endif
        </form>

        <form method="POST" action="{{ route('cfm.master-settlement.process-masters') }}" id="mastersSettlementForm">
            @csrf
            @if ($cityFilter)
                <input type="hidden" name="city_id" value="{{ $cityFilter }}">
            @endif

            <div class="overflow-x-auto">
                <table class="table w-full min-w-0 text-sm">
                    <thead>
                        <tr>
                            <th style="width: 2.5rem;">
                                <input type="checkbox" id="selectAllMasters" class="h-4 w-4 rounded border-border">
                            </th>
                            <th style="width: 3rem;">№</th>
                            <th>Имя мастера</th>
                            <th>Город</th>
                            <th class="text-right">К сдаче</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($masters as $index => $row)
                            <tr>
                                <td>
                                    <input
                                        type="checkbox"
                                        name="master_ids[]"
                                        value="{{ $row['master']->user_id }}"
                                        class="master-row-checkbox h-4 w-4 rounded border-border"
                                    >
                                </td>
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    <a href="{{ route('cfm.master-settlement.show', $row['master']->user_id) }}" class="text-primary hover:underline">
                                        {{ $row['master']->user_name }}
                                    </a>
                                </td>
                                <td>{{ $row['city_name'] }}</td>
                                <td class="text-right font-medium">{{ number_format($row['amount_to_pay'], 0, ',', ' ') }} р.</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-muted-foreground">
                                    Нет мастеров с несданными заказами
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($masters->isNotEmpty())
                <div class="border-t border-border px-4 py-3">
                    <p class="mb-3 text-sm text-muted-foreground">
                        Показаны записи 1–{{ $masters->count() }} из {{ $masters->count() }}
                    </p>
                    <x-ui.button type="submit" class="bg-emerald-600 hover:bg-emerald-700">
                        Обработать выбранных
                    </x-ui.button>
                </div>
            @endif
        </form>
    </x-ui.card>
@endsection

@push('scripts')
<script>
document.getElementById('selectAllMasters')?.addEventListener('change', function () {
    document.querySelectorAll('.master-row-checkbox').forEach(cb => { cb.checked = this.checked; });
});

document.getElementById('mastersSettlementForm')?.addEventListener('submit', function (e) {
    const checked = document.querySelectorAll('.master-row-checkbox:checked');
    if (checked.length === 0) {
        e.preventDefault();
        alert('Выберите хотя бы одного мастера');
        return;
    }
    if (!confirm('Отметить все несданные заказы выбранных мастеров как сданные?')) {
        e.preventDefault();
    }
});
</script>
@endpush
