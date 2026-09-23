@extends('layouts.error')

@section('title', 'Страница не найдена')

@section('content')
<x-ui.card class="p-16 text-center max-w-[500px] w-full">
    <div class="text-6xl mb-4" aria-hidden="true">🔍</div>
    <h1 class="text-foreground text-2xl font-semibold mb-2">Ошибка 404 — Страница не найдена</h1>
    <p class="text-muted-foreground mb-6">Запрашиваемая страница не существует</p>
    @auth
        <a href="{{ route('orders.index') }}" class="text-primary hover:underline">← Вернуться на главную</a>
    @else
        <a href="{{ hub_login_url() }}" class="text-primary hover:underline">← Войти в систему</a>
    @endauth
</x-ui.card>
@endsection
