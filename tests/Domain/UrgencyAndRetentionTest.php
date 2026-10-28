<?php

declare(strict_types=1);

use OCA\FlzAkut\Domain\RetentionPolicy;
use OCA\FlzAkut\Domain\UrgencyClassifier;

require_once dirname(__DIR__) . '/bootstrap.php';

$classifier = new UrgencyClassifier('Europe/Berlin');
$holidayDates = ['2026-12-28'];
if ($classifier->classify('2026-12-24T08:00:00+01:00', '2026-12-24T09:00:00+01:00', $holidayDates) !== 'sz') throw new RuntimeException('Heutige Schicht muss SZ sein.');
if ($classifier->classify('2026-12-29T08:00:00+01:00', '2026-12-24T09:00:00+01:00', $holidayDates) !== 'week') throw new RuntimeException('Nach dem nächsten Büroarbeitstag liegende Wochenschicht muss orange klassifiziert sein.');
if ($classifier->classify('2027-01-10T08:00:00+01:00', '2026-12-24T09:00:00+01:00', $holidayDates) !== 'normal') throw new RuntimeException('Spätere Schicht darf keine Dringlichkeitsfarbe erhalten.');

$policy = new RetentionPolicy();
$default = $policy->expiresAt('2026-01-03T20:00:00+01:00', 180);
if ($default->format('Y-m-d') !== '2026-07-02') throw new RuntimeException('Standardaufbewahrung muss 180 Tage ab Schichtende betragen.');
$minimum = $policy->expiresAt('2026-01-31T20:00:00+01:00', 30);
if ($minimum->format('Y-m-d') < '2026-02-28') throw new RuntimeException('Löschung darf nicht vor einem Monat nach Monatsende erfolgen.');
try { $policy->expiresAt('2026-01-01T00:00:00+01:00', 366); throw new RuntimeException('Mehr als ein Jahr muss abgelehnt werden.'); } catch (InvalidArgumentException) {}

echo "flz_akut urgency and retention tests passed\n";
