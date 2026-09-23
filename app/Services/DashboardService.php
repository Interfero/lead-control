<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * Получить статистику для пользователя
     */
    public function getStatsForUser(User $user): array
    {
        $cityIds = $user->hasRole('developer') 
            ? null 
            : $user->cities->pluck('city_id')->toArray();
        
        return [
            'today' => $this->getTodayStats($cityIds),
            'week' => $this->getWeekStats($cityIds),
            'month' => $this->getMonthStats($cityIds),
            'pending_orders' => $this->getPendingOrders($cityIds),
            'recent_orders' => $this->getRecentOrders($cityIds),
        ];
    }
    
    /**
     * Статистика за сегодня
     */
    private function getTodayStats(?array $cityIds): array
    {
        $query = Order::whereDate('order_created_at', Carbon::today());
        
        if ($cityIds) {
            $query->whereHas('address', fn($q) => $q->whereIn('city_id', $cityIds));
        }
        
        $orders = $query->get();
        $completed = $orders->where('order_status', 'completed');
        
        return [
            'new_orders' => $orders->count(),
            'completed_orders' => $completed->count(),
            'revenue' => $completed->sum('amount_paid') - $completed->sum('amount_comp'),
        ];
    }
    
    /**
     * Статистика за неделю
     */
    private function getWeekStats(?array $cityIds): array
    {
        $query = Order::whereBetween('order_created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ]);
        
        if ($cityIds) {
            $query->whereHas('address', fn($q) => $q->whereIn('city_id', $cityIds));
        }
        
        $orders = $query->get();
        $completed = $orders->where('order_status', 'completed');
        
        return [
            'total_orders' => $orders->count(),
            'completed_orders' => $completed->count(),
            'revenue' => $completed->sum('amount_paid') - $completed->sum('amount_comp'),
            'conversion' => $orders->count() > 0 
                ? round($completed->count() / $orders->count() * 100) 
                : 0,
        ];
    }
    
    /**
     * Статистика за месяц
     */
    private function getMonthStats(?array $cityIds): array
    {
        $query = Order::whereMonth('order_created_at', Carbon::now()->month)
            ->whereYear('order_created_at', Carbon::now()->year);
        
        if ($cityIds) {
            $query->whereHas('address', fn($q) => $q->whereIn('city_id', $cityIds));
        }
        
        $orders = $query->get();
        $completed = $orders->where('order_status', 'completed');
        
        return [
            'total_orders' => $orders->count(),
            'completed_orders' => $completed->count(),
            'revenue' => $completed->sum('amount_paid') - $completed->sum('amount_comp'),
        ];
    }
    
    /**
     * Заказы, требующие внимания
     */
    private function getPendingOrders(?array $cityIds): Collection
    {
        $query = Order::whereIn('order_status', ['pending', 'unassigned'])
            ->with(['address.city', 'persons', 'source'])
            ->orderBy('order_created_at');
        
        if ($cityIds) {
            $query->whereHas('address', fn($q) => $q->whereIn('city_id', $cityIds));
        }
        
        return $query->limit(10)->get();
    }
    
    /**
     * Последние заказы
     */
    private function getRecentOrders(?array $cityIds): Collection
    {
        $query = Order::with(['address.city', 'master', 'persons', 'source'])
            ->orderByDesc('order_created_at');
        
        if ($cityIds) {
            $query->whereHas('address', fn($q) => $q->whereIn('city_id', $cityIds));
        }
        
        return $query->limit(10)->get();
    }
}
