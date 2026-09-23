@extends('layouts.app')

@section('title', 'Обратная связь')

@section('content')
<div style="max-width: 1200px; margin: 0 auto;">
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Претензии', 'url' => route('complaints.index')],
                    ['label' => 'Обратная связь', 'url' => null],
                ]"
            />
        </div>
        @if (auth()->user()->hasAnyRole(['developer', 'call_center', 'senior_dispatcher']))
            <div class="shrink-0">
                <x-ui.button
                    href="{{ route('complaints.reviews.create') }}"
                    class="gap-2 rounded-md px-4 py-1.5 text-sm"
                >
                    {!! icon('add') !!}
                    Добавить обращение
                </x-ui.button>
            </div>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom: 1rem;">{{ session('success') }}</div>
    @endif

    <div class="card" style="padding: 0;">
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th style="min-width: 200px;">Документы</th>
                        <th>Ссылка</th>
                        <th>ID партнёра</th>
                        <th>Номер заявки</th>
                        <th>Автор</th>
                        <th>Дата</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($reviews as $review)
                        <tr>
                            <td>
                                <div class="flex flex-wrap items-center gap-1">
                                    @foreach($review->documents as $doc)
                                        @if($loop->iteration > 4)
                                            @break
                                        @endif
                                        @php
                                            $isImage = str_starts_with($doc->file_mime, 'image/');
                                            $viewUrl = $isImage || $doc->file_mime === 'application/pdf'
                                                ? route('documents.show', $doc->document_id)
                                                : route('documents.download', $doc->document_id);
                                        @endphp
                                        @if($isImage)
                                            <a href="{{ $viewUrl }}" target="_blank" rel="noopener" class="block h-10 w-10 shrink-0 overflow-hidden rounded border border-border" title="{{ $doc->file_name }}">
                                                <img src="{{ route('documents.show', $doc->document_id) }}" alt="" class="h-full w-full object-cover">
                                            </a>
                                        @else
                                            <a href="{{ $viewUrl }}" target="_blank" rel="noopener" class="flex h-10 w-10 shrink-0 items-center justify-center rounded border border-border bg-muted text-lg" title="{{ $doc->file_name }}">📄</a>
                                        @endif
                                    @endforeach
                                    @if($review->image && $review->documents->isEmpty())
                                        <a href="{{ route('complaints.reviews.image', ['path' => $review->image]) }}" target="_blank" rel="noopener" class="block h-10 w-10 shrink-0 overflow-hidden rounded border border-border">
                                            <img src="{{ route('complaints.reviews.image', ['path' => $review->image]) }}" alt="" class="h-full w-full object-cover">
                                        </a>
                                    @endif
                                    @if($review->documents->isEmpty() && !$review->image)
                                        <span class="text-muted-foreground" title="Нет файлов">—</span>
                                    @endif
                                    @if($review->documents->count() > 4)
                                        <span class="text-xs text-muted-foreground">+{{ $review->documents->count() - 4 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                @if($review->link)
                                    @if(\Illuminate\Support\Str::startsWith($review->link, ['http://', 'https://']))
                                        <a href="{{ $review->link }}" target="_blank" rel="noopener noreferrer" style="color: var(--primary);">{{ \Illuminate\Support\Str::limit($review->link, 50) }}</a>
                                    @else
                                        <span title="{{ $review->link }}">{{ \Illuminate\Support\Str::limit($review->link, 50) }}</span>
                                    @endif
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $review->partner_id ?? '—' }}</td>
                            <td>{{ $review->order_number ?? '—' }}</td>
                            <td>{{ $review->createdBy?->user_name ?? '—' }}</td>
                            <td>{{ $review->created_at->format('d.m.Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align: center; color: #6b7280; padding: 2rem;">Обращений пока нет</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($reviews->hasPages())
            <div style="padding: 1rem;">
                {{ $reviews->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
