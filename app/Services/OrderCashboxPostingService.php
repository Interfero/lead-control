<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderCashboxPostingService
{
    public function postForClosedOrder(Order $order, ?User $user = null): void
    {
        if (! Schema::hasTable('cfm_operations')) {
            return;
        }

        if (! $this->isClosedMoneyOrder($order)) {
            return;
        }

        $amount = $this->orderCashAmount($order);

        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($order, $user, $amount): void {
            $columns = Schema::getColumnListing('cfm_operations');

            $relatedOrderColumn = $this->firstColumn($columns, [
                'related_order_id',
                'order_id',
            ]);

            if (! $relatedOrderColumn) {
                return;
            }

            $existing = DB::table('cfm_operations')
                ->where($relatedOrderColumn, $order->order_id)
                ->first();

            if ($existing) {
                $this->markOperationAsPosted($existing, $columns, $user);
                return;
            }

            $payload = $this->buildOperationPayload($order, $user, $amount, $columns, $relatedOrderColumn);

            if (! empty($payload)) {
                DB::table('cfm_operations')->insert($payload);
            }
        });
    }

    private function isClosedMoneyOrder(Order $order): bool
    {
        if ($order->order_closed_at) {
            return true;
        }

        $status = mb_strtolower((string) $order->order_status);

        return in_array($status, [
            'completed',
            'done',
            'complete',
            'finished',
            'closed',
            'success',
            'paid',
            'готов',
            'готово',
            'выполнено',
            'закрыта',
            'закрыт',
            'проведена',
            'проведен',
        ], true);
    }

    private function orderCashAmount(Order $order): float
    {
        $amountPaid = (float) ($order->amount_paid ?? 0);
        $amountComp = (float) ($order->amount_comp ?? 0);

        return round($amountPaid - $amountComp, 2);
    }

    private function buildOperationPayload(
        Order $order,
        ?User $user,
        float $amount,
        array $columns,
        string $relatedOrderColumn
    ): array {
        $payload = [];

        $payload[$relatedOrderColumn] = $order->order_id;

        $amountColumn = $this->firstColumn($columns, [
            'amount',
            'sum',
            'operation_amount',
            'value',
        ]);

        if (! $amountColumn) {
            return [];
        }

        $payload[$amountColumn] = $amount;

        $cityId = $order->address?->city_id ?? null;

        if (! $cityId) {
            $order->loadMissing('address');
            $cityId = $order->address?->city_id;
        }

        $this->put($payload, $columns, 'city_id', $cityId);

        $typeColumn = $this->firstColumn($columns, [
            'type',
            'operation_type',
            'kind',
            'direction',
        ]);

        if ($typeColumn) {
            $payload[$typeColumn] = $typeColumn === 'direction' ? 'in' : 'income';
        }

        $commentColumn = $this->firstColumn($columns, [
            'description',
            'comment',
            'note',
            'operation_comment',
        ]);

        if ($commentColumn) {
            $payload[$commentColumn] = 'Автоматический приход по заявке #' . $order->order_id;
        }

        $this->put($payload, $columns, 'created_by', $user?->user_id ?? $user?->id);
        $this->put($payload, $columns, 'created_by_user_id', $user?->user_id ?? $user?->id);
        $this->put($payload, $columns, 'user_id', $user?->user_id ?? $user?->id);

        $this->applyPostedFields($payload, $columns, $user);

        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = now();
        }

        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = now();
        }

        return array_filter($payload, static fn ($value) => $value !== null);
    }

    private function markOperationAsPosted(object $operation, array $columns, ?User $user): void
    {
        $payload = [];

        $this->applyPostedFields($payload, $columns, $user);

        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = now();
        }

        if (empty($payload)) {
            return;
        }

        $idColumn = $this->firstColumn($columns, [
            'cfm_operation_id',
            'operation_id',
            'id',
        ]);

        if (! $idColumn || ! isset($operation->{$idColumn})) {
            return;
        }

        DB::table('cfm_operations')
            ->where($idColumn, $operation->{$idColumn})
            ->update($payload);
    }

    private function applyPostedFields(array &$payload, array $columns, ?User $user): void
    {
        foreach ([
            'is_posted',
            'posted',
            'is_completed',
            'completed',
            'is_confirmed',
            'confirmed',
            'is_processed',
            'processed',
            'is_conducted',
            'conducted',
        ] as $column) {
            $this->put($payload, $columns, $column, true);
        }

        $statusColumn = $this->firstColumn($columns, [
            'status',
            'operation_status',
        ]);

        if ($statusColumn) {
            $payload[$statusColumn] = 'posted';
        }

        foreach ([
            'posted_at',
            'completed_at',
            'confirmed_at',
            'processed_at',
            'conducted_at',
        ] as $column) {
            $this->put($payload, $columns, $column, now());
        }

        $userId = $user?->user_id ?? $user?->id;

        foreach ([
            'posted_by',
            'completed_by',
            'confirmed_by',
            'processed_by',
            'conducted_by',
        ] as $column) {
            $this->put($payload, $columns, $column, $userId);
        }
    }

    private function firstColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function put(array &$payload, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) {
            $payload[$column] = $value;
        }
    }
}