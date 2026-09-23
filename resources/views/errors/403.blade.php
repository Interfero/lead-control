@extends('layouts.error')

@section('title', 'Доступ запрещён')

@section('content')
<x-ui.card class="p-16 text-center max-w-[500px] w-full">
    <div class="text-6xl mb-4" aria-hidden="true">🚫</div>
    <h1 class="text-foreground text-2xl font-semibold mb-2">Ошибка 403 — Доступ запрещён</h1>
    <p class="text-muted-foreground mb-6">У вас нет прав для просмотра этой страницы</p>
    @auth
        <p class="text-muted-foreground text-sm mb-4">
            Ссылка «главная» для заказов вам недоступна. Откройте настройки — в шапке по имени есть «Выход».
        </p>
        <a href="{{ route('settings') }}" class="text-primary hover:underline">← Настройки</a>
    @else
        <a href="{{ hub_login_url() }}" class="text-primary hover:underline">← Войти в систему</a>
    @endauth
</x-ui.card>
@endsection
