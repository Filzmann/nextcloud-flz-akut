<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Domain;

use DateTimeImmutable;
use InvalidArgumentException;

final class RetentionPolicy {
    public function expiresAt(string $shiftEndsAt, int $days): DateTimeImmutable {
        if ($days < 30 || $days > 365) throw new InvalidArgumentException('Die Aufbewahrung muss zwischen 30 und 365 Tagen liegen.');
        $end = new DateTimeImmutable($shiftEndsAt);
        $dayExpiry = $end->modify('+' . $days . ' days');
        $monthMinimum = $end->modify('last day of next month')->setTime(23, 59, 59);
        return $dayExpiry > $monthMinimum ? $dayExpiry : $monthMinimum;
    }
}
