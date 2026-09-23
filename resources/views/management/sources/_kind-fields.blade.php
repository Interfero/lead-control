@php
    $selectedKind = old('source_kind', $sourceKind ?? \App\Models\Source::KIND_FLYER);
    $useUrl = (bool) old('use_source_url', $useSourceUrl ?? false);
    $kindLabels = \App\Models\Source::kindLabels();
@endphp

<div class="form-group">
    <label class="form-label">Категория *</label>
    <select name="source_kind" id="sourceKindSelect" class="form-input" required>
        @foreach ($kindLabels as $code => $label)
            <option value="{{ $code }}" {{ $selectedKind === $code ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-muted-foreground">
        <strong>Листовки</strong> — физические листовки компании.
        <strong>Парты</strong> — SuperPart и партнёрские источники (каталог SuperPart, при необходимости URL).
    </p>
    @error('source_kind')
        <small class="text-destructive">{{ $message }}</small>
    @enderror
</div>

<div id="partyUrlFields" class="{{ $selectedKind === \App\Models\Source::KIND_PARTY ? '' : 'hidden' }}">
    <div class="form-group">
        <label class="filter-checkbox inline-flex cursor-pointer select-none items-center gap-2">
            <input type="checkbox" name="use_source_url" id="useSourceUrlCheckbox" value="1" {{ $useUrl ? 'checked' : '' }}>
            <span>Использовать URL-ссылку</span>
        </label>
    </div>

    <div class="form-group" id="sourceUrlField" style="{{ $useUrl ? '' : 'display:none' }}">
        <label class="form-label">URL ссылки</label>
        <input type="url" name="source_url" class="form-input" value="{{ old('source_url', $sourceUrl ?? '') }}" placeholder="https://...">
        @error('source_url')
            <small class="text-destructive">{{ $message }}</small>
        @enderror
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                const kindParty = @json(\App\Models\Source::KIND_PARTY);
                const kindFlyer = @json(\App\Models\Source::KIND_FLYER);
                const kindSelect = document.getElementById('sourceKindSelect');
                const partyFields = document.getElementById('partyUrlFields');
                const useUrlCheckbox = document.getElementById('useSourceUrlCheckbox');
                const urlField = document.getElementById('sourceUrlField');
                const flyerMaketGroup = document.getElementById('flyerMaketGroup');

                function syncSourceKindUi() {
                    const kind = kindSelect ? kindSelect.value : '';
                    const isParty = kind === kindParty;
                    if (partyFields) {
                        partyFields.classList.toggle('hidden', !isParty);
                    }
                    if (flyerMaketGroup) {
                        flyerMaketGroup.classList.toggle('hidden', isParty);
                    }
                }

                function syncUrlField() {
                    if (!urlField || !useUrlCheckbox) {
                        return;
                    }
                    urlField.style.display = useUrlCheckbox.checked ? '' : 'none';
                }

                kindSelect?.addEventListener('change', syncSourceKindUi);
                useUrlCheckbox?.addEventListener('change', syncUrlField);
                syncSourceKindUi();
                syncUrlField();
            })();
        </script>
    @endpush
@endonce
