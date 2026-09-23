@extends('layouts.error')

@section('title', 'Сессия истекла')

@section('content')
<x-ui.card class="p-16 text-center max-w-[520px] w-full">
    <div class="text-6xl mb-4" aria-hidden="true">⏱</div>
    <h1 class="text-foreground text-2xl font-semibold mb-2">Сессия истекла</h1>
    <p class="text-muted-foreground mb-6">
        Страница была открыта слишком долго или форма устарела после обновления системы.
        Обновите страницу и повторите действие.
    </p>
    <div class="flex flex-wrap items-center justify-center gap-3">
        <button type="button" class="btn btn-primary" onclick="window.location.reload()">Обновить страницу</button>
        @auth
            <a href="{{ route('orders.index') }}" class="btn btn-secondary">К заказам</a>
        @else
            <a href="{{ hub_login_url() }}" class="btn btn-secondary">Войти снова</a>
        @endauth
    </div>
</x-ui.card>
@endsection
