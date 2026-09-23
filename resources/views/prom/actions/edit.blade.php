@extends('layouts.app')

@section('title', 'Редактировать разноску')

@section('content')
<div class="page-container">
    <div class="card">
        <div class="card-header">
            <h5>{!! icon('edit') !!} Редактировать запись</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('prom.actions.update', $action) }}">
                @csrf
                @method('PUT')
                
                <div class="form-group">
                    <label class="form-label">Дата <span class="required">*</span></label>
                    <input type="date" name="route_action_date" class="form-input" 
                           value="{{ old('route_action_date', $action->route_action_date?->format('Y-m-d')) }}" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Город</label>
                    <input type="text" class="form-input" value="{{ $action->city?->city_name ?? '—' }}" disabled>
                    <span class="form-hint">Город нельзя изменить</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Промоутер <span class="required">*</span></label>
                    <select name="promoter_id" class="form-select" required>
                        @foreach($promoters as $promoter)
                            <option value="{{ $promoter->promoter_id }}" 
                                    {{ old('promoter_id', $action->promoter_id) == $promoter->promoter_id ? 'selected' : '' }}>
                                {{ $promoter->promoter_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Маршрут <span class="required">*</span></label>
                    <select name="route_id" class="form-select" required>
                        @foreach($routes as $route)
                            <option value="{{ $route->route_id }}" 
                                    {{ old('route_id', $action->route_id) == $route->route_id ? 'selected' : '' }}>
                                {{ $route->route_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Макет</label>
                    <select name="maket_id" class="form-select">
                        <option value="">Не выбран</option>
                        @foreach($makets as $maket)
                            <option value="{{ $maket->maket_id }}" 
                                    {{ old('maket_id', $action->maket_id) == $maket->maket_id ? 'selected' : '' }}>
                                {{ $maket->maket_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Листовок <span class="required">*</span></label>
                    <input type="number" name="leaflets_count" class="form-input" 
                           value="{{ old('leaflets_count', $action->leaflets_count) }}" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Примечание</label>
                    <textarea name="route_action_note" class="form-input" rows="2" maxlength="500">{{ old('route_action_note', $action->route_action_note) }}</textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        {!! icon('save') !!} Сохранить
                    </button>
                    <a href="{{ route('prom.actions') }}" class="btn btn-secondary">
                        Отмена
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
