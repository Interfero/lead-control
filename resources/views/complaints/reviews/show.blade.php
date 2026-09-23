@extends('layouts.app')

@section('title', 'Обращение #' . $review->id)

@section('content')
<div class="mb-4">
    <x-breadcrumbs
        :items="[
            ['label' => 'Главная', 'url' => route('orders.index')],
            ['label' => 'Претензии', 'url' => route('complaints.index')],
            ['label' => 'Обратная связь', 'url' => route('complaints.reviews')],
            ['label' => 'Обращение #' . $review->id, 'url' => null],
        ]"
    />
</div>

@if(session('success'))
    <div class="alert alert-success mb-4">{{ session('success') }}</div>
@endif

<div class="order-show-page">
    <div class="cfm-show-layout">
        <div>
            <div class="card p-4">
                <h3 class="mb-4 text-base font-semibold">Обращение #{{ $review->id }}</h3>

                <div class="form-group">
                    <label class="form-label">Ссылка</label>
                    <div class="form-static">
                        @if($review->link)
                            @if(\Illuminate\Support\Str::startsWith($review->link, ['http://', 'https://']))
                                <a href="{{ $review->link }}" target="_blank" rel="noopener noreferrer" class="text-primary hover:underline">{{ $review->link }}</a>
                            @else
                                <span class="whitespace-pre-line">{{ $review->link }}</span>
                            @endif
                        @else
                            —
                        @endif
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">ID партнёра</label>
                        <div class="form-static">{{ $review->partner_id ?? '—' }}</div>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Номер заявки</label>
                        <div class="form-static">{{ $review->order_number ?? '—' }}</div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Автор</label>
                    <div class="form-static">{{ $review->createdBy?->user_name ?? '—' }}</div>
                </div>

                <div class="form-group">
                    <label class="form-label">Дата</label>
                    <div class="form-static">{{ $review->created_at->format('d.m.Y H:i') }}</div>
                </div>
            </div>
        </div>

        <div class="cfm-show-sidebar">
            <div class="card p-4">
                <div class="order-show-buttons mb-4">
                    <a href="{{ route('complaints.reviews') }}" class="btn btn-secondary btn-order-compact" style="grid-column: 1 / -1;">
                        {!! icon('back') !!} К списку отзывов
                    </a>
                </div>

                <h3 class="order-documents-heading mb-3 border-t border-border pt-4" style="font-size: 1rem;">{!! icon('document') !!} Документы</h3>
                <div class="cfm-sidebar-documents-only" aria-label="Документы отзыва">
                    <div class="cfm-files-list">
                        @foreach($review->documents as $doc)
                            @php
                                $isImage = str_starts_with($doc->file_mime, 'image/');
                                $viewUrl = $isImage || $doc->file_mime === 'application/pdf'
                                    ? route('documents.show', $doc->document_id)
                                    : route('documents.download', $doc->document_id);
                            @endphp
                            <div class="cfm-file-item file-item" data-document-id="{{ $doc->document_id }}">
                                @if($isImage)
                                    <a href="{{ $viewUrl }}" target="_blank" class="cfm-file-thumb file-thumb">
                                        <img src="{{ route('documents.show', $doc->document_id) }}" alt="{{ $doc->file_name }}">
                                    </a>
                                @else
                                    <a href="{{ $viewUrl }}" target="_blank" class="cfm-file-thumb cfm-file-thumb--placeholder" title="Открыть файл">📄</a>
                                @endif
                                <div class="cfm-file-item-body">
                                    <a href="{{ $viewUrl }}" class="file-name cfm-file-name" target="_blank" title="Открыть файл">{{ $doc->file_name }}</a>
                                    <div class="cfm-file-item-actions">
                                        <span class="file-size">{{ $doc->human_size }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        @if($review->image && $review->documents->isEmpty())
                            <div class="cfm-file-item file-item">
                                <a href="{{ route('complaints.reviews.image', ['path' => $review->image]) }}" target="_blank" rel="noopener" class="cfm-file-thumb file-thumb">
                                    <img src="{{ route('complaints.reviews.image', ['path' => $review->image]) }}" alt="">
                                </a>
                                <div class="cfm-file-item-body">
                                    <span class="file-name cfm-file-name text-muted-foreground">Вложение (старый формат)</span>
                                </div>
                            </div>
                        @endif

                        @if($review->documents->isEmpty() && !$review->image)
                            <div class="rounded-md border border-border bg-muted p-4 text-center text-sm text-muted-foreground">
                                Нет прикреплённых файлов
                            </div>
                        @endif
                    </div>

                    <p class="order-docs-hint mt-3"><strong>Форматы:</strong> jpg, png, jpeg, gif, pdf, doc, docx, xls, xlsx, odt, txt, csv</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
