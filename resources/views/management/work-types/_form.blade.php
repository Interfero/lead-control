{{-- $workType — null при создании. code редактируется только при создании (см. контроллер). --}}
<div class="form-group">
    <label class="form-label">Название *</label>
    <input type="text" name="name" class="form-input" value="{{ old('name', $workType->name ?? '') }}" required>
    @error('name')
        <small class="text-destructive">{{ $message }}</small>
    @enderror
</div>

<div class="form-group">
    <label class="form-label">Код *</label>
    @if ($workType)
        <input type="text" class="form-input bg-muted cursor-default" value="{{ $workType->code }}" readonly>
        <small class="text-muted-foreground">Код нельзя менять после создания — он уже используется в заявках и в SuperPart.</small>
    @else
        <input type="text" name="code" class="form-input" value="{{ old('code') }}" placeholder="например, vacuum_cleaner" required>
        <small class="text-muted-foreground">Латиница, цифры, дефис и подчёркивание. Дальше не меняется.</small>
        @error('code')
            <small class="text-destructive">{{ $message }}</small>
        @enderror
    @endif
</div>

<div class="form-group">
    <label class="form-label">Профильность *</label>
    <select name="order_core" class="form-input" required>
        @foreach (['core' => 'Профильный', 'non_core' => 'Непрофильный', 'other' => 'Прочий'] as $code => $label)
            <option value="{{ $code }}" {{ old('order_core', $workType->order_core ?? 'non_core') === $code ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>
    @error('order_core')
        <small class="text-destructive">{{ $message }}</small>
    @enderror
</div>

<div class="grid grid-cols-1 gap-4 md:grid-cols-3">
    <div class="form-group">
        <label class="form-label">% выплаты партнёру *</label>
        <input type="number" name="partner_rate" class="form-input" step="0.01" min="0" max="100"
               value="{{ old('partner_rate', $workType->partner_rate ?? 30) }}" required>
        @error('partner_rate')
            <small class="text-destructive">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label class="form-label">Потолок выплаты партнёру, ₽ *</label>
        <input type="number" name="partner_cap" class="form-input" step="1" min="0"
               value="{{ old('partner_cap', $workType->partner_cap ?? 2500) }}" required>
        @error('partner_cap')
            <small class="text-destructive">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label class="form-label">% выплаты мастеру</label>
        <input type="number" name="master_rate" class="form-input" step="0.01" min="0" max="100"
               value="{{ old('master_rate', $workType->master_rate ?? 0) }}">
        <small class="text-muted-foreground">Пока не используется в расчёте зарплаты, задел на будущее.</small>
        @error('master_rate')
            <small class="text-destructive">{{ $message }}</small>
        @enderror
    </div>
</div>

<div class="form-group">
    <label class="form-label">Порядок в списке</label>
    <input type="number" name="sort_order" class="form-input" step="1" min="0"
           value="{{ old('sort_order', $workType->sort_order ?? 0) }}">
    @error('sort_order')
        <small class="text-destructive">{{ $message }}</small>
    @enderror
</div>

<div class="form-group">
    <label class="filter-checkbox inline-flex cursor-pointer select-none items-center gap-2">
        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $workType->is_active ?? true) ? 'checked' : '' }}>
        <span>Активен (виден в формах заявок и отдаётся в SuperPart)</span>
    </label>
</div>
