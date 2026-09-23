@php
    $user = auth()->user();
    $isMaster = $user->hasRole('master') && !$user->hasAnyRole(['developer', 'branch_head', 'regional_director']);
@endphp

@extends($isMaster ? 'layouts.master' : 'layouts.app')

@section('title', 'Главная')

@section('content')
@if ($isMaster)
    {{-- Для мастера --}}
    <div style="font-size: 4rem; margin-bottom: 1rem;">🔧</div>
    <h1 style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;">
        Добро пожаловать, {{ $user->user_name }}!
    </h1>
    <p style="color: #6b7280;">
        Ваш личный кабинет находится в разработке.<br>
        Скоро здесь появятся ваши заказы и статистика.
    </p>
@else
    {{-- Для остальных ролей --}}
    <div class="card">
        <div style="text-align: center; padding: 3rem;">
            <div style="font-size: 3rem; margin-bottom: 1rem;">🚧</div>
            <h2 style="color: #374151; margin-bottom: 0.5rem;">Дашборд в разработке</h2>
            <p style="color: #6b7280;">
                Здесь будет отображаться сводная информация по вашим городам
            </p>
        </div>
    </div>
@endif
@endsection
