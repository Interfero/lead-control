<?php

namespace App\Services\Hub;

use App\Models\Hub\HubOrderCache;
use App\Support\Hub\HubMasterSalary;
use App\Support\Hub\HubStatusMap;
use Carbon\CarbonImmutable;

/**
 * Нормализация заявки Единого хаба в формат заявки GM API.
 *
 * Ключи совпадают с mapOrderSummary() для LC-заявок, плюс обязательные по ТЗ
 * source / sourceOrderId / masterId / cityId.
 */
class HubOrderPresenter
{
    public const SOURCE_KP = 'kp_lead';

    /**
     * Канонический ID заявки в GM: source + external_id, конфликтов с LC-ID быть не может.
     */
    public static function canonicalId(string $source, string $externalId): string
    {
        return ($source === self::SOURCE_KP ? 'kp' : $source).'-'.$externalId;
    }

    /**
     * Разбор канонического ID: «kp-2645253» → ['kp_lead', '2645253'].
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parseCanonicalId(string $id): ?array
    {
        if (preg_match('/^kp-(\d+)$/i', trim($id), $m) === 1) {
            return [self::SOURCE_KP, $m[1]];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(HubOrderCache $row, ?int $masterId, string $source = self::SOURCE_KP): array
    {
        $externalId = (string) $row->external_id;
        $status = HubStatusMap::toGmStatus($row->raw_status, $row->status);
        $isFinal = HubStatusMap::isFinal($status);

        $paid = $row->paid_amount !== null ? (int) $row->paid_amount : null;
        $parts = $row->parts_amount !== null ? (int) $row->parts_amount : 0;
        $net = $paid !== null
            ? max(0, $paid - $parts)
            : max(0, (int) ($row->total_amount ?? 0));

        $orderCore = $row->order_core ?: ($row->is_noncore ? 'non_core' : 'core');
        $salary = HubMasterSalary::salary(
            $net,
            $row->order_type,
            $orderCore,
            (bool) $row->is_long_trip,
            (bool) $row->is_satellite,
            (bool) $row->is_partner_order,
        );

        $createdAt = $this->toMoscow($row->created_at_local, $row->timezone);
        $scheduledAt = $this->toMoscow($row->call_at_local, $row->timezone);
        // KP не отдаёт время смены статуса: берём updated_at_local, иначе метку синхронизации.
        $statusChangedAt = $this->toMoscow($row->updated_at_local, $row->timezone) ?? $createdAt;
        $closedAt = $isFinal
            ? ($this->toMoscow($row->updated_at_local, $row->timezone) ?? $createdAt)
            : null;

        $street = trim((string) ($row->address ?? ''));
        // address — улица/дом (без города); address_office не смешиваем в street.
        $address = $street;
        $cityName = trim((string) ($row->city_name ?? ''));
        $addressFull = $this->formatAddressFull($cityName, $address);

        $phone = $this->formatPhone($row->phone);

        return [
            'id' => self::canonicalId($source, $externalId),
            'source' => $source,
            'sourceOrderId' => $externalId,
            'orderNumber' => $externalId,
            'masterId' => $masterId !== null ? (string) $masterId : null,
            'cityId' => $row->city_id !== null ? (int) $row->city_id : null,
            'cityName' => $cityName !== '' ? $cityName : null,
            'status' => $status,
            'description' => $row->description,
            'address' => $address !== '' ? $address : null,
            'addressFull' => $addressFull,
            'clientName' => $row->client_name,
            'clientPhone' => $phone,
            'clientAge' => $row->client_age !== null ? (int) $row->client_age : null,
            'equipmentType' => null,
            'previousContactsSummary' => null,
            'clientContactType' => $phone ? 'phone' : null,
            'orderKind' => $row->order_type,
            'techDirectorComment' => $row->comments,
            'branchComment' => $row->comments,
            'masterSdComment' => null,
            'masterCloseComment' => null,
            'amountRub' => $net,
            'billingCategory' => $row->order_type,
            'distanceKm' => null,
            'financialSnapshot' => [
                'amountPaidRub' => $paid ?? $net,
                'amountCompRub' => $parts,
                'netAmountRub' => $net,
                'masterSalaryRub' => $salary,
                'orderType' => $row->order_type,
                'orderCore' => $orderCore,
            ],
            'hasDocuments' => is_array($row->documents) && $row->documents !== [],
            'hasClaim' => false,
            'claim' => null,
            'statusChangedAt' => $statusChangedAt?->toIso8601String(),
            'inProgressAt' => null,
            'scheduledAt' => $scheduledAt?->toIso8601String(),
            'createdAt' => $createdAt?->toIso8601String(),
            'updatedAt' => ($statusChangedAt ?? $createdAt)?->toIso8601String(),
            'closedAt' => $closedAt?->toIso8601String(),
            'contactUri' => $phone ? 'tel:'.$phone : null,
            'rawStatus' => $row->raw_status,
            'lastSyncedAt' => $this->hubServerTime($row->last_synced_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function details(HubOrderCache $row, ?int $masterId, string $source = self::SOURCE_KP): array
    {
        $summary = $this->summary($row, $masterId, $source);

        $documents = [];
        foreach ((array) ($row->documents ?? []) as $index => $document) {
            if (! is_array($document)) {
                continue;
            }
            $documents[] = [
                'id' => (string) ($document['id'] ?? $index),
                'kind' => (string) ($document['category'] ?? 'contract'),
                'fileName' => (string) ($document['name'] ?? ''),
                'mimeType' => null,
                'url' => $document['url'] ?? null,
                'uploadedAt' => null,
            ];
        }

        $timeline = [];
        if ($summary['createdAt']) {
            $timeline[] = ['date' => $summary['createdAt'], 'type' => 'created', 'label' => 'Order created', 'author' => null];
        }
        if ($summary['scheduledAt']) {
            $timeline[] = ['date' => $summary['scheduledAt'], 'type' => 'scheduled', 'label' => 'Client visit scheduled', 'author' => null];
        }
        if ($summary['closedAt']) {
            $timeline[] = ['date' => $summary['closedAt'], 'type' => 'closed', 'label' => 'Order closed', 'author' => null];
        }

        return array_merge($summary, [
            'documents' => $documents,
            'timeline' => $timeline,
            'assignedMaster' => $masterId !== null
                ? ['userId' => $masterId, 'displayName' => $row->master_name, 'role' => 'master', 'phone' => null]
                : null,
            'branch' => [
                'branchId' => $row->city_id !== null ? (int) $row->city_id : null,
                'branchCity' => $row->city_name,
            ],
            'canCallClient' => $summary['clientPhone'] !== null,
            'canMessageClient' => $summary['clientPhone'] !== null,
        ]);
    }

    /**
     * Время заявки источника в Europe/Moscow: расчёты статистики по ТЗ только в МСК.
     */
    public function toMoscow(mixed $value, ?string $timezone): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        $tz = trim((string) $timezone);
        if ($tz === '') {
            $tz = 'Europe/Moscow';
        }

