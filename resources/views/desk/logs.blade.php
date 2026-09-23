@extends('layouts.app')

@section('title', 'Логи Единого окна')

@section('content')
<div class="mx-auto max-w-[1400px] min-w-0">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h1 class="m-0 text-xl font-semibold">Логи Единого окна</h1>
        <x-ui.button href="{{ route('desk.index') }}" variant="secondary" size="sm">Назад</x-ui.button>
    </div>

    <x-ui.card padding="sm" class="mb-4">
        <form method="GET" class="flex flex-wrap gap-2">
            <select name="crm_id" class="form-control form-control-sm">
                <option value="">Все CRM</option>
                @foreach($connections as $crm)
                    <option value="{{ $crm->id }}" @selected((string) request('crm_id') === (string) $crm->id)>{{ $crm->name }}</option>
                @endforeach
            </select>
            <select name="level" class="form-control form-control-sm">
                <option value="">Все уровни</option>
                @foreach(['error','warning','info'] as $lvl)
                    <option value="{{ $lvl }}" @selected(request('level') === $lvl)>{{ $lvl }}</option>
                @endforeach
            </select>
            <x-ui.button type="submit" size="sm">Фильтр</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card padding="none">
        <div class="table-responsive">
            <table class="table text-sm">
                <thead>
                    <tr>
                        <th>Время</th>
                        <th>CRM</th>
                        <th>Уровень</th>
                        <th>Действие</th>
                        <th>Заказ</th>
                        <th>Сообщение</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap">{{ $log->created_at?->format('d.m.Y H:i:s') }}</td>
                            <td>{{ $log->connection?->name ?? '—' }}</td>
                            <td>{{ $log->level }}</td>
                            <td>{{ $log->action ?? '—' }}</td>
                            <td>{{ $log->external_id ?? '—' }}</td>
                            <td class="max-w-xl break-words">{{ $log->message }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-muted-foreground">Пусто</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($logs->hasPages())
            <div class="border-t border-border px-4 py-3">{{ $logs->links() }}</div>
        @endif
    </x-ui.card>
</div>
@endsection
