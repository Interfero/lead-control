@extends('layouts.app')

@section('title', 'В разработке')

@section('content')
<div class="card" style="text-align: center; padding: 4rem;">
    <div style="font-size: 4rem; margin-bottom: 1rem;">🚧</div>
    <h1 style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;">
        Страница в разработке
    </h1>
    <p style="color: #6b7280; margin-bottom: 1.5rem;">
        Этот раздел скоро будет доступен
    </p>
    <a href="{{ route('orders.index') }}" style="color: #7c3aed; text-decoration: none;">
        ← Вернуться на главную
    </a>
</div>
@endsection
