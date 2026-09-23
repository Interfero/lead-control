<?php

namespace App\Http\Controllers\Management;

use App\Helpers\TimezoneHelper;
use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CityManagementController extends Controller
{
    public function index(): View
    {
        $cities = City::with('parentCity')
            ->orderBy('city_name')
            ->paginate(50);

        return view('management.cities.index', compact('cities'));
    }

    public function create(): View
    {
        $parentCities = City::orderBy('city_name')->get(['city_id', 'city_name']);
        $timezones = TimezoneHelper::uniqueIdentifiersForMskSelect();

        return view('management.cities.create', compact('parentCities', 'timezones'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'city_name' => 'required|string|max:255|unique:cities,city_name',
            'city_timezone' => 'required|string|timezone',
            'city_inn' => 'nullable|string|regex:/^\d{10}(\d{2})?$/',
            'parent_city_id' => 'nullable|exists:cities,city_id',
            'is_active' => 'nullable|boolean',
        ]);

        City::create([
            'city_name' => $validated['city_name'],
            'city_type' => 'city',
            'city_timezone' => $validated['city_timezone'],
            'city_inn' => $validated['city_inn'] ?? null,
            'parent_city_id' => $validated['parent_city_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('management.cities.index')
            ->with('success', 'Город создан');
    }

    public function edit(City $city): View
    {
        $parentCities = City::where('city_id', '!=', $city->city_id)
            ->orderBy('city_name')
            ->get(['city_id', 'city_name']);
        $timezones = TimezoneHelper::uniqueIdentifiersForMskSelect($city->city_timezone);

        return view('management.cities.edit', compact('city', 'parentCities', 'timezones'));
    }

    public function update(Request $request, City $city): RedirectResponse
    {
        $validated = $request->validate([
            'city_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cities', 'city_name')->ignore($city->city_id, 'city_id'),
            ],
            'city_timezone' => 'required|string|timezone',
            'city_inn' => 'nullable|string|regex:/^\d{10}(\d{2})?$/',
            'parent_city_id' => 'nullable|exists:cities,city_id|different:city_id',
            'is_active' => 'nullable|boolean',
        ]);

        if ((int) ($validated['parent_city_id'] ?? 0) === (int) $city->city_id) {
            return back()
                ->withErrors(['parent_city_id' => 'Нельзя выбрать этот же город как родительский'])
                ->withInput();
        }

        $city->update([
            'city_name' => $validated['city_name'],
            'city_timezone' => $validated['city_timezone'],
            'city_inn' => $validated['city_inn'] ?? null,
            'parent_city_id' => $validated['parent_city_id'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->route('management.cities.index')
            ->with('success', 'Город обновлён');
    }
}
