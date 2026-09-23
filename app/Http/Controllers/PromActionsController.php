<?php

namespace App\Http\Controllers;

use App\Models\RouteAction;
use App\Models\City;
use App\Models\Route;
use App\Models\RouteMaket;
use App\Models\Promoter;
use App\Services\PromActionsService;
use Illuminate\Http\Request;

class PromActionsController extends Controller
{
    public function __construct(
        private PromActionsService $actionsService
    ) {}
    
    /**
     * Список разноски
     */
    public function index(Request $request)
    {
        $filters = [
            'city_id' => $request->input('city_id'),
            'promoter_id' => $request->input('promoter_id'),
            'route_id' => $request->input('route_id'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ];
        
        // Данные с группировкой по неделям
        $groupedActions = $this->actionsService->getActionsGroupedByWeek($filters);
        
        // Сводка
        $summary = $this->actionsService->getSummary($filters);
        
        // Справочники для фильтров
        $cities = City::where('is_active', true)->orderBy('city_name')->get();
        $promoters = Promoter::where('promoter_status', 'active')
            ->orderBy('promoter_name')
            ->get();
        $routes = Route::where('is_active', true)->orderBy('route_name')->get();
        $makets = RouteMaket::where('is_active', true)->orderBy('maket_name')->get();
        
        return view('prom.actions.index', compact(
            'groupedActions', 'summary', 'cities', 'promoters', 'routes', 'makets', 'filters'
        ));
    }
    
    /**
     * Сохранить запись
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'route_action_date' => 'required|date',
            'city_id' => 'required|exists:cities,city_id',
            'promoter_id' => 'required|exists:promoters,promoter_id',
            'route_id' => 'required|exists:routes,route_id',
            'maket_id' => 'nullable|exists:route_makets,maket_id',
            'leaflets_count' => 'required|integer|min:0',
            'route_action_note' => 'nullable|string|max:500',
        ]);
        
        $this->actionsService->create($validated, auth()->user());
        
        return redirect()
            ->route('prom.actions')
            ->with('success', 'Запись разноски добавлена');
    }
    
    /**
     * Форма редактирования
     */
    public function edit(RouteAction $action)
    {
        $cities = City::where('is_active', true)->get();
        $promoters = Promoter::where('promoter_status', 'active')
            ->orderBy('promoter_name')
            ->get();
        $routes = Route::where('is_active', true)->orderBy('route_name')->get();
        $makets = RouteMaket::where('is_active', true)->orderBy('maket_name')->get();
        
        return view('prom.actions.edit', compact('action', 'cities', 'promoters', 'routes', 'makets'));
    }
    
    /**
     * Обновить запись
     */
    public function update(Request $request, RouteAction $action)
    {
        $validated = $request->validate([
            'route_action_date' => 'required|date',
            'promoter_id' => 'required|exists:promoters,promoter_id',
            'route_id' => 'required|exists:routes,route_id',
            'maket_id' => 'nullable|exists:route_makets,maket_id',
            'leaflets_count' => 'required|integer|min:0',
            'route_action_note' => 'nullable|string|max:500',
        ]);
        
        $this->actionsService->update($action, $validated);
        
        return redirect()
            ->route('prom.actions')
            ->with('success', 'Запись разноски обновлена');
    }
    
    /**
     * Удалить запись
     */
    public function destroy(RouteAction $action)
    {
        $this->actionsService->delete($action);
        
        return redirect()
            ->route('prom.actions')
            ->with('success', 'Запись разноски удалена');
    }
}
