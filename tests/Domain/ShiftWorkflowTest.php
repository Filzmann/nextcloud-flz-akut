<?php

declare(strict_types=1);

use OCA\FlzAkut\Domain\ShiftWorkflow;
use OCA\FlzAkut\Tests\Support\InMemoryShiftStore;

require_once dirname(__DIR__) . '/bootstrap.php';

$store = new InMemoryShiftStore();
$workflow = new ShiftWorkflow($store);
$shift = $workflow->create('TeamA', 'west', '2026-11-03T08:00:00+01:00', '2026-11-03T20:00:00+01:00', 'Ersatz gesucht', 'day', 'dispatcher-a');
if ($shift['state'] !== 'open' || $shift['source_template_key'] !== 'day') throw new RuntimeException('Neue Schicht ist nicht offen oder verliert die unveränderliche Vorlagenreferenz.');

$parts = $workflow->split((int)$shift['id'], ['2026-11-03T13:30:00+01:00'], 'dispatcher-a');
if (count($parts) !== 2 || $parts[0]['ends_at'] !== $parts[1]['starts_at'] || $store->get((int)$shift['id'])['state'] !== 'split') throw new RuntimeException('Beliebige lückenfreie Teilung ersetzt die Ursprungsschicht nicht korrekt.');

$accepted = $workflow->accept((int)$parts[0]['id'], 'assistant-a', 'EB / ASN', '2026-10-27T10:00:00+01:00', true, 'dispatcher-a');
if ($accepted['state'] !== 'mediated' || $accepted['assigned_uid'] !== 'assistant-a' || $accepted['ma_label'] !== 'EB / ASN') throw new RuntimeException('Eine Schicht wird nicht genau einer AS vermittelt.');

try {
    $workflow->accept((int)$parts[0]['id'], 'assistant-b', 'EB', '2026-10-27T10:01:00+01:00', false, 'dispatcher-b');
    throw new RuntimeException('Eine bereits vermittelte Schicht darf nicht doppelt besetzt werden.');
} catch (DomainException) {
}

$reopened = $workflow->reopen((int)$parts[0]['id'], 'dispatcher-b');
if ($reopened['state'] !== 'open' || $reopened['assigned_uid'] !== null || count($store->audit) < 3) throw new RuntimeException('Wiederöffnung oder Audit ist unvollständig.');

$first = $workflow->addContact((int)$parts[1]['id'], 'assistant-a', 'phone', 'declined', '2026-10-27T10:10:00+01:00', 'dispatcher-a', null, 'Kann vielleicht ab 17 Uhr');
$second = $workflow->addContact((int)$parts[1]['id'], 'assistant-a', 'sms', 'open', '2026-10-27T11:10:00+01:00', 'dispatcher-b', null, 'Übermorgen erneut anfragen');
if ($first['id'] === $second['id'] || count($store->contacts) !== 2) throw new RuntimeException('Mehrere Kontaktversuche je AS und Schicht müssen erhalten bleiben.');

$night = $workflow->create('TeamA', 'west', '2026-11-03T22:00:00+01:00', '2026-11-03T06:00:00+01:00', 'Nachtschicht', null, 'dispatcher-a');
if ($night['starts_at'] !== '2026-11-03T22:00:00+01:00' || $night['ends_at'] !== '2026-11-04T06:00:00+01:00') throw new RuntimeException('Nachtschicht (bis kleiner als von) muss am nächsten Tag enden.');

try {
    $workflow->create('TeamA', 'west', '2026-11-03T08:00:00+01:00', '2026-11-03T08:00:00+01:00', '', null, 'dispatcher-a');
    throw new RuntimeException('Gleiche Start- und Endzeit darf keine Schicht ergeben.');
} catch (InvalidArgumentException) {
}

try {
    $workflow->create('TeamA', 'west', '2026-11-03T12:00:00+01:00', '2026-11-01T06:00:00+01:00', '', null, 'dispatcher-a');
    throw new RuntimeException('Ein Ende mehr als 24 Stunden vor dem Beginn darf nicht als Nachtschicht durchgehen.');
} catch (InvalidArgumentException) {
}

echo "flz_akut shift workflow tests passed\n";
