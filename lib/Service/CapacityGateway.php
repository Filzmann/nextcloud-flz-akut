<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCA\FlzKapazitaet\PublicApi\V1\CapacityInterval;
use OCA\FlzKapazitaet\PublicApi\V1\CapacityQueryEvent;
use OCP\EventDispatcher\IEventDispatcher;

/**
 * Liest Kapazitätsmeldungen read-only über den öffentlichen flz_kapazitaet-V1-Vertrag
 * (CapacityQueryEvent). Ohne installierten Provider (class_exists schlägt fehl) oder bei
 * Fehlern bleibt das Ergebnis leer — ein gültiger Standalone-Zustand, der keine Rechte erteilt.
 */
final class CapacityGateway {
    public function __construct(private IEventDispatcher $events) {}

    /**
     * @param list<array<string,mixed>> $shifts gelistete Schichten (starts_at/ends_at als ISO-UTC)
     * @param list<string> $uids Assistenz-UIDs des Teams
     * @return list<array{uid:string,day:string,restriction:string,note:?string}>
     */
    public function forShifts(array $shifts, array $uids): array {
        $uids = array_values(array_unique(array_filter(array_map('strval', $uids))));
        if ($shifts === [] || $uids === [] || !class_exists(CapacityQueryEvent::class)) return [];
        try {
            $starts = array_map(static fn(array $s): \DateTimeImmutable => new \DateTimeImmutable((string)$s['starts_at']), $shifts);
            $ends = array_map(static fn(array $s): \DateTimeImmutable => new \DateTimeImmutable((string)$s['ends_at']), $shifts);
            $event = new CapacityQueryEvent(min($starts), max($ends), $uids);
            $this->events->dispatchTyped($event);
            return array_map(static fn(CapacityInterval $capacity): array => $capacity->toArray(), $event->capacities());
        } catch (\Throwable) {
            return [];
        }
    }
}
