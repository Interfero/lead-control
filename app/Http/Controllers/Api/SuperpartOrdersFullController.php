<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\SuperpartOutboxWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Полная постраничная сверка (ТЗ FR-REC-03) без N+1 на стороне SP.
 * Курсор = after_id (order_id).
 */
class SuperpartOrdersFullController extends Controller
{
    public function __invoke(Request $request, SuperpartOutboxWriter $writer): JsonResponse
    {
        $afterId = max(0, (int) $request->query('after_id', 0));
        $limit = min(100, max(1, (int) $request->query('limit', 50)));
        $since = '2026-09-09 00:00:00';

        $spSourceIds = DB::table('sources')
            ->where('available_for_superpart', 1)
            ->where('superpart_partner_id', '>', 0)
            ->pluck('source_id');

        $everSpIds = DB::table('superpart_outbox')->distinct()->pluck('order_id');

        $orders = Order::query()
            ->with(['address.city', 'persons.phones', 'source'])
            ->where('order_id', '>', $afterId)
            ->where(function ($q) use ($spSourceIds, $since, $everSpIds) {
                $q->whereIn('source_id', $spSourceIds)
                    ->orWhereIn('order_id', $everSpIds)
                    ->orWhere(function ($q2) use ($since) {
                        $q2->whereNotNull('partner_user_id')
                            ->where('order_created_at', '>=', $since);
                    });
            })
            ->orderBy('order_id')
            ->limit($limit)
            ->get();

        $items = [];
        foreach ($orders as $order) {
            $orderId = (int) $order->order_id;
            // Для сверки предпочитаем последний outbox-снимок (стабильный event_id/checksum),
            // иначе строим live payload с текущим sync_version (без искусственного bump).
            $lastOutbox = DB::table('superpart_outbox')
                ->where('order_id', $orderId)
                ->orderByDesc('id')
                ->first();

            if ($lastOutbox) {
                $snapshot = is_string($lastOutbox->payload)
                    ? json_decode($lastOutbox->payload, true)
                    : (array) $lastOutbox->payload;
                if (! is_array($snapshot)) {
                    $snapshot = [];
                }
                $version = (int) ($lastOutbox->order_version ?: ($order->sync_version ?? 0));
                $items[] = [
                    'order_id' => $orderId,
                    'sync_version' => $version,
                    'snapshot' => $snapshot,
                    'source' => 'outbox',
                ];
                continue;
            }

            $version = max(0, (int) ($order->sync_version ?? 0));
            $eventId = (string) Str::uuid();
            $snapshot = $writer->buildSnapshotPayload($order, max(1, $version), $eventId, null);
            $items[] = [
                'order_id' => $orderId,
                'sync_version' => max(1, $version),
                'snapshot' => $snapshot,
                'source' => 'live',
            ];
        }

        $nextAfter = $orders->isEmpty() ? $afterId : (int) $orders->last()->order_id;
        $hasMore = $orders->count() === $limit;

        $canon = [];
        foreach ($items as $it) {
            $canon[] = $it['order_id'].'|'.$it['sync_version'].'|'.($it['snapshot']['checksum'] ?? '');
        }

        return response()->json([
            'items' => $items,
            'next_after_id' => $nextAfter,
            'has_more' => $hasMore,
            'page_checksum' => hash('sha256', implode("\n", $canon)),
            'count' => count($items),
        ]);
    }
}