        try {
            return CarbonImmutable::parse($value)
                ->shiftTimezone($tz)
                ->setTimezone('Europe/Moscow');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Серверные метки Единого окна хранятся в TZ того приложения (UTC), поэтому Eloquent
     * не должен трактовать их как московское время.
     */
    private function hubServerTime(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        $tz = (string) config('services.unified_hub.timezone', 'UTC');

        try {
            return ($value instanceof \DateTimeInterface
                ? CarbonImmutable::instance($value)
                : CarbonImmutable::parse((string) $value))
                ->shiftTimezone($tz)
                ->setTimezone('Europe/Moscow');
        } catch (\Throwable) {
            return null;
        }
    }

    private function formatPhone(?string $rawPhone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $rawPhone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) === 10) {
            return '+7'.$digits;
        }

        if (strlen($digits) === 11 && ($digits[0] === '7' || $digits[0] === '8')) {
            return '+7'.substr($digits, 1);
        }

        return '+'.$digits;
    }

    /**
     * Полное место для UI: cityName + улица, без дубля если address уже начинается с города.
     */
    private function formatAddressFull(string $cityName, string $address): ?string
    {
        $cityName = trim($cityName);
        $address = trim($address);
        if ($cityName === '' && $address === '') {
            return null;
        }
        if ($cityName === '') {
            return $address;
        }
        if ($address === '') {
            return $cityName;
        }
        if (str_starts_with(mb_strtolower($address), mb_strtolower($cityName))) {
            return $address;
        }

        return $cityName.', '.$address;
    }
}
