@extends('layouts.app')

@section('title', 'Чёрный список')

@section('content')
<div style="max-width: 1600px; margin: 0 auto;">
    <div class="mb-3">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Сотрудники', 'url' => route('hr.index')],
                ['label' => 'Чёрный список', 'url' => null],
            ]"
        />
    </div>

    {{-- Форма поиска --}}
    <div class="card" style="margin-bottom: 1rem; padding: 0.75rem;">
        <form method="GET" action="{{ route('hr.blacklist') }}">
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                <div class="filter-field" style="width: 200px;">
                    <input type="text" name="passport" class="form-input" placeholder="Поиск по паспорту" 
                        value="{{ request('passport') }}">
                </div>
                <div class="filter-field" style="width: 200px;">
                    <input type="text" name="search" class="form-input" placeholder="Поиск по ФИО" 
                        value="{{ request('search') }}">
                </div>
                <button type="submit" class="btn btn-filter">{!! icon('search') !!} Поиск</button>
                <a href="{{ route('hr.blacklist') }}" class="btn btn-filter">{!! icon('refresh') !!} Сбросить</a>
            </div>
        </form>
    </div>
    
    {{-- Таблица --}}
    <div class="card" style="padding: 0;">
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ФИО</th>
                        <th>Паспорт</th>
                        <th>Города</th>
                        <th>Причина</th>
                        <th>Добавлен</th>
                        <th>Кем добавлен</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($blacklisted as $user)
                        <tr>
                            <td>{{ $user->user_id }}</td>
                            <td><strong>{{ $user->user_name }}</strong></td>
                            <td>{{ $user->user_passport ?? '—' }}</td>
                            <td>{{ $user->cities->pluck('city_name')->join(', ') }}</td>
                            <td style="max-width: 300px;">
                                <span title="{{ $user->blacklist_reason }}">
                                    {{ \Illuminate\Support\Str::limit($user->blacklist_reason, 100) }}
                                </span>
                            </td>
                            <td>{{ $user->blacklisted_at?->format('d.m.Y H:i') }}</td>
                            <td>{{ $user->blacklistedByUser?->user_name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align: center; color: #6b7280; padding: 2rem;">
                                Чёрный список пуст
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($blacklisted->hasPages())
            <div style="padding: 1rem; display: flex; justify-content: center;">
                {{ $blacklisted->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

