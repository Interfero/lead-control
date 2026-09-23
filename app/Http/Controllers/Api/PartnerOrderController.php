<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Api\OrderLeadPresenter;
use App\Services\PartnerOrderIngestService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PartnerOrderController extends Controller
{
    public function __construct(
        private PartnerOrderIngestService $ingestService,
        private OrderLeadPresenter $presenter
    ) {}

    /**
     * Список заявок по всем активным филиалам (без whitelist «наших» городов).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'city_id' => ['nullable', 'integer', 'exists:cities,city_id'],
            'order_status' => ['nullable', 'string', 'max:64'],
            'updated_after' => ['nullable', 'date'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:5000'],
        ]);

        $query = Order::query()
            ->with(['address.city', 'persons.phones', 'master', 'source'])
            ->orderByDesc('order_id');

        if (! empty($validated['city_id'])) {
            $cityId = (int) $validated['city_id'];
            $query->whereHas('address', fn ($q) => $q->where('city_id', $cityId));
        }

        if (! empty($validated['order_status'])) {
            $query->where('order_status', $validated['order_status']);
        }

        if (! empty($validated['updated_after'])) {
            $after = Carbon::parse($validated['updated_after']);
            $query->where(function ($q) use ($after) {
                $q->where('order_created_at', '>=', $after)
                    ->orWhere('order_closed_at', '>=', $after)
                    ->orWhere('datetime_order', '>=', $after);
            });
        }

        $limit = (int) ($validated['limit'] ?? 500);
        $orders = $query->limit($limit)->get()
            ->map(fn (Order $o) => $this->presenter->present($o))
            ->values()
            ->all();

        return response()->json(['orders' => $orders]);
    }

    public function store(Request $request): JsonResponse
    {
        $equipmentKeys = implode(',', array_keys(Order::EQUIPMENT_TYPES));

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['required', 'string', 'max:50'],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'city_id')->where(fn ($q) => $q->where('is_active', true))],
            'street' => ['required', 'string', 'max:255'],
            'house' => ['required', 'string', 'max:64'],
            'flat' => ['nullable', 'string', 'max:32'],
            'address_adds' => ['nullable', 'string', 'max:2000'],
            'order_core' => ['nullable', 'in:core,non_core,other'],
            'equipment_type' => ['nullable', 'string', 'in:'.$equipmentKeys],
            'datetime_order' => ['required', 'date'],
            'order_adds' => ['nullable', 'string', 'max:65535'],
            'partner_user_id' => ['required', 'integer', 'min:1'],
            'source_id' => ['nullable', 'integer', Rule::exists('sources', 'source_id')],
            // Происхождение заявки: приём в LC всегда LC (KP-LEAD — только в Desk/CRM2).
            'source' => ['nullable', 'string', Rule::in(['LC'])],
        ]);

        $idempotencyKey = (string) $request->header('Idempotency-Key', '');
        if ($idempotencyKey === '') {
            return response()->json(['message' => 'Заголовок Idempotency-Key обязателен'], 422);
        }
        if (strlen($idempotencyKey) > 128) {
            return response()->json(['message' => 'Idempotency-Key слишком длинный'], 422);
        }

        if (empty($validated['equipment_type'] ?? null) && empty($validated['order_core'] ?? null)) {
            return response()->json(['message' => 'Укажите order_core или equipment_type'], 422);
        }

        try {
            $order = $this->ingestService->createOrder($validated, $idempotencyKey);
        } catch (ValidationException $e) {
            $errs = $e->errors();
            if (isset($errs['config'])) {
                return response()->json(['message' => $errs['config'][0]], 503);
            }
            if (isset($errs['idempotency'])) {
                return response()->json(['message' => $errs['idempotency'][0]], 503);
            }
            throw $e;
        } catch (QueryException $e) {
            Log::error('SuperPart POST /partner-orders: ошибка БД', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $this->hintForPartnerOrderQueryException($e),
            ], 500);
        } catch (Throwable $e) {
            Log::error('SuperPart POST /partner-orders: неожиданное исключение', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'message' => 'Ошибка выполнения на сервере CRM: откройте storage/logs/laravel.log в момент запроса — там будет исключение.',
            ], 500);
        }

        $order->load(['address.city', 'persons.phones', 'master', 'source']);

        return response()->json($this->presenter->present($order), 201);
    }

    public function show(int $order_id): JsonResponse
    {
        $order = Order::query()
            ->with(['address.city', 'persons.phones', 'master', 'source'])
            ->find($order_id);
        if (! $order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        return response()->json($this->presenter->present($order));
    }

    public function statuses(): JsonResponse
    {
        return response()->json([
            'statuses' => $this->presenter->statusCatalog(),
            'labels' => Order::getStatusLabels(),
        ]);
    }

    public function statusShow(int $order_id): JsonResponse
    {
        $order = Order::query()->with('address')->find($order_id);
        if (! $order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        return response()->json($this->presenter->presentStatus($order));
    }

    public function statusUpdate(Request $request, int $order_id): JsonResponse
    {
        $order = Order::query()->with(['address.city', 'persons.phones', 'master', 'source'])->find($order_id);
        if (! $order) {
            return response()->json(['message' => 'Заказ не найден'], 404);
        }

        if ($order->isClosedStatus()) {
            return response()->json(['message' => 'Заказ уже закрыт, статус менять нельзя'], 422);
        }

        $allowed = array_keys(Order::getStatusLabels());
        $validated = $request->validate([
            'order_status' => ['required', 'string', Rule::in($allowed)],
        ]);

        $order->order_status = $validated['order_status'];
        $order->save();

        return response()->json($this->presenter->presentStatus($order->fresh('address')));
    }

    /**
     * Краткая подсказка по типичным SQL-ошибкам при приёме партнёрского заказа (миграции, схема).
     */
    private function hintForPartnerOrderQueryException(QueryException $e): string
    {
        $msg = $e->getMessage();

        if (str_contains($msg, 'partner_user_id') && str_contains($msg, 'Unknown column')) {
            return 'В таблице orders нет колонки partner_user_id. На сервере CRM выполните: php83 artisan migrate --force (миграция 2026_04_06_120000_partner_superpart_integration).';
        }

        if (str_contains($msg, 'partner_order_idempotency') && (str_contains($msg, "doesn't exist") || str_contains($msg, 'Base table') || str_contains($msg, 'не существует'))) {
            return 'Нет таблицы partner_order_idempotency. Выполните php83 artisan migrate --force (миграция SuperPart от 06.04.2026).';
        }

        if (str_contains($msg, 'integration_api_logs') && (str_contains($msg, "doesn't exist") || str_contains($msg, 'Base table'))) {
            return 'Нет таблицы integration_api_logs. Выполните php83 artisan migrate --force.';
        }

        if (str_contains($msg, 'Duplicate entry') && str_contains($msg, 'idempotency')) {
            return 'Конфликт Idempotency-Key (повторная вставка). Повторите запрос с тем же ключом или используйте новый.';
        }

        return 'Ошибка базы данных CRM. Полный текст в storage/logs/laravel.log; часто нужна миграция SuperPart или проверка схемы MySQL.';
    }
}
