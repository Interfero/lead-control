<?php

namespace App\Http\Controllers;

use App\Models\Source;
use App\Services\DispatcherDashboardService;
use Illuminate\Http\Request;

class DispatcherDashboardController extends Controller
{
    public function __construct(
        private DispatcherDashboardService $dashboard,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();

        if (! $user->hasAnyRole(['developer', 'call_center', 'senior_dispatcher', 'regional_director', 'general_director'])) {
            abort(403);
        }

        $filterCityId = $request->filled('city_id') ? (int) $request->city_id : null;
        $filterMasterId = $request->filled('master_id') ? (int) $request->master_id : null;
        $filterSourceId = $request->get('source_id');
        $filterSourceFormat = $request->get('source_format');

        $stats = $this->dashboard->getStats($user, $filterCityId, $filterMasterId, $filterSourceId, $filterSourceFormat);
        $closedOrders = $this->dashboard->getClosedOrders($user, $filterCityId, $filterMasterId, $filterSourceId, $filterSourceFormat);
        $cities = $this->dashboard->getFilterCities($user);
        $masters = $this->dashboard->getFilterMasters($user, $filterCityId);
        $sources = Source::with('city')->where('is_active', true)->orderBy('source_name')->get();

        $showCallCenterActiveBoard = $user->isDispatcher();
        $activeOrders = $showCallCenterActiveBoard ? $this->dashboard->getActiveOrdersForCallCenter() : collect();
        $statusLabels = \App\Models\Order::getStatusLabels();

        return view('dispatcher.dashboard', compact(
            'stats', 'closedOrders', 'cities', 'masters', 'sources',
            'filterCityId', 'filterMasterId', 'filterSourceId', 'filterSourceFormat',
            'showCallCenterActiveBoard', 'activeOrders', 'statusLabels',
        ));
    }
}
