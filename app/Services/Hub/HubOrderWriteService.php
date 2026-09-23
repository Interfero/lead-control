<?php

namespace App\Services\Hub;

use App\Exceptions\HubOrderConflictException;
use App\Models\Hub\HubOrderCache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Запись статуса KP-заявки через внутренний API Единого окна (lead-desk).
 *
 * Lead Control не хранит пароли КП и не пишет в orders_cache напрямую
 * (hub DB — read-only). Desk принимает Bearer DESK_INTERNAL_TOKEN, пишет в КП
 * своим Crm2HttpAdapter и обновляет кэш.
 */
class HubOrderWriteService
{
    /**
     * @param  array{comment?: string, master_external_id?: string}  $extra
     * @return array{ok: bool, raw_status: string|null, status: string|null, idempotent: bool}
     */
    public function setRawStatus(string $externalId, string $rawStatus, array $extra = []): array
    {
        $driver = (string) config('services.unified_hub.write_driver', 'http');

        // Тесты не имеют права менять статусы реальных заявок в КП, даже если
        // прогон идёт на боевом хосте с настроенным DESK_INTERNAL_TOKEN.
        if ($driver === 'cache' || app()->environment('testing')) {
            return $this->setRawStatusInCache($externalId, $rawStatus);
        }

        return $this->setRawStatusViaDesk($externalId, $rawStatus, $extra);
    }

    /**
     * Тестовый драйвер: только локальный кэш хаба (без HTTP/КП).
     *
     * @return array{ok: bool, raw_status: string|null, status: string|null, idempotent: bool}
     */
    private function setRawStatusInCache(string $externalId, string $rawStatus): array
    {
        $crmId = (int) config('services.unified_hub.kp_crm_id', 2);
        $row = HubOrderCache::query()
            ->where('crm_id', $crmId)
            ->where('external_id', $externalId)
            ->first();

        if (! $row) {
            throw HubOrderConflictException::writeFailed('Заявка не найдена в кэше хаба.');
        }

        $idempotent = (string) $row->raw_status === $rawStatus;
        if (! $idempotent) {
            $row->raw_status = $rawStatus;
            $unified = match ($rawStatus) {
                'pending', 'new', 'callback', 'not_processed' => 'new',
                'on_way' => 'on_way',
                'in_progress', 'in_progress_sd', 'sd' => 'in_progress',
                'review', 'ready' => 'ready',
                'completed', 'closed', 'done' => 'closed',
                default => 'in_progress',
            };
            $row->status = $unified;
            if (isset($row->gm_status) || array_key_exists('gm_status', $row->getAttributes())) {
                $row->gm_status = $rawStatus;
            }
            $row->last_synced_at = now();
            $row->save();
        }

        return [
            'ok' => true,
            'raw_status' => (string) $row->raw_status,
            'status' => (string) $row->status,
            'idempotent' => $idempotent,
        ];
    }

    /**
     * @param  array{comment?: string, master_external_id?: string}  $extra
     * @return array{ok: bool, raw_status: string|null, status: string|null, idempotent: bool}
     */
    private function setRawStatusViaDesk(string $externalId, string $rawStatus, array $extra): array
    {
        $base = rtrim((string) config('services.unified_hub.write_url', ''), '/');
        $token = (string) config('services.unified_hub.write_token', '');

        if ($base === '' || $token === '') {
            throw HubOrderConflictException::writeNotConfigured();
        }

        $url = $base.'/desk/internal/orders/'.$externalId.'/status';
        $payload = array_filter([
            'raw_status' => $rawStatus,
            'comment' => $extra['comment'] ?? null,
            'master_external_id' => $extra['master_external_id'] ?? null,
        ], static fn ($v) => $v !== null && $v !== '');

        try {
            $response = Http::timeout((int) config('services.unified_hub.write_timeout', 45))
                ->withToken($token)
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (Throwable $exception) {
            Log::warning('hub.write.desk_http_failed', [
                'external_id' => $externalId,
                'error' => $exception->getMessage(),
            ]);

            throw HubOrderConflictException::writeFailed(
                'Нет связи с Единым окном при записи статуса KP-Lead.'
            );
        }

        $json = $response->json() ?? [];
        if ($response->successful() && ($json['ok'] ?? false)) {
            return [
                'ok' => true,
                'raw_status' => isset($json['raw_status']) ? (string) $json['raw_status'] : $rawStatus,
                'status' => isset($json['status']) ? (string) $json['status'] : null,
                'idempotent' => (bool) ($json['idempotent'] ?? false),
            ];
        }

        $code = (string) ($json['code'] ?? 'source_write_failed');
        $message = (string) ($json['message'] ?? 'Не удалось записать статус в KP-Lead.');

        if ($code === 'order_already_final') {
            throw HubOrderConflictException::alreadyFinal();
        }

        if ($code === 'order_not_found' || $response->status() === 404) {
            throw HubOrderConflictException::writeFailed('Заявка не найдена в Едином окне.', action: 'write');
        }

        if ($code === 'source_write_not_configured' || $code === 'internal_write_disabled') {
            throw HubOrderConflictException::writeNotConfigured();
        }

        throw new HubOrderConflictException($code, $message);
    }
}
