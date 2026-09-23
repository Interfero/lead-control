@extends('layouts.app')

@section('title', 'Новая претензия')

@section('content')
<div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
    <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'Претензии', 'url' => route('complaints.index')],
                ['label' => 'Новая претензия', 'url' => null],
            ]"
        />
    </div>
</div>

<div style="max-width: 700px; margin: 0 auto;">
    <div class="card">
        <h1 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem;">Новая претензия</h1>
        
        <form method="POST" action="{{ route('complaints.store') }}">
            @csrf
            
            @if($order)
                <input type="hidden" name="order_id" value="{{ $order->order_id }}">
                <div class="form-group">
                    <label class="form-label">Заказ</label>
                    <div class="form-static">
                        #{{ $order->order_id }} — {{ $order->address->city->city_name ?? '' }}, {{ $order->address->street ?? '' }} {{ $order->address->house ?? '' }}
                    </div>
                </div>
            @endif
            
            @if($person)
                <input type="hidden" name="person_id" value="{{ $person->person_id }}">
                <div class="form-group">
                    <label class="form-label">Клиент</label>
                    <div class="form-static">{{ $person->person_name }}</div>
                </div>
            @endif
            
            <div class="form-row">
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Город <span style="color: #dc2626;">*</span></label>
                    <select name="city_id" class="form-input" required>
                        <option value="">Выберите город</option>
                        @foreach($cities as $city)
                            <option value="{{ $city->city_id }}" {{ ($presetCityId ?? '') == $city->city_id ? 'selected' : '' }}>{{ $city->city_name }}</option>
                        @endforeach
                    </select>
                    @error('city_id') <span class="form-error">{{ $message }}</span> @enderror
                </div>
                
                <div class="form-group" style="flex: 1;">
                    <label class="form-label">Тип <span style="color: #dc2626;">*</span></label>
                    <select name="complaint_type" class="form-input" required>
                        <option value="">Выберите тип</option>
                        @foreach(\App\Models\Complaint::TYPES as $code => $label)
                            <option value="{{ $code }}" {{ old('complaint_type') === $code ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('complaint_type') <span class="form-error">{{ $message }}</span> @enderror
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Описание претензии <span style="color: #dc2626;">*</span></label>
                <textarea name="complaint_text" class="form-input" rows="10" required maxlength="5000" placeholder="Опишите суть претензии...">{{ old('complaint_text') }}</textarea>
                @error('complaint_text') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">{!! icon('save') !!} Создать</button>
                <a href="{{ route('complaints.index') }}" class="btn btn-secondary" style="margin-left: auto;">{!! icon('back') !!} Назад</a>
            </div>
        </form>
    </div>
</div>
@endsection

