<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="form-group">
        <label class="form-label">Дата, от *</label>
        <input type="date" name="begin_date" class="form-input" required
            value="{{ old('begin_date', $pin->begin_date?->format('Y-m-d')) }}">
        @error('begin_date')
            <small class="text-destructive">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label class="form-label">Дата, по *</label>
        <input type="date" name="end_date" class="form-input" required
            value="{{ old('end_date', $pin->end_date?->format('Y-m-d')) }}">
        @error('end_date')
            <small class="text-destructive">{{ $message }}</small>
        @enderror
    </div>
</div>

<p class="mb-4 text-xs text-muted-foreground">Разница дат — не более 7 дней.</p>

<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="form-group">
        <label class="form-label">Город *</label>
        <select name="city_id" class="form-input" required>
            <option value="">Выберите город</option>
            @foreach ($cities as $city)
                <option value="{{ $city->city_id }}" {{ (string) old('city_id', $pin->city_id) === (string) $city->city_id ? 'selected' : '' }}>
                    {{ $city->city_name }}
                </option>
            @endforeach
        </select>
        @error('city_id')
            <small class="text-destructive">{{ $message }}</small>
        @enderror
    </div>

    <div class="form-group">
        <label class="form-label">Начало работы (час) *</label>
        <input type="number" name="time_from" class="form-input" required min="{{ \App\Models\CityOpenTime::MIN_HOUR }}" max="{{ \App\Models\CityOpenTime::MAX_HOUR }}"
            value="{{ old('time_from', $pin->time_from ?? 10) }}">
        @error('time_from')
            <small class="text-destructive">{{ $message }}</small>
        @enderror
    </div>
</div>

<div class="form-group">
    <label class="form-label">Комментарий</label>
    <textarea name="comment" class="form-input" rows="5" maxlength="4000">{{ old('comment', $pin->comment) }}</textarea>
    @error('comment')
        <small class="text-destructive">{{ $message }}</small>
    @enderror
</div>
