<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Helpers\PhoneHelper;
use App\Models\City;
use App\Models\FlyerMaket;
use App\Models\Promoter;
use App\Models\Source;
use App\Services\PartnerApiService;
use App\Services\ReportCsvExporter;
use App\Services\SourceAttributionService;
use App\Services\SourceImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SourceManagementController extends Controller
{
    private const SOURCE_MANAGER_ROLES = ['developer', 'general_director', 'senior_dispatcher'];

    public function index(Request $request): View
    {
        $superpartOnly = $request->boolean('superpart');
        $kind = $request->input('kind');
        $withoutPhone = $request->boolean('without_phone');
        $cityFilterId = $request->input('city_id');

        $baseQuery = Source::query()->with(['city', 'flyerMaket']);

        if ($superpartOnly) {
            $baseQuery->where('available_for_superpart', true);
        }

        if (in_array($kind, Source::kindCodes(), true)) {
            $baseQuery->where('source_kind', $kind);
        }

        if ($withoutPhone) {
            $baseQuery->where('is_active', true)
                ->where(function ($q) {
                    $q->whereNull('source_phone')->orWhere('source_phone', '');
                })
                ->requiresDidPhone();
        }

        if ($cityFilterId) {
            $baseQuery->where('city_id', $cityFilterId);
        }

        $groupedView = ! $superpartOnly && ! $kind && ! $withoutPhone && ! $cityFilterId;

        $superpartSources = collect();
        $sourcesByCity = collect();
        $sources = null;

        if ($groupedView) {
            $superpartSources = (clone $baseQuery)
                ->where('available_for_superpart', true)
                ->orderBy('source_name')
                ->get();

            $sourcesByCity = (clone $baseQuery)
                ->where('available_for_superpart', false)
                ->get()
                ->sortBy(fn (Source $source) => [
                    $source->city?->city_name ?? 'ЯЯЯ',
                    $source->source_name,
                ])
                ->groupBy(fn (Source $source) => $source->city?->city_name ?? 'Без города')
                ->sortKeys();
        } else {
            $sources = (clone $baseQuery)->orderBy('source_name')->paginate(50)->withQueryString();
        }

        $gapStats = app(SourceAttributionService::class)->gapStats();
        $ordersWithoutSource = \App\Models\Order::query()->whereNull('source_id')->count();
        $cities = City::query()->orderBy('city_name')->get(['city_id', 'city_name']);

        return view('management.sources.index', compact(
            'sources',
            'superpartSources',
            'sourcesByCity',
            'groupedView',
            'superpartOnly',
            'kind',
            'withoutPhone',
            'cityFilterId',
            'cities',
            'gapStats',
            'ordersWithoutSource'
        ));
    }

    public function create(): View
    {
        return view('management.sources.create', $this->formLists());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedSource($request);

        $source = Source::create($validated + [
            'is_active' => $request->boolean('is_active'),
            'available_for_superpart' => $this->resolveAvailableForSuperpart($request, $validated['source_kind']),
        ]);

        \App\Jobs\NotifySuperpartSourceUpsertJob::dispatch((int) $source->source_id);

        return redirect()->route('management.sources.index')
            ->with('success', 'Источник создан');
    }

    public function edit(Source $source): View
    {
        $attachedOrders = $source->orders()
            ->with(['address.city'])
            ->orderByDesc('order_id')
            ->limit(200)
            ->get(['order_id', 'order_status', 'datetime_order', 'address_id']);

        return view('management.sources.edit', array_merge(
            ['source' => $source, 'attachedOrders' => $attachedOrders],
            $this->formLists($source)
        ));
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $validated = $this->validatedSource($request, $source);

        $beforeAvailable = (bool) $source->available_for_superpart;
        $beforePartnerId = (int) ($source->superpart_partner_id ?? 0);

        $source->update($validated + [
            'is_active' => $request->boolean('is_active'),
            'available_for_superpart' => $this->resolveAvailableForSuperpart($request, $validated['source_kind']),
        ]);

        $source->refresh();
        $afterAvailable = (bool) $source->available_for_superpart;
        $afterPartnerId = (int) ($source->superpart_partner_id ?? 0);

        \App\Jobs\NotifySuperpartSourceUpsertJob::dispatch((int) $source->source_id);

        // FR-SRC-04: смена SP-признака/партнёра → re-enqueue всех заявок источника.
        if ($beforeAvailable !== $afterAvailable || $beforePartnerId !== $afterPartnerId) {
            \App\Jobs\EnqueueSuperpartSnapshotsForSourceJob::dispatch((int) $source->source_id);
        }

        return redirect()->route('management.sources.index')
            ->with('success', 'Источник сохранён');
    }

    public function destroy(Source $source): RedirectResponse
    {
        if (! auth()->user()->hasAnyRole(self::SOURCE_MANAGER_ROLES)) {
            abort(403);
        }

        $ordersCount = $source->orders()->count();

        $promotersCount = Promoter::query()->where('source_id', $source->source_id)->count();
        if ($promotersCount > 0) {
            return redirect()->route('management.sources.edit', $source)
                ->with('error', "Нельзя удалить источник: к нему привязано записей промоутеров: {$promotersCount}.");
        }

        if ($source->available_for_superpart || $source->superpart_local_source_id) {
            \App\Jobs\NotifySuperpartSourceDeleteJob::dispatch([
                'source_id' => $source->source_id,
                'source_name' => $source->source_name,
                'superpart_local_source_id' => $source->superpart_local_source_id,
                'superpart_partner_id' => $source->superpart_partner_id,
                'is_active' => false,
                'available_for_superpart' => false,
                'deleted' => true,
            ]);
        }

        $sourceId = (int) $source->source_id;
        DB::transaction(function () use ($source, $sourceId, $ordersCount): void {
            if ($ordersCount > 0) {
                DB::table('orders')->where('source_id', $sourceId)->update(['source_id' => null]);
            }
            if (\Illuminate\Support\Facades\Schema::hasTable('calls')) {
                DB::table('calls')->where('source_id', $sourceId)->update(['source_id' => null]);
            }
            $source->delete();
        });

        $message = 'Источник удалён';
        if ($ordersCount > 0) {
            $message .= " (источник снят с {$ordersCount} заказов)";
        }

        return redirect()->route('management.sources.index')
            ->with('success', $message);
    }

    public function importForm(): View
    {
        return view('management.sources.import');
    }

    public function import(Request $request, SourceImportService $importService): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ], [
            'file.required' => 'Выберите CSV-файл',
            'file.mimes' => 'Допустимый формат: CSV',
        ]);

        $result = $importService->importFromCsv($request->file('file'));

        $message = "Импорт завершён: создано {$result['created']}, обновлено {$result['updated']}, пропущено {$result['skipped']}.";
        if ($result['errors'] !== []) {
            $preview = implode(' ', array_slice($result['errors'], 0, 5));
            if (count($result['errors']) > 5) {
                $preview .= ' …';
            }

            return redirect()->route('management.sources.index')
                ->with('warning', $message.' Ошибки: '.$preview);
        }

        return redirect()->route('management.sources.index')->with('success', $message);
    }

    public function importTemplate(ReportCsvExporter $csvExporter): StreamedResponse
    {
        $rows = app(SourceImportService::class)->templateRows();
        $headers = array_shift($rows);

        return $csvExporter->download('sources_import_template.csv', $headers, $rows);
    }

    /**
     * @return array{cities: \Illuminate\Database\Eloquent\Collection, flyerMakets: \Illuminate\Database\Eloquent\Collection}
     */
    private function formLists(?Source $forSource = null): array
    {
        $cities = City::query()->orderBy('city_name')->get(['city_id', 'city_name']);

        $maketQuery = FlyerMaket::query()->orderBy('flyer_maket_name');
        if ($forSource && $forSource->flyer_maket_id) {
            $maketQuery->where(function ($q) use ($forSource) {
                $q->where('is_active', true)
                    ->orWhere('flyer_maket_id', $forSource->flyer_maket_id);
            });
        } else {
            $maketQuery->where('is_active', true);
        }
        $flyerMakets = $maketQuery->get(['flyer_maket_id', 'flyer_maket_name', 'is_active']);

        return compact('cities', 'flyerMakets');
    }

    private function validatedSource(Request $request, ?Source $source = null): array
    {
        if ($request->exists('source_phone')) {
            $raw = trim((string) $request->input('source_phone', ''));
            if ($raw === '') {
                $request->merge(['source_phone' => null]);
            } else {
                $normalized = PhoneHelper::normalize($raw);
                if ($normalized === '') {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'source_phone' => 'Укажите корректный номер линии (цифры).',
                    ]);
                }
                $request->merge(['source_phone' => $normalized]);
            }
        }

        $phoneRule = Rule::unique('sources', 'source_phone');
        if ($source) {
            $phoneRule->ignore($source->source_id, 'source_id');
        }

        $validated = $request->validate([
            'source_name' => 'required|string|max:255',
            'source_phone' => ['nullable', 'string', 'max:32', $phoneRule],
            'source_format' => 'nullable|in:online,offline',
            'source_kind' => 'required|in:'.implode(',', Source::kindCodes()),
            'source_url' => 'nullable|url|max:2048',
            'city_id' => 'nullable|exists:cities,city_id',
            'flyer_maket_id' => 'nullable|exists:flyer_makets,flyer_maket_id',
        ]);

        $validated['source_phone'] = ! empty($validated['source_phone'])
            ? $validated['source_phone']
            : null;

        $useUrl = $request->boolean('use_source_url');
        if ($validated['source_kind'] === Source::KIND_PARTY && $useUrl) {
            $request->validate([
                'source_url' => 'required|url|max:2048',
            ]);
            $validated['use_source_url'] = true;
            $validated['source_url'] = $validated['source_url'] ?? null;
        } else {
            $validated['use_source_url'] = false;
            $validated['source_url'] = null;
        }

        return $validated;
    }

    private function resolveAvailableForSuperpart(Request $request, string $kind): bool
    {
        unset($kind);

        return $request->boolean('available_for_superpart');
    }
}
