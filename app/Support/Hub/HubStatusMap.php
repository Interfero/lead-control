<?php

namespace App\Support\Hub;

/**
 * Сопоставление статусов внешних источников Единого хаба со статусами GM API.
 *
 * Адаптер KP-Lead в Едином окне уже приводит «сырой» статус карточки КП к кодам CRM1,
 * поэтому основная карта — 1:1. Но GM не должен зависеть от того, что придёт новый код:
 * всё неизвестное раскладывается по унифицированному статусу кэша (new/on_way/in_progress/ready/closed).
 *
 * Полная таблица — docs/GM-UNIFIED-HUB-API.md.
 */
final class HubStatusMap
{
    /** raw_status источника → статус GM. */
    private const RAW_TO_GM = [
        'callback' => 'callback',
        'not_processed' => 'not_processed',
        'new' => 'pending',
        'pending' => 'pending',
        'on_way' => 'on_way',
        'in_progress' => 'in_progress',
        'in_progress_sd' => 'in_progress_sd',
        'sd' => 'in_progress_sd',
        'review' => 'review',
        'ready' => 'review',
        'done' => 'completed',
        'closed' => 'completed',
        'completed' => 'completed',
        'cancelled_cc' => 'cancelled_cc',
        'cancelled_city' => 'cancelled_city',
        'canceled_cc' => 'cancelled_cc',
        'canceled_city' => 'cancelled_city',
        'rejected' => 'rejected',
        'refused' => 'rejected',
    ];

    /** unified status кэша хаба → статус GM (страховка для незнакомых raw_status). */
    private const UNIFIED_TO_GM = [
        'new' => 'pending',
        'on_way' => 'on_way',
        'in_progress' => 'in_progress',
        'ready' => 'review',
        'closed' => 'completed',
    ];

    /** Финальные статусы GM: заказ уходит в историю и попадает в месячную статистику. */
    public const FINAL_STATUSES = ['completed', 'cancelled_cc', 'cancelled_city', 'rejected'];

    public static function toGmStatus(?string $rawStatus, ?string $unifiedStatus): string
    {
        $raw = mb_strtolower(trim((string) $rawStatus));
        if ($raw !== '' && isset(self::RAW_TO_GM[$raw])) {
            return self::RAW_TO_GM[$raw];
        }

        $unified = mb_strtolower(trim((string) $unifiedStatus));
        if ($unified !== '' && isset(self::UNIFIED_TO_GM[$unified])) {
            return self::UNIFIED_TO_GM[$unified];
        }

        return 'pending';
    }

    public static function isFinal(string $gmStatus): bool
    {
        return in_array($gmStatus, self::FINAL_STATUSES, true);
    }

    /**
     * @return array<string, string>
     */
    public static function table(): array
    {
        return self::RAW_TO_GM;
    }
}
