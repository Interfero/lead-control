<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\City;
use App\Models\District;
use App\Services\PromRoutesService;
use Illuminate\Http\Request;

class PromRoutesController extends Controller
{
    public function __construct(
        private PromRoutesService $routesService
    ) {}
    
    /**
     * Список маршрутов
     */
    public function index(Request $request)
    {
        $cities = City::where('is_active', true)->get();
        
        // Город по умолчанию — первый активный
        $cityId = $request->input('city_id');
        if (!$cityId && $cities->isNotEmpty()) {
            $cityId = $cities->first()->city_id;
        }
        
        $routes = collect();
        $districts = collect();
        
        if ($cityId) {
            $routes = $this->routesService->getRoutesByCity($cityId);
            $districts = District::where('city_id', $cityId)
                ->where('is_active', true)
                ->orderBy('district_name')
                ->get();
        }
        
        return view('prom.routes.index', compact('routes', 'cities', 'districts', 'cityId'));
    }
    
    /**
     * Создать маршрут
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_name' => 'required|string|max:255|unique:routes,route_name',
            'city_id' => 'required|exists:cities,city_id',
            'district_id' => 'nullable|exists:districts,district_id',
            'route_apartments_count' => 'nullable|integer|min:0',
            'route_entrances_count' => 'nullable|integer|min:0',
            'route_note' => 'nullable|string|max:500',
        ]);
        
        $this->routesService->create($validated);
        
        return redirect()
            ->route('prom.routes', ['city_id' => $validated['city_id']])
            ->with('success', 'Маршрут создан');
    }
    
    /**
     * Обновить маршрут
     */
    public function update(Request $request, Route $route)
    {
        $validated = $request->validate([
            'route_name' => 'required|string|max:255|unique:routes,route_name,' . $route->route_id . ',route_id',
            'district_id' => 'nullable|exists:districts,district_id',
            'route_apartments_count' => 'nullable|integer|min:0',
            'route_entrances_count' => 'nullable|integer|min:0',
            'route_note' => 'nullable|string|max:500',
        ]);
        
        $this->routesService->update($route, $validated);
        
        return redirect()
            ->route('prom.routes', ['city_id' => $route->city_id])
            ->with('success', 'Маршрут обновлён');
    }
    
    /**
     * История прохождения (AJAX для модального окна)
     */
    public function history(Route $route)
    {
        $history = $this->routesService->getRouteHistory($route);
        
        return response()->json([
            'route' => $route,
            'history' => $history,
        ]);
    }
    
    /**
     * Удалить (деактивировать) маршрут
     */
    public function destroy(Route $route)
    {
        $cityId = $route->city_id;
        $this->routesService->deactivate($route);
        
        return redirect()
            ->route('prom.routes', ['city_id' => $cityId])
            ->with('success', 'Маршрут удалён');
    }
}
