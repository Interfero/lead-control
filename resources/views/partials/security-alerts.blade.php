@if (auth()->check() && auth()->user()->hasAnyRole(['general_director', 'developer']) && \Illuminate\Support\Facades\Schema::hasTable('security_alerts'))
    @foreach (\App\Models\SecurityAlert::query()->whereNull('seen_at')->latest('created_at')->limit(5)->get() as $securityAlert)
        <x-ui.alert type="warning">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <strong>Безопасность:</strong> {{ $securityAlert->message }}
                    <span class="text-muted-foreground text-sm">
                        · {{ $securityAlert->created_at?->format('d.m.Y H:i') }}
                        @if (! empty($securityAlert->meta['ip'])) · IP {{ $securityAlert->meta['ip'] }} @endif
                    </span>
                </div>
                <form method="POST" action="{{ route('security-alerts.seen', $securityAlert->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-sm">Прочитано</button>
                </form>
            </div>
        </x-ui.alert>
    @endforeach
@endif
