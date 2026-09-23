<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Services\MasterSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MasterSettlementController extends Controller
{
    public function __construct(
        private MasterSettlementService $settlementService,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $cityFilter = $request->filled('city_id') ? (int) $request->city_id : null;

        $masters = $this->settlementService->mastersForSettlement($user, $cityFilter);
        $totalAmount = (int) $masters->sum('amount_to_pay');

        $cityIds = $user->cityIdsForScope();
        $citiesQuery = City::query()->where('is_active', true)->orderBy('city_name');
        if ($cityIds !== null) {
            $citiesQuery->whereIn('city_id', $cityIds);
        }
        $cities = $citiesQuery->get(['city_id', 'city_name']);

        return view('cfm.master-settlement.index', compact('masters', 'totalAmount', 'cities', 'cityFilter'));
    }

    public function show(Request $request, int $user_id): View
    {
        $sort = $request->get('sort') === 'order_closed_at' ? 'order_closed_at' : null;
        $dir = strtolower((string) $request->get('dir', 'desc'));
        if (! in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'desc';
        }

        $detail = $this->settlementService->masterDetail(auth()->user(), $user_id, $sort, $dir);

        return view('cfm.master-settlement.show', [
            'master' => $detail['master'],
            'orders' => $detail['orders'],
            'totalToPay' => $detail['total_to_pay'],
            'sort' => $sort ?? 'order_closed_at',
            'sortDir' => $dir,
        ]);
    }

    public function processMaster(Request $request, int $user_id): RedirectResponse
    {
        $validated = $request->validate([
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'integer|exists:orders,order_id',
        ]);

        $count = $this->settlementService->markHandedOver(auth()->user(), $validated['order_ids']);

        // Нельзя вызывать ->withQueryString() на RedirectResponse: __call трактует
        // это как with('query_string', $argv[0]) и падает с Undefined array key 0.
        $query = array_filter([
            'sort' => $request->query('sort'),
            'dir' => $request->query('dir'),
        ], fn ($v) => $v !== null && $v !== '');

        return redirect()
            ->route('cfm.master-settlement.show', array_merge(['user_id' => $user_id], $query))
            ->with('success', "Отмечено заказов: {$count}");
    }

    public function processMasters(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'master_ids' => 'required|array|min:1',
            'master_ids.*' => 'integer|exists:users,user_id',
        ]);

        $count = $this->settlementService->markHandedOverForMasters(
            auth()->user(),
            $validated['master_ids']
        );

        return redirect()
            ->route('cfm.master-settlement.index', $request->only('city_id'))
            ->with('success', "Сдано по заказам: {$count}");
    }
}
