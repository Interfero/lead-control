<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Инкрементальная выборка изменений для SuperPart (ТЗ FR-REC-01).
 * Курсор = id записи superpart_outbox (монотонный).
 */
class SuperpartOrdersChangesController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $cursor = max(0, (int) $request->query('cursor', 0));
        $limit = min(200, max(1, (int) $request->query('limit', 50)));

        $rows = DB::table('superpart_outbox')
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'event_id', 'order_id', 'order_version', 'event_type', 'payload', 'status', 'created_at']);

        $items = [];
        foreach ($rows as $row) {
            $payload = is_string($row->payload)
                ? json_decode($row->payload, true)
                : (array) $row->payload;
            $items[] = [
                'cursor' => (int) $row->id,
                'event_id' => $row->event_id,
                'order_id' => (int) $row->order_id,
                'order_version' => (int) $row->order_version,
                'event_type' => $row->event_type,
                'outbox_status' => $row->status,
                'created_at' => $row->created_at,
                'snapshot' => $payload,
            ];
        }

        $nextCursor = $rows->isEmpty() ? $cursor : (int) $rows->last()->id;
        $hasMore = $rows->count() === $limit;

        $pageCanon = [];
        foreach ($items as $it) {
            $pageCanon[] = $it['event_id'].'|'.$it['order_id'].'|'.$it['order_version'];
        }
        $pageChecksum = hash('sha256', implode("\n", $pageCanon));

        return response()->json([
            'items' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
            'page_checksum' => $pageChecksum,
            'count' => count($items),
        ]);
    }
}
