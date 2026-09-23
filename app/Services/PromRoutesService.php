<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteAction;
use Illuminate\Pagination\LengthAwarePaginator;

class PromRoutesService
{
    /**
     * Получить маршруты по городу с вычисляемыми полями
     * 
     * Оптимизировано: используем withCount и withSum вместо загрузки всех actions
     */
    public function getRoutesByCity(int $cityId, int $perPage = 10): LengthAwarePaginator
    {
        $routes = Route::where('city_id', $cityId)
            ->where('is_active', true)
            ->with(['district'])
            ->withCount('actions as passes_count')
            ->withSum('actions', 'leaflets_count')
            ->withMax('actions', 'route_action_date')
            ->orderBy('route_name')
            ->paginate($perPage);
        
        // Добавляем вычисляемые поля к каждому маршруту
        $routes->getCollection()->transform(function ($route) {
            return $this->enrichRouteWithStats($route);
        });
        
        return $routes;
    }
    
    /**
     * Обогатить маршрут статистикой (использует предзагруженные агрегаты)
     */
    private function enrichRouteWithStats(Route $route): Route
    {
        // Данные уже загружены через withCount/withSum/withMax
        $route->total_leaflets = $route->actions_sum_leaflets_count ?? 0;
        $route->last_pass_date = $route->actions_max_route_action_date;
        
        // Коэффициент сложности: Квартиры / Подъезды × 100
        $route->complexity_coefficient = $route->route_entrances_count > 0
            ? (int) (($route->route_apartments_count / $route->route_entrances_count) * 100)
            : 0;
        
        // Коэффициент разноски: Всего листовок / Квартиры × 100
        $route->delivery_coefficient = $route->route_apartments_count > 0
            ? (int) (($route->total_leaflets / $route->route_apartments_count) * 100)
            : 0;
        
        return $route;
    }
    
    /**
     * Получить историю прохождения маршрута
     */
    public function getRouteHistory(Route $route, int $limit = 50): \Illuminate\Support\Collection
    {
        return RouteAction::where('route_id', $route->route_id)
            ->with(['promoter', 'maket'])
            ->orderBy('route_action_date', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($action) use ($route) {
                // Процент прохождения
                $action->pass_percentage = $route->route_apartments_count > 0
                    ? min(100, (int) (($action->leaflets_count / $route->route_apartments_count) * 100))
                    : 0;
                return $action;
            });
    }
    
    /**
     * Создать маршрут
     */
    public function create(array $data): Route
    {
        return Route::create([
            'route_name' => $data['route_name'],
            'city_id' => $data['city_id'],
            'district_id' => $data['district_id'] ?? null,
            'route_apartments_count' => $data['route_apartments_count'] ?? 0,
            'route_entrances_count' => $data['route_entrances_count'] ?? 0,
            'route_note' => $data['route_note'] ?? null,
            'is_active' => true,
        ]);
    }
    
    /**
     * Обновить маршрут
     */
    public function update(Route $route, array $data): Route
    {
        $route->update([
            'route_name' => $data['route_name'] ?? $route->route_name,
            'district_id' => array_key_exists('district_id', $data) ? $data['district_id'] : $route->district_id,
            'route_apartments_count' => $data['route_apartments_count'] ?? $route->route_apartments_count,
            'route_entrances_count' => $data['route_entrances_count'] ?? $route->route_entrances_count,
            'route_note' => array_key_exists('route_note', $data) ? $data['route_note'] : $route->route_note,
        ]);
        
        return $route->fresh();
    }
    
    /**
     * Деактивировать маршрут (мягкое удаление)
     */
    public function deactivate(Route $route): void
    {
        $route->update(['is_active' => false]);
    }
}
