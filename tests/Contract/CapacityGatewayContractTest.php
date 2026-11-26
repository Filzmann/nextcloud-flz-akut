<?php

declare(strict_types=1);

namespace OCP\EventDispatcher { if (!class_exists(Event::class)) { class Event { public function __construct() {} } } if (!interface_exists(IEventDispatcher::class)) { interface IEventDispatcher { public function dispatchTyped(object $event): object; } } }

namespace {

use OCA\FlzAkut\Service\CapacityGateway;
use OCA\FlzKapazitaet\PublicApi\V1\CapacityInterval;
use OCA\FlzKapazitaet\PublicApi\V1\CapacityQueryEvent;

$fail = static function (string $message): void { throw new RuntimeException($message); };

$shifts = [
    ['starts_at' => '2030-01-01T10:00:00+00:00', 'ends_at' => '2030-01-01T12:00:00+00:00'],
    ['starts_at' => '2030-01-02T22:00:00+00:00', 'ends_at' => '2030-01-03T06:00:00+00:00'],
];

// Leerfälle: keine Schichten oder keine UIDs -> leer, ohne Event
$gateway = new CapacityGateway(new class implements OCP\EventDispatcher\IEventDispatcher {
    public function dispatchTyped(object $event): object { throw new RuntimeException('darf nicht aufgerufen werden'); }
});
if ($gateway->forShifts([], ['as1']) !== []) $fail('Keine Schichten -> leeres Ergebnis.');
if ($gateway->forShifts($shifts, []) !== []) $fail('Keine UIDs -> leeres Ergebnis.');

// Provider antwortet: nur angefragte UIDs und überlappende Tage gelangen durch
$dispatcher = new class implements OCP\EventDispatcher\IEventDispatcher {
    public function dispatchTyped(object $event): object {
        if (!$event instanceof CapacityQueryEvent) return $event;
        $event->add(new CapacityInterval('as1', '2030-01-01', '', 'alles am Tag'));
        $event->add(new CapacityInterval('as1', '2030-01-02', 'nacht', 'nur nachts'));
        $event->add(new CapacityInterval('as9', '2030-01-01', '', 'nicht angefragt'));
        $event->add(new CapacityInterval('as1', '2030-06-15', '', 'außerhalb Fenster'));
        return $event;
    }
};
$gateway = new CapacityGateway($dispatcher);
$result = $gateway->forShifts($shifts, ['as1']);
if (count($result) !== 2) $fail('Gateway muss genau die passenden Kapazitäten liefern: ' . json_encode($result));
if ($result[0] !== ['uid' => 'as1', 'day' => '2030-01-01', 'restriction' => '', 'note' => 'alles am Tag']) $fail('Erster Eintrag falsch: ' . json_encode($result[0]));
if ($result[1]['restriction'] !== 'nacht') $fail('Zweiter Eintrag falsch.');

// Fenster wird aus den Schichten gebildet (min Start .. max Ende)
$seen = null;
$spy = new class($seen) implements OCP\EventDispatcher\IEventDispatcher {
    public ?CapacityQueryEvent $captured = null;
    public function dispatchTyped(object $event): object { $this->captured = $event; return $event; }
};
$gateway = new CapacityGateway($spy);
$gateway->forShifts($shifts, ['as1']);
if ($spy->captured === null) $fail('Event muss dispatcht werden.');
if ($spy->captured->start()->format('Y-m-d\TH:i:sP') !== '2030-01-01T10:00:00+00:00') $fail('Fensterstart falsch.');
if ($spy->captured->end()->format('Y-m-d\TH:i:sP') !== '2030-01-03T06:00:00+00:00') $fail('Fensterende falsch.');
if ($spy->captured->employeeUids() !== ['as1']) $fail('Nur Team-UIDs werden angefragt.');

// Fehler im Provider -> fail-closed leer
$broken = new class implements OCP\EventDispatcher\IEventDispatcher {
    public function dispatchTyped(object $event): object { throw new RuntimeException('Provider kaputt'); }
};
if ((new CapacityGateway($broken))->forShifts($shifts, ['as1']) !== []) $fail('Fehler muss fail-closed leer liefern.');

echo "CapacityGatewayContractTest: OK\n";
}
