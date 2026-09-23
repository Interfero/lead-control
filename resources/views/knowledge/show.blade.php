@extends('layouts.app')

@section('title', $article->article_title)

@section('content')
<div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div>
        <a href="{{ route('information.index') }}" class="back-link">
            {!! icon('back') !!} Назад к списку
        </a>
        <h1 class="page-title" style="margin-top: 0.5rem;">{{ $article->article_title }}</h1>
        @if($article->article_category)
            <span class="category-badge">{!! icon('folder') !!} {{ $article->article_category }}</span>
        @endif
    </div>
    @if($canEdit)
        <a href="{{ route('information.edit', $article->article_id) }}" class="btn btn-secondary">
            {!! icon('edit') !!} Редактировать
        </a>
    @endif
</div>

<div class="card article-content @if($article->content_format === 'html') article-content--html regulation-document @endif">
    @if($article->content_format === 'html')
        {!! $article->article_content !!}
    @else
        {!! nl2br(e($article->article_content)) !!}
    @endif
</div>

@if($article->content_format === 'html')
@push('scripts')
<script>
document.querySelectorAll('.regulation-document a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (event) {
        var id = this.getAttribute('href').slice(1);
        var target = document.getElementById(id);
        if (!target) return;
        event.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', '#' + id);
    });
});
</script>
@endpush
@endif

<div class="article-meta">
    <span>{!! icon('user') !!} Автор: {{ $article->creator->user_name ?? 'Неизвестен' }}</span>
    <span>{!! icon('calendar') !!} Создано: {{ $article->created_at->format('d.m.Y H:i') }}</span>
    @if($article->updated_by)
        <span>{!! icon('edit') !!} Изменено: {{ $article->updated_at->format('d.m.Y H:i') }}</span>
    @endif
</div>
@endsection

