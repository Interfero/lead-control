@extends('layouts.master')

@section('title', 'Главная')

@section('content')
<div style="font-size: 4rem; margin-bottom: 1rem;">{!! icon('settings') !!}</div>
<h1 style="font-size: 1.5rem; color: #374151; margin-bottom: 0.5rem;">
    Добро пожаловать, {{ auth()->user()->user_name }}!
</h1>
<p style="color: #6b7280;">
    Ваш личный кабинет находится в разработке.<br>
    Скоро здесь появятся ваши заказы и статистика.
</p>
@endsection
