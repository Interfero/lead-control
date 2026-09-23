@extends('layouts.app')

@section('title', 'Новая статья')

@section('content')
    <div class="orders-index-toolbar mb-4">
        <x-breadcrumbs
            :items="[
                ['label' => 'Главная', 'url' => route('orders.index')],
                ['label' => 'База знаний', 'url' => route('information.index')],
                ['label' => 'Новая статья', 'url' => null],
            ]"
        />
    </div>

    <x-ui.card class="max-w-3xl">
        <h3 class="mb-4 text-base font-semibold">{!! icon('add') !!} Новая статья</h3>

        <form action="{{ route('information.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="article_title" class="form-label">Заголовок <span class="required">*</span></label>
                <input
                    type="text"
                    id="article_title"
                    name="article_title"
                    class="form-input @error('article_title') error @enderror"
                    value="{{ old('article_title') }}"
                    required
                    autofocus
                >
                @error('article_title')
                    <small class="text-destructive">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="article_category" class="form-label">Категория</label>
                <input
                    type="text"
                    id="article_category"
                    name="article_category"
                    class="form-input @error('article_category') error @enderror"
                    value="{{ old('article_category') }}"
                    list="categories-list"
                    placeholder="Например: Инструкции, FAQ, Регламент"
                >
                @if (!empty($categories))
                    <datalist id="categories-list">
                        @foreach ($categories as $cat)
                            <option value="{{ $cat }}">
                        @endforeach
                    </datalist>
                @endif
                @error('article_category')
                    <small class="text-destructive">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label for="article_content" class="form-label">Содержание <span class="required">*</span></label>
                <textarea
                    id="article_content"
                    name="article_content"
                    class="form-input @error('article_content') error @enderror"
                    rows="15"
                    required
                >{{ old('article_content') }}</textarea>
                @error('article_content')
                    <small class="text-destructive">{{ $message }}</small>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Доступ по ролям</label>
                <div class="flex flex-wrap gap-2">
                    @foreach (\App\Models\Role::where('is_active', true)->orderBy('role_name')->get() as $role)
                        <label class="flex cursor-pointer items-center gap-2 rounded-md border border-border bg-muted px-3 py-1.5 text-sm">
                            <input type="checkbox" name="visible_roles[]" value="{{ $role->role_code }}"
                                {{ in_array($role->role_code, old('visible_roles', [])) ? 'checked' : '' }}>
                            {{ $role->role_name }}
                        </label>
                    @endforeach
                </div>
                <small class="text-muted-foreground">Если ничего не выбрано — статья видна всем</small>
            </div>

            <div class="form-group">
                <label for="sort_order" class="form-label">Порядок сортировки</label>
                <input
                    type="number"
                    id="sort_order"
                    name="sort_order"
                    class="form-input w-28 @error('sort_order') error @enderror"
                    value="{{ old('sort_order', 0) }}"
                    min="0"
                >
                @error('sort_order')
                    <small class="text-destructive">{{ $message }}</small>
                @enderror
            </div>

            <div class="mt-4 flex flex-wrap gap-3">
                <x-ui.button type="submit" class="gap-2">
                    {!! icon('save') !!} Сохранить
                </x-ui.button>
                <x-ui.button href="{{ route('information.index') }}" variant="secondary">
                    Отмена
                </x-ui.button>
            </div>
        </form>
    </x-ui.card>
@endsection
