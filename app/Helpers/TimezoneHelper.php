<?php

namespace App\Helpers;

use DateTimeImmutable;
use DateTimeZone;

class TimezoneHelper
{
    private const MSK_TIMEZONE = 'Europe/Moscow';

    /**
     * Разница UTC-смещения зоны и Москвы на текущий момент (секунды).
     */
    public static function diffSecondsFromMsk(string $ianaTimezone): int
    {
        $tz = new DateTimeZone($ianaTimezone);
        $msk = new DateTimeZone(self::MSK_TIMEZONE);

        $nowTz = new DateTimeImmutable('now', $tz);
        $nowMsk = new DateTimeImmutable('now', $msk);

        return $nowTz->getOffset() - $nowMsk->getOffset();
    }

    /**
     * Подпись смещения относительно Москвы: МСК+0, МСК+2, МСК-1; при дробном часе — МСК+5:30.
     */
    public static function mskOffsetLabel(string $ianaTimezone): string
    {
        $diffSeconds = self::diffSecondsFromMsk($ianaTimezone);

        $sign = $diffSeconds >= 0 ? '+' : '-';
        $totalMinutes = intdiv(abs($diffSeconds), 60);
        $hours = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        if ($minutes === 0) {
            return 'МСК'.$sign.$hours;
        }

        return sprintf('МСК%s%d:%02d', $sign, $hours, $minutes);
    }

    /**
     * По одному IANA на каждое уникальное смещение относительно Москвы (на «сейчас»),
     * канонический идентификатор — лексикографически меньший в группе.
     * Сортировка: от западных смещений к восточным.
     *
     * @param  string|null  $ensureInclude  для редактирования: добавить сохранённый IANA, если его нет в списке
     * @return list<string>
     */
    public static function uniqueIdentifiersForMskSelect(?string $ensureInclude = null): array
    {
        $idsByDiff = [];
        foreach (DateTimeZone::listIdentifiers() as $id) {
            $diff = self::diffSecondsFromMsk($id);
            $idsByDiff[$diff][] = $id;
        }

        $ids = [];
        foreach ($idsByDiff as $list) {
            if (in_array(self::MSK_TIMEZONE, $list, true)) {
                $ids[] = self::MSK_TIMEZONE;
            } else {
                sort($list);
                $ids[] = $list[0];
            }
        }

        if ($ensureInclude !== null && $ensureInclude !== '' && ! in_array($ensureInclude, $ids, true)) {
            $ids[] = $ensureInclude;
        }

        usort($ids, fn (string $a, string $b): int => self::diffSecondsFromMsk($a) <=> self::diffSecondsFromMsk($b));

        return $ids;
    }
}
