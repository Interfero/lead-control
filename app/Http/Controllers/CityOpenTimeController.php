<?php

namespace App\Http\Controllers;

use App\Models\CityOpenTime;
use App\Services\CityOpenTimeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CityOpenTimeController extends Controller
{
    public function __construct(
        private CityOpenTimeService $service,
    ) {}

    public function index(Request $request): View
    {
        $filters = [
            'year' => $request->input('year', now()->year),
            'month' => $request->input('month', now()->month),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'city_id' => $request->input('city_id'),
        ];

        $pins = $this->service->listQuery($filters)->paginate(50)->withQueryString();
        $cities = $this->service->citiesForSelect();

        return view('city-open-times.index', compact('pins', 'cities', 'filters'));
    }

    public function create(): View
    {
        return view('city-open-times.create', [
            'cities' => $this->service->citiesForSelect(),
            'pin' => new CityOpenTime([
                'begin_date' => now(),
                'end_date' => now(),
                'time_from' => 10,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $pin = $this->service->create($validated, $request->user());

        return redirect()
            ->route('city-open-times.edit', $pin->city_open_time_id)
            ->with('success', 'Закрепление сохранено');
    }

    public function edit(CityOpenTime $cityOpenTime): View
    {
        $cityOpenTime->load(['city', 'creator', 'editor']);

        return view('city-open-times.edit', [
            'pin' => $cityOpenTime,
            'cities' => $this->service->citiesForSelect(),
        ]);
    }

    public function update(Request $request, CityOpenTime $cityOpenTime): RedirectResponse
    {
        $validated = $this->validated($request);
        $this->service->update($cityOpenTime, $validated, $request->user());

        return redirect()
            ->route('city-open-times.edit', $cityOpenTime->city_open_time_id)
            ->with('success', 'Закрепление обновлено');
    }

    public function destroy(CityOpenTime $cityOpenTime): RedirectResponse
    {
        $cityOpenTime->delete();

        return redirect()
            ->route('city-open-times.index')
            ->with('success', 'Закрепление удалено');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'begin_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:begin_date'],
            'city_id' => ['required', 'exists:cities,city_id'],
            'time_from' => ['required', 'integer', 'min:'.CityOpenTime::MIN_HOUR, 'max:'.CityOpenTime::MAX_HOUR],
            'comment' => ['nullable', 'string', 'max:4000'],
        ]);

        $begin = \Carbon\Carbon::parse($validated['begin_date']);
        $end = \Carbon\Carbon::parse($validated['end_date']);
        if ($begin->diffInDays($end) > 7) {
            throw ValidationException::withMessages([
                'end_date' => 'Разница дат должна быть не более 7 дней.',
            ]);
        }

        return $validated;
    }
}
