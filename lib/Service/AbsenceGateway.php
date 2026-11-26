<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCA\LocalBase\Calendar\AbsenceQueryEvent;
use OCP\EventDispatcher\IEventDispatcher;

/**
 * Liest Abwesenheiten (z. B. Urlaub) read-only über den LocalBase-AbsenceQueryEvent-Vertrag.
 * Ohne Provider (Urlaubs-App nicht verfügbar) bleibt das Ergebnis leer — ein gültiger Standalone-Zustand.
 */
final class AbsenceGateway {
    public function __construct(private IEventDispatcher $events) {}

    /**
     * @param list<array<string,mixed>> $shifts gelistete Schichten (starts_at/ends_at als ISO-UTC)
     * @param list<string> $uids Assistenz-UIDs des Teams
     * @return list<array{uid:string,startsAt:string,endsAt:string,status:string,label:string}>
     */
    public function forShifts(array $shifts, array $uids): array {
        $uids = array_values(array_unique(array_filter(array_map('strval', $uids))));
        if ($shifts === [] || $uids === [] || !class_exists(AbsenceQueryEvent::class)) return [];
        try {
            $starts = array_map(static fn(array $s): \DateTimeImmutable => new \DateTimeImmutable((string)$s['starts_at']), $shifts);
            $ends = array_map(static fn(array $s): \DateTimeImmutable => new \DateTimeImmutable((string)$s['ends_at']), $shifts);
            $event = new AbsenceQueryEvent(min($starts), max($ends), $uids);
            $this->events->dispatchTyped($event);
            $out = [];
            foreach ($event->absences() as $absence) {
                $absenceData = $absence->toArray();
                foreach ($shifts as $shift) {
                    if (!$absence->overlaps(new \DateTimeImmutable((string)$shift['starts_at']), new \DateTimeImmutable((string)$shift['ends_at']))) continue;
                    $key = $absence->employeeUid() . '|' . $absence->start()->format(DATE_ATOM);
                    $out[$key] = [
                        'uid' => $absence->employeeUid(),
                        'startsAt' => $absence->start()->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
                        'endsAt' => $absence->end()->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
                        'status' => $absence->status(),
                        'label' => (string)($absenceData['label'] ?? 'Urlaub'),
                    ];
                    break;
                }
            }
            return array_values($out);
        } catch (\Throwable) {
            return [];
        }
    }
}
