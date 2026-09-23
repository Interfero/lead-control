<?php

namespace App\Http\Controllers;

use App\Desk\Services\DeskOrderService;
use App\Desk\Services\DeskSyncService;
use App\Models\CrmConnection;
use App\Models\DeskLog;
use App\Models\DeskOrderCache;
use App\Models\DeskStat;
use App\Models\Order;
use Illuminate\Http\Request;
use Throwable;

class DeskController extends Controller
{
    public function __construct(
        protected DeskOrderService $orders,
        protected DeskSyncService $sync
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = $this->orders->baseQueryForUser($user);

        if ($request->filled('city_id')) {
            $cityId = (int) $request->city_id;
            if (! $user->hasAccessToCity($cityId)) {
                abort(403);
            }
            $query->where('city_id', $cityId);
        }

        if ($request->filled('status')) {
            $statuses = (array) $request->input('status');
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('master')) {
            $query->where('master_name', 'like', '%'.$request->master.'%');
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($builder) use ($q) {
                $builder->where('address', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('client_name', 'like', "%{$q}%")
                    ->orWhere('external_id', 'like', "%{$q}%");
            });
        }

        if ($request->filled('crm_id')) {
            $query->where('crm_id', (int) $request->crm_id);
        }

        $orders = $query
            ->orderByDesc('created_at_local')
            ->paginate(30)
            ->withQueryString();

        $connections = CrmConnection::query()->orderBy('id')->get();
        $counts = $this->orders->statusCounts($user);
        $cities = $user->accessibleCities()->orderBy('city_name')->get(['city_id', 'city_name']);
        $closedCount = DeskStat::getValue('closed_via_desk');
        $statusLabels = DeskOrderCache::unifiedStatusLabels();
        $typeLabels = DeskOrderCache::orderTypeLabels();
        $rawStatusLabels = Order::getStatusLabels();

        return view('desk.index', compact(
            'orders',
            'connections',
            'counts',
            'cities',
            'closedCount',
            'statusLabels',
            'typeLabels',
            'rawStatusLabels'
        ));
    }

    public function show(Request $request, int $id)
    {
        $cached = $this->orders->findForUser($request->user(), $id);
        $stale = false;

        try {
            $cached = $this->sync->refreshCachedOrder($cached);
        } catch (Throwable $e) {
            $stale = true;
            if (str_contains($e->getMessage(), 'закрыт') || str_contains($e->getMessage(), 'не найден')) {
                return response()->json(['error' => $e->getMessage(), 'gone' => true], 410);
            }
        }

        $settable = array_keys(Order::getStatusLabels());
        $closed = ['completed', 'cancelled_cc', 'cancelled_city', 'rejected'];
        $settable = array_values(array_diff($settable, $closed));

        return response()->json([
            'order' => $cached,
            'stale' => $stale,
            'settable_statuses' => $settable,
            'status_labels' => Order::getStatusLabels(),
            'type_labels' => DeskOrderCache::orderTypeLabels(),
            'can_close' => $request->user()->hasAnyRole([
                'developer', 'senior_dispatcher', 'senior_manager', 'branch_head', 'regional_director', 'general_director',
            ]),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $cached = $this->orders->findForUser($request->user(), $id);

        $validated = $request->validate([
            'raw_status' => 'nullable|string|max:64',
            'paid_amount' => 'nullable|integer|min:0',
            'parts_amount' => 'nullable|integer|min:0',
            'comment' => 'nullable|string|max:2000',
        ]);

        try {
            $updated = $this->orders->update($cached, $validated, $request->user());

            return response()->json([
                'ok' => true,
                'order' => $updated,
                'message' => 'Сохранено в CRM',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function close(Request $request, int $id)
    {
        if (! $request->user()->hasAnyRole([
            'developer', 'senior_dispatcher', 'senior_manager', 'branch_head', 'regional_director', 'general_director',
        ])) {
            abort(403);
        }

        $cached = $this->orders->findForUser($request->user(), $id);

        try {
            // Подтянуть свежие поля перед валидацией закрытия
            $cached = $this->sync->refreshCachedOrder($cached);
            $this->orders->close($cached, $request->user());

            return response()->json([
                'ok' => true,
                'message' => 'Заказ закрыт',
                'closed_count' => DeskStat::getValue('closed_via_desk'),
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function sync(Request $request, int $crmId)
    {
        $connection = CrmConnection::query()->findOrFail($crmId);

        try {
            $result = $this->sync->syncConnection($connection, full: true);

            return redirect()
                ->route('desk.index')
                ->with('success', "{$connection->name}: обновлено {$result['upserted']}, удалено {$result['removed']}");
        } catch (Throwable $e) {
            return redirect()
                ->route('desk.index')
                ->with('error', "Ошибка синхронизации {$connection->name}: ".$e->getMessage());
        }
    }

    public function logs(Request $request)
    {
        if (! $request->user()->hasRole('developer')) {
            abort(403);
        }

        $logs = DeskLog::query()
            ->with('connection')
            ->when($request->filled('crm_id'), fn ($q) => $q->where('crm_id', (int) $request->crm_id))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->level))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $connections = CrmConnection::query()->orderBy('id')->get();

        return view('desk.logs', compact('logs', 'connections'));
    }
}
