<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardService $dashboardService
    ) {}
    
    public function index()
    {
        $user = auth()->user();
        
        // Для мастера — специальная страница
        if ($user->hasRole('master') && !$user->hasAnyRole(['developer', 'branch_head'])) {
            return view('dashboard.master');
        }
        
        $stats = $this->dashboardService->getStatsForUser($user);
        
        return view('dashboard.index', compact('stats'));
    }
}
