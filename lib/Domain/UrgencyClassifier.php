<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Domain;

use DateTimeImmutable;
use DateTimeZone;

final class UrgencyClassifier {
    public function __construct(private string $timezone) {}

    /** @param list<string> $publicHolidayDates */
    public function classify(string $shiftStartsAt, string $now, array $publicHolidayDates): string {
        $zone = new DateTimeZone($this->timezone);
        $today = (new DateTimeImmutable($now))->setTimezone($zone)->setTime(0, 0);
        $shiftDay = (new DateTimeImmutable($shiftStartsAt))->setTimezone($zone)->setTime(0, 0);
        $nextOfficeDay = $today->modify('+1 day');
        while ((int)$nextOfficeDay->format('N') >= 6 || in_array($nextOfficeDay->format('Y-m-d'), $publicHolidayDates, true)) $nextOfficeDay = $nextOfficeDay->modify('+1 day');
        if ($shiftDay >= $today && $shiftDay <= $nextOfficeDay) return 'sz';
        if ($shiftDay > $nextOfficeDay && $shiftDay <= $today->modify('+7 days')) return 'week';
        return 'normal';
    }
}
