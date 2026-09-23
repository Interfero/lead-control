@extends('layouts.app')

@section('title', 'База знаний')

@section('content')
    <div class="mx-auto w-full max-w-5xl min-w-0 px-0 sm:px-2">
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'База знаний', 'url' => null],
                ]"
            />
        </div>
        @if ($canEdit)
            <div class="shrink-0">
                <x-ui.button
                    href="{{ route('information.create') }}"
                    class="gap-2 rounded-md px-4 py-1.5 text-sm"
                >
                    {!! icon('add') !!}
                    Добавить статью
                </x-ui.button>
            </div>
        @endif
    </div>

    @forelse($articles as $category => $categoryArticles)
        <div class="card knowledge-index-category mb-4 w-full min-w-0 overflow-hidden p-4 sm:p-5">
            <h3 class="knowledge-index-category-title mb-3 break-words">{!! icon('folder') !!} {{ $category ?: 'Общее' }}</h3>
            <ul class="article-list knowledge-article-list">
                @foreach($categoryArticles as $article)
                    <li class="knowledge-article-list__item">
                        <a href="{{ route('information.show', $article->article_id) }}" class="knowledge-article-list__link break-words">
                            {!! icon('document') !!} {{ $article->article_title }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <div class="card w-full p-8 text-center sm:p-12">
            <div class="mb-4 text-5xl">{!! icon('info') !!}</div>
            <p class="text-muted-foreground">Статей пока нет</p>
            @if ($canEdit)
                <div class="mt-4">
                    <x-ui.button
                        href="{{ route('information.create') }}"
                        class="gap-2 rounded-md px-4 py-1.5 text-sm"
                    >
                        {!! icon('add') !!}
                        Создать первую статью
                    </x-ui.button>
                </div>
            @endif
        </div>
    @endforelse
    </div>
@endsection
