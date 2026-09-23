@extends('layouts.app')

@section('title', 'Редактирование: ' . $article->article_title)

@section('content')
<div class="page-header">
    <a href="{{ route('information.show', $article->article_id) }}" class="back-link">
        {!! icon('back') !!} Назад к статье
    </a>
    <h1 class="page-title" style="margin-top: 0.5rem;">{!! icon('edit') !!} Редактирование статьи</h1>
</div>

<div class="card">
    <form action="{{ route('information.update', $article->article_id) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="form-group">
            <label for="article_title" class="form-label">Заголовок <span class="required">*</span></label>
            <input 
                type="text" 
                id="article_title" 
                name="article_title" 
                class="form-control @error('article_title') is-invalid @enderror" 
                value="{{ old('article_title', $article->article_title) }}"
                required
            >
            @error('article_title')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        
        <div class="form-group">
            <label for="article_category" class="form-label">Категория</label>
            <input 
                type="text" 
                id="article_category" 
                name="article_category" 
                class="form-control @error('article_category') is-invalid @enderror" 
                value="{{ old('article_category', $article->article_category) }}"
                list="categories-list"
                placeholder="Например: Инструкции, FAQ, Правила"
            >
            @if(!empty($categories))
                <datalist id="categories-list">
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}">
                    @endforeach
                </datalist>
            @endif
            @error('article_category')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        
        <div class="form-group">
            <label for="article_content" class="form-label">Содержание <span class="required">*</span></label>
            <textarea 
                id="article_content" 
                name="article_content" 
                class="form-control @error('article_content') is-invalid @enderror" 
                rows="15"
                required
            >{{ old('article_content', $article->article_content) }}</textarea>
            @error('article_content')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        
        <div class="form-group">
            <label class="form-label">Доступ по ролям</label>
            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                @foreach(\App\Models\Role::where('is_active', true)->orderBy('role_name')->get() as $role)
                    <label style="display: flex; align-items: center; gap: 0.25rem; padding: 0.375rem 0.75rem; background: #f3f4f6; border-radius: 6px; cursor: pointer; font-size: 0.875rem;">
                        <input type="checkbox" name="visible_roles[]" value="{{ $role->role_code }}"
                            {{ in_array($role->role_code, $article->visible_roles ?? []) ? 'checked' : '' }}>
                        {{ $role->role_name }}
                    </label>
                @endforeach
            </div>
            <small class="form-text">Если ничего не выбрано — статья видна всем</small>
        </div>
        
        <div class="form-row">
            <div class="form-group" style="flex: 0 0 auto;">
                <label for="sort_order" class="form-label">Порядок сортировки</label>
                <input 
                    type="number" 
                    id="sort_order" 
                    name="sort_order" 
                    class="form-control @error('sort_order') is-invalid @enderror" 
                    value="{{ old('sort_order', $article->sort_order) }}"
                    min="0"
                    style="width: 120px;"
                >
                @error('sort_order')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="form-group" style="flex: 0 0 auto;">
                <label class="form-label">&nbsp;</label>
                <label class="checkbox-label">
                    <input 
                        type="checkbox" 
                        name="is_published" 
                        value="1"
                        {{ old('is_published', $article->is_published) ? 'checked' : '' }}
                    >
                    <span>Опубликовано</span>
                </label>
            </div>
        </div>
        
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                {!! icon('save') !!} Сохранить
            </button>
            <a href="{{ route('information.show', $article->article_id) }}" class="btn btn-secondary">
                Отмена
            </a>
            <button type="button" class="btn btn-danger" onclick="confirmDelete()">
                {!! icon('delete') !!} Удалить
            </button>
        </div>
    </form>
    
    <form id="delete-form" action="{{ route('information.destroy', $article->article_id) }}" method="POST" style="display: none;">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection

@push('scripts')
<script>
    function confirmDelete() {
        if (confirm('Вы уверены, что хотите удалить эту статью?')) {
            document.getElementById('delete-form').submit();
        }
    }
</script>
@endpush
