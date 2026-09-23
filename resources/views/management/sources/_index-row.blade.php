<tr
    class="cursor-pointer hover:bg-muted/60 @if(! $source->source_phone && $source->is_active) bg-amber-500/10 @endif"
    title="Открыть редактирование"
    tabindex="0"
    role="link"
    onclick="window.location='{{ e($editUrl ?? route('management.sources.edit', $source->source_id)) }}'"
    onkeydown="if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location='{{ e($editUrl ?? route('management.sources.edit', $source->source_id)) }}'; }"
>
    @php
        $formatLabel = $formatLabel ?? match ($source->source_format) {
            \App\Models\Source::FORMAT_ONLINE => 'онлайн',
            \App\Models\Source::FORMAT_OFFLINE => 'офлайн',
            default => null,
        };
    @endphp
    <td>{{ $source->source_id }}</td>
    <td><strong>{{ $source->source_name }}</strong></td>
    <td>{{ $source->kindLabel() ?? '—' }}</td>
    <td>{{ $source->source_phone ?? '—' }}</td>
    <td>{{ $formatLabel ?? '—' }}</td>
    <td>{{ $source->city?->city_name ?? '—' }}</td>
    <td class="max-w-[10rem] truncate">
        @if ($source->use_source_url && $source->source_url)
            <a href="{{ $source->source_url }}" target="_blank" rel="noopener" class="text-primary" onclick="event.stopPropagation()">ссылка</a>
        @else
            —
        @endif
    </td>
    <td>
        @if ($source->available_for_superpart)
            <span class="inline-flex items-center rounded-md bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary">Да</span>
        @else
            <span class="text-muted-foreground">—</span>
        @endif
    </td>
    <td>{{ $source->superpart_partner_id ?? '—' }}</td>
    <td>
        @if ($source->is_active)
            <span class="inline-flex items-center rounded-md bg-primary/15 px-2 py-0.5 text-xs font-medium text-primary">Активен</span>
        @else
            <span class="inline-flex items-center rounded-md bg-muted px-2 py-0.5 text-xs font-medium text-muted-foreground">Неактивен</span>
        @endif
    </td>
</tr>
