@extends('layouts.app')

@section('title', 'Редактор статей ДДС')

@section('content')
<div class="mx-auto min-w-0 max-w-full">
    <div class="orders-index-toolbar mb-4 flex min-w-0 max-w-full flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 max-w-full flex-1 flex-wrap items-center gap-2">
            <x-breadcrumbs
                :items="[
                    ['label' => 'Главная', 'url' => route('orders.index')],
                    ['label' => 'Касса', 'url' => route('cfm.index')],
                    ['label' => 'Редактор статей', 'url' => null],
                ]"
            />
        </div>
        <div class="shrink-0">
            <x-ui.button
                type="button"
                onclick="toggleNewForm()"
                class="gap-2 rounded-md px-4 py-1.5 text-sm"
            >
                {!! icon('add') !!}
                Новая статья
            </x-ui.button>
        </div>
    </div>

    {{-- Форма создания новой статьи --}}
    <div id="newCategoryForm" class="card" style="margin-bottom: 1rem; display: none;">
        <h3 style="margin: 0 0 1rem; font-size: 1rem;">Создать новую статью</h3>
        <form method="POST" action="{{ route('cfm.editor.store') }}">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label class="form-label">Название <span style="color: #dc2626;">*</span></label>
                    <input type="text" name="cfm_cat_name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Тип <span style="color: #dc2626;">*</span></label>
                    <select name="cfm_cat_group" class="form-input" required>
                        <option value="inflows">Приход</option>
                        <option value="outflows">Расход</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Вид операции <span style="color: #dc2626;">*</span></label>
                    <select name="cfm_cat_activities" class="form-input" required>
                        <option value="operating">Операционная</option>
                        <option value="investing">Инвестиционная</option>
                        <option value="financing">Финансовая</option>
                        <option value="technical">Техническая</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Подпункты (через запятую)</label>
                    <input type="text" name="subcategories" class="form-input" placeholder="Пункт 1, Пункт 2">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Описание</label>
                <input type="text" name="cfm_cat_adds" class="form-input">
            </div>
            <div style="display: flex; gap: 1.5rem; margin-bottom: 1rem;">
                <label class="checkbox-label">
                    <input type="checkbox" name="available_for_city" value="1" checked> Город
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="available_for_mc" value="1"> УК
                </label>
                <label class="checkbox-label">
                    <input type="checkbox" name="available_for_df" value="1"> ФР
                </label>
            </div>
            <button type="submit" class="btn btn-primary">{!! icon('save') !!} Создать</button>
        </form>
    </div>
    
    {{-- Таблица статей --}}
    <div class="card" style="padding: 0;">
        <div style="overflow-x: auto;">
            <table class="table" style="min-width: 1000px;">
                <thead>
                    <tr>
                        <th style="min-width: 160px;">Статья</th>
                        <th style="min-width: 90px;">Тип</th>
                        <th style="min-width: 120px;">Вид операции</th>
                        <th style="min-width: 150px;">Описание</th>
                        <th style="min-width: 130px;">Подпункты</th>
                        <th style="width: 50px; text-align: center;">Город</th>
                        <th style="width: 50px; text-align: center;">УК</th>
                        <th style="width: 50px; text-align: center;">ФР</th>
                        <th style="width: 55px; text-align: center;" title="Показывать в списке при создании операции">Активна</th>
                        <th style="width: 80px;">Действия</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($categories as $cat)
                        <tr id="row-{{ $cat->cfm_cat_id }}" class="{{ $cat->is_auto ? 'auto-row' : '' }}">
                            <form method="POST" action="{{ route('cfm.editor.update', $cat->cfm_cat_id) }}" class="category-form">
                                @csrf
                                <td>
                                    <input type="text" name="cfm_cat_name" value="{{ $cat->cfm_cat_name }}" class="form-input-sm" {{ $cat->is_auto ? 'readonly' : '' }}>
                                </td>
                                <td>
                                    <select name="cfm_cat_group" class="form-input-sm" {{ $cat->is_auto ? 'disabled' : '' }}>
                                        <option value="inflows" {{ $cat->cfm_cat_group === 'inflows' ? 'selected' : '' }}>Приход</option>
                                        <option value="outflows" {{ $cat->cfm_cat_group === 'outflows' ? 'selected' : '' }}>Расход</option>
                                    </select>
                                    @if($cat->is_auto)<input type="hidden" name="cfm_cat_group" value="{{ $cat->cfm_cat_group }}">@endif
                                </td>
                                <td>
                                    <select name="cfm_cat_activities" class="form-input-sm" {{ $cat->is_auto ? 'disabled' : '' }}>
                                        <option value="operating" {{ $cat->cfm_cat_activities === 'operating' ? 'selected' : '' }}>Операционная</option>
                                        <option value="investing" {{ $cat->cfm_cat_activities === 'investing' ? 'selected' : '' }}>Инвестиционная</option>
                                        <option value="financing" {{ $cat->cfm_cat_activities === 'financing' ? 'selected' : '' }}>Финансовая</option>
                                        <option value="technical" {{ $cat->cfm_cat_activities === 'technical' ? 'selected' : '' }}>Техническая</option>
                                    </select>
                                    @if($cat->is_auto)<input type="hidden" name="cfm_cat_activities" value="{{ $cat->cfm_cat_activities }}">@endif
                                </td>
                                <td>
                                    <input type="text" name="cfm_cat_adds" value="{{ $cat->cfm_cat_adds }}" class="form-input-sm">
                                </td>
                                <td>
                                    @php
                                        $subcats = $cat->getSubcategoriesArray();
                                    @endphp
                                    <input type="text" name="subcategories" value="{{ implode(', ', $subcats) }}" class="form-input-sm" placeholder="через запятую">
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="available_for_city" value="1" {{ $cat->available_for_city ? 'checked' : '' }}>
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="available_for_mc" value="1" {{ $cat->available_for_mc ? 'checked' : '' }}>
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" name="available_for_df" value="1" {{ $cat->available_for_df ? 'checked' : '' }}>
                                </td>
                                <td style="text-align: center;" title="Показывать в списке при создании операции">
                                    <input type="checkbox" name="is_visible" value="1" {{ $cat->is_visible ? 'checked' : '' }}>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.25rem;">
                                        <button type="submit" class="btn-action-sm btn-save" title="Сохранить">
                                            {!! icon('save') !!}
                                        </button>
                            </form>
                                        @if(!$cat->is_auto && $cat->operations_count === 0)
                                            <form method="POST" action="{{ route('cfm.editor.delete', $cat->cfm_cat_id) }}" style="display: inline;" onsubmit="return confirm('Удалить статью «{{ $cat->cfm_cat_name }}»?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-action-sm btn-delete" title="Удалить">
                                                    {!! icon('delete') !!}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleNewForm() {
    const form = document.getElementById('newCategoryForm');
    form.style.display = form.style.display === 'none' ? 'block' : 'none';
}
</script>
@endpush
