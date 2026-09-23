<?php

namespace App\Services;

use App\Models\RouteAction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PromActionsService
{
    /**
     * Получить записи разноски с группировкой по неделям
     * 
     * Паттерн: как CfmService — группировка по периодам
     */
    public function getActionsGroupedByWeek(array $filters = []): Collection
    {
        $query = $this->buildQuery($filters);
        $actions = $query->get();
        
        // Группируем по неделям
        return $actions->groupBy(function ($action) {
            return Carbon::parse($action->route_action_date)->startOfWeek()->format('Y-m-d');
        })->map(function ($weekActions, $weekStart) {
            $weekStartDate = Carbon::parse($weekStart);
            $weekEndDate = $weekStartDate->copy()->endOfWeek();
            
            return [
                'week_start' => $weekStartDate,
                'week_end' => $weekEndDate,
                'week_label' => $weekStartDate->format('d.m') . ' - ' . $weekEndDate->format('d.m.Y'),
                'total_leaflets' => $weekActions->sum('leaflets_count'),
                'actions_count' => $weekActions->count(),
                'actions' => $weekActions,
            ];
        })->sortByDesc('week_start');
    }
    
    /**
     * Получить записи с пагинацией (для отображения списком)
     */
    public function getActions(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        return $this->buildQuery($filters)->paginate($perPage);
    }
    
    /**
     * Построить запрос с фильтрами
     */
    private function buildQuery(array $filters)
    {
        $query = RouteAction::with(['city', 'promoter', 'route', 'maket', 'creator'])
            ->orderBy('route_action_date', 'desc')
            ->orderBy('route_action_id', 'desc');
        
        // Фильтр по городу
        if (!empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }
        
        // Фильтр по промоутеру
        if (!empty($filters['promoter_id'])) {
            $query->where('promoter_id', $filters['promoter_id']);
        }
        
        // Фильтр по маршруту
        if (!empty($filters['route_id'])) {
            $query->where('route_id', $filters['route_id']);
        }
        
        // Фильтр по датам
        if (!empty($filters['date_from'])) {
            $query->whereDate('route_action_date', '>=', $filters['date_from']);
        } else {
            // По умолчанию — последние 4 недели
            $query->whereDate('route_action_date', '>=', now()->subWeeks(4)->startOfWeek());
        }
        
        if (!empty($filters['date_to'])) {
            $query->whereDate('route_action_date', '<=', $filters['date_to']);
        }
        
        return $query;
    }
    
    /**
     * Получить сводку за период
     */
    public function getSummary(array $filters = []): array
    {
        $query = RouteAction::query();
        
        if (!empty($filters['city_id'])) {
            $query->where('city_id', $filters['city_id']);
        }
        if (!empty($filters['promoter_id'])) {
            $query->where('promoter_id', $filters['promoter_id']);
        }
        if (!empty($filters['route_id'])) {
            $query->where('route_id', $filters['route_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('route_action_date', '>=', $filters['date_from']);
        } else {
            // По умолчанию — последние 4 недели
            $query->whereDate('route_action_date', '>=', now()->subWeeks(4)->startOfWeek());
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('route_action_date', '<=', $filters['date_to']);
        }
        
        return [
            'total_actions' => (clone $query)->count(),
            'total_leaflets' => (clone $query)->sum('leaflets_count'),
        ];
    }
    
    /**
     * Создать запись разноски
     */
    public function create(array $data, User $creator): RouteAction
    {
        $action = new RouteAction([
            'route_action_date' => $data['route_action_date'],
            'city_id' => $data['city_id'],
            'promoter_id' => $data['promoter_id'],
            'route_id' => $data['route_id'],
            'maket_id' => $data['maket_id'] ?? null,
            'leaflets_count' => $data['leaflets_count'] ?? 0,
            'route_action_status' => 'done',
            'route_action_note' => $data['route_action_note'] ?? null,
        ]);
        $action->created_by = $creator->user_id;
        $action->save();
        
        return $action;
    }
    
    /**
     * Обновить запись разноски
     */
    public function update(RouteAction $action, array $data): RouteAction
    {
        $action->update([
            'route_action_date' => $data['route_action_date'] ?? $action->route_action_date,
            'promoter_id' => $data['promoter_id'] ?? $action->promoter_id,
            'route_id' => $data['route_id'] ?? $action->route_id,
            'maket_id' => array_key_exists('maket_id', $data) ? $data['maket_id'] : $action->maket_id,
            'leaflets_count' => $data['leaflets_count'] ?? $action->leaflets_count,
            'route_action_note' => array_key_exists('route_action_note', $data) 
                ? $data['route_action_note'] 
                : $action->route_action_note,
        ]);
        
        return $action->fresh();
    }
    
    /**
     * Удалить запись
     */
    public function delete(RouteAction $action): void
    {
        $action->delete();
    }
}
