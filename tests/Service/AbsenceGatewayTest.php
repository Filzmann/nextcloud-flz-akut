<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    if (!class_exists(Event::class, false)) {
        class Event {
            public function __construct() {}
        }
    }
    if (!interface_exists(IEventDispatcher::class)) {
        interface IEventDispatcher {
            public function dispatchTyped(object $event): void;
        }
    }
}

namespace {
    use OCA\FlzAkut\Service\AbsenceGateway;
    use OCA\LocalBase\Calendar\AbsenceInterval;
    use OCA\LocalBase\Calendar\AbsenceQueryEvent;
    use OCP\EventDispatcher\IEventDispatcher;

    require_once dirname(__DIR__) . '/bootstrap.php';

    $withProvider = new class implements IEventDispatcher {
        public function dispatchTyped(object $event): void {
            if ($event instanceof AbsenceQueryEvent) {
                $event->add(new AbsenceInterval('as1', new DateTimeImmutable('2030-01-01T08:00:00+00:00'), new DateTimeImmutable('2030-01-02T08:00:00+00:00'), AbsenceInterval::STATUS_APPROVED, 'Urlaub'));
                $event->add(new AbsenceInterval('as2', new DateTimeImmutable('2031-05-01T08:00:00+00:00'), new DateTimeImmutable('2031-05-02T08:00:00+00:00'), AbsenceInterval::STATUS_PLANNED, 'Urlaub'));
            }
        }
    };
    $withoutProvider = new class implements IEventDispatcher {
        public function dispatchTyped(object $event): void {}
    };

    $shifts = [
        ['id' => 1, 'starts_at' => '2030-01-01T10:00:00+00:00', 'ends_at' => '2030-01-01T12:00:00+00:00'],
    ];

    $gateway = new AbsenceGateway($withProvider);
    $absences = $gateway->forShifts($shifts, ['as1', 'as2']);
    if (count($absences) !== 1 || $absences[0]['uid'] !== 'as1' || $absences[0]['status'] !== 'approved' || $absences[0]['label'] !== 'Urlaub') {
        throw new RuntimeException('Nur Abwesenheiten, die eine gelistete Schicht überlappen, dürfen geliefert werden.');
    }
    if ($absences[0]['startsAt'] !== '2030-01-01T08:00:00+00:00') {
        throw new RuntimeException('Abwesenheiten müssen als UTC-ISO ausgeliefert werden.');
    }

    $empty = (new AbsenceGateway($withoutProvider))->forShifts($shifts, ['as1']);
    if ($empty !== []) throw new RuntimeException('Ohne Provider (Urlaubs-App nicht verfügbar) muss das Ergebnis leer sein.');
    if ($gateway->forShifts([], ['as1']) !== [] || $gateway->forShifts($shifts, []) !== []) {
        throw new RuntimeException('Ohne Schichten oder UIDs darf nichts abgefragt werden.');
    }

    echo "flz_akut absence gateway tests passed\n";
}
