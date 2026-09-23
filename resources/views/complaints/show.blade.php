@extends('layouts.app')

@section('title', "Претензия №{$complaint->complaint_id}")

@section('content')
@php
    $canEditComplaint = !auth()->user()->hasRole('general_director') || auth()->user()->hasRole('developer');
@endphp
<div style="max-width: 900px; margin: 0 auto;">
    <div style="display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; align-items: start;">
        
        {{-- ЛЕВАЯ КОЛОНКА --}}
        <div>
            <div class="card" style="padding: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h1 style="font-size: 1.125rem; font-weight: 600; margin: 0;">Претензия №{{ $complaint->complaint_id }}</h1>
                    <span class="badge badge-complaint-{{ $complaint->complaint_status }}">{{ $complaint->status_label }}</span>
                </div>
                
                @if($complaint->isCloseable() && $canEditComplaint)
                <form method="POST" action="{{ route('complaints.update', $complaint->complaint_id) }}">
                    @csrf
                    @method('PUT')
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Тип</label>
                            <select name="complaint_type" class="form-input">
                                @foreach(\App\Models\Complaint::TYPES as $code => $label)
                                    <option value="{{ $code }}" {{ $complaint->complaint_type === $code ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Статус</label>
                            <select name="complaint_status" class="form-input">
                                @foreach(\App\Models\Complaint::STATUSES as $code => $label)
                                    <option value="{{ $code }}" {{ $complaint->complaint_status === $code ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Описание</label>
                        <textarea name="complaint_text" class="form-input" rows="5">{{ $complaint->complaint_text }}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Результат / Решение</label>
                        <textarea name="complaint_result" class="form-input" rows="3" placeholder="Опишите результат рассмотрения...">{{ $complaint->complaint_result }}</textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">{!! icon('save') !!} Сохранить</button>
                        <a href="{{ route('complaints.index') }}" class="btn btn-secondary" style="margin-left: auto;">{!! icon('back') !!} Назад</a>
                    </div>
                </form>
                @elseif(!$canEditComplaint)
                    {{-- Ген. директор: только просмотр открытой карточки --}}
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Тип</label>
                            <div class="form-static">{{ $complaint->type_label }}</div>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Статус</label>
                            <div class="form-static">{{ $complaint->status_label }}</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Описание</label>
                        <div class="form-static" style="white-space: pre-line;">{{ $complaint->complaint_text }}</div>
                    </div>
                    @if($complaint->complaint_result)
                    <div class="form-group">
                        <label class="form-label">Результат</label>
                        <div class="form-static" style="white-space: pre-line;">{{ $complaint->complaint_result }}</div>
                    </div>
                    @endif
                    <a href="{{ route('complaints.index') }}" class="btn btn-secondary">{!! icon('back') !!} Назад</a>
                @else
                    {{-- Просмотр (претензия закрыта) --}}
                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Тип</label>
                            <div class="form-static">{{ $complaint->type_label }}</div>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Статус</label>
                            <div class="form-static">{{ $complaint->status_label }}</div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Описание</label>
                        <div class="form-static" style="white-space: pre-line;">{{ $complaint->complaint_text }}</div>
                    </div>
                    
                    @if($complaint->complaint_result)
                    <div class="form-group">
                        <label class="form-label">Результат</label>
                        <div class="form-static" style="white-space: pre-line;">{{ $complaint->complaint_result }}</div>
                    </div>
                    @endif
                    
                    <a href="{{ route('complaints.index') }}" class="btn btn-secondary">{!! icon('back') !!} Назад</a>
                @endif
            </div>
            
            {{-- Комментарии --}}
            <div class="card" style="padding: 1rem; margin-top: 1rem;">
                <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem;">{!! icon('document') !!} Комментарии</h3>
                
                @forelse($complaint->comments->sortByDesc('created_at') as $comment)
                    <div style="padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; font-size: 0.8125rem;">
                        <div style="display: flex; justify-content: space-between;">
                            <span style="font-weight: 600;">{{ $comment->author?->user_name ?? 'Неизвестно' }}</span>
                            <span style="color: #9ca3af; font-size: 0.75rem;">{{ $comment->created_at?->format('d.m.Y H:i') }}</span>
                        </div>
                        <div style="margin-top: 0.25rem; color: #4b5563; white-space: pre-line;">{{ $comment->comment_text }}</div>
                    </div>
                @empty
                    <div style="text-align: center; color: #9ca3af; padding: 1rem;">Комментариев пока нет</div>
                @endforelse
                
                @if($canEditComplaint)
                <form method="POST" action="{{ route('complaints.comments.store', $complaint->complaint_id) }}" style="margin-top: 1rem;" id="complaintCommentForm">
                    @csrf
                    <textarea name="comment_text" class="form-input" rows="5" required placeholder="Добавить комментарий..." maxlength="2000"></textarea>
                    <button type="submit" class="btn btn-primary" style="margin-top: 0.5rem; height: 32px; font-size: 0.8125rem;" id="complaintCommentSubmitBtn">{!! icon('save') !!} Добавить</button>
                </form>
                <script>
                    (function () {
                        var form = document.getElementById('complaintCommentForm');
                        if (!form || form.dataset.bound === '1') return;
                        form.dataset.bound = '1';
                        form.addEventListener('submit', function (e) {
                            if (form.dataset.submitting === '1') {
                                e.preventDefault();
                                return;
                            }
                            form.dataset.submitting = '1';
                            var btn = document.getElementById('complaintCommentSubmitBtn');
                            if (btn) {
                                btn.disabled = true;
                                btn.setAttribute('aria-busy', 'true');
                                btn.textContent = 'Отправка…';
                            }
                        }, { once: false });
                    })();
                </script>
                @endif
            </div>
        </div>
        
        {{-- ПРАВАЯ КОЛОНКА --}}
        <div class="card" style="padding: 1rem; position: sticky; top: 80px;">
            {{-- Клиент --}}
            @if($complaint->person)
            <div style="margin-bottom: 0.75rem;">
                <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;">Клиент</div>
                <div>{{ $complaint->person->person_name }}</div>
                @foreach($complaint->person->phones as $phone)
                    <div style="color: #6b7280; font-size: 0.8125rem;"><x-phone-masked :phone="$phone" /></div>
                @endforeach
            </div>
            @endif
            
            {{-- Заказ --}}
            @if($complaint->order)
            <div style="margin-bottom: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e5e7eb;">
                <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;">Заказ</div>
                <a href="{{ route('orders.show', $complaint->order_id) }}" style="color: var(--primary);">
                    #{{ $complaint->order_id }}
                </a>
                @if($complaint->order->master)
                    <div style="color: #6b7280; font-size: 0.8125rem;">Мастер: {{ $complaint->order->master->user_name }}</div>
                @endif
            </div>
            @endif
            
            {{-- Город --}}
            <div style="margin-bottom: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #e5e7eb;">
                <div style="font-weight: 600; font-size: 0.875rem; margin-bottom: 0.25rem;">Город</div>
                <div>{{ $complaint->city->city_name ?? '—' }}</div>
            </div>
            
            {{-- Метаданные --}}
            <div style="padding-top: 0.75rem; border-top: 1px solid #e5e7eb; font-size: 0.8125rem; color: #6b7280;">
                <div><strong>Создана:</strong> {{ $complaint->complaint_created_at?->format('d.m.Y H:i') }}</div>
                <div><strong>Автор:</strong> {{ $complaint->createdBy?->user_name ?? '—' }}</div>
                @if($complaint->complaint_closed_at)
                    <div style="margin-top: 0.5rem;"><strong>Закрыта:</strong> {{ $complaint->complaint_closed_at->format('d.m.Y H:i') }}</div>
                    <div><strong>Закрыл:</strong> {{ $complaint->closedBy?->user_name ?? '—' }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

