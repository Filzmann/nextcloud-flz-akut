<?php

declare(strict_types=1);

use OCA\FlzAkut\Domain\ShiftWorkflow;
use OCA\FlzAkut\Tests\Support\InMemoryShiftStore;

require_once dirname(__DIR__) . '/bootstrap.php';

$store = new InMemoryShiftStore();
$workflow = new ShiftWorkflow($store);
$shift = $workflow->create('TeamA', 'west', '2030-06-03T08:00:00+02:00', '2030-06-03T20:00:00+02:00', 'Ersatz gesucht', 'day', 'dispatcher-a');
if ($shift['state'] !== 'open' || $shift['source_template_key'] !== 'day') throw new RuntimeException('Neue Schicht ist nicht offen oder verliert die unveränderliche Vorlagenreferenz.');

$parts = $workflow->split((int)$shift['id'], ['2030-06-03T13:30:00+02:00'], 'dispatcher-a');
if (count($parts) !== 2 || $parts[0]['ends_at'] !== $parts[1]['starts_at'] || $store->get((int)$shift['id'])['state'] !== 'split') throw new RuntimeException('Beliebige lückenfreie Teilung ersetzt die Ursprungsschicht nicht korrekt.');
$listed = $store->listForBureaux(['west']);
if (in_array('split', array_column($listed, 'state'), true)) throw new RuntimeException('Die Listing darf den Split-Ursprung nicht mehr ausliefern; nur die Teile bleiben übrig.');
if (count(array_filter($listed, static fn(array $s): bool => $s['state'] === 'open')) !== 2) throw new RuntimeException('Nach dem Teilen müssen genau die beiden neuen Teile gelistet werden.');
$pastListed = $store->listForBureaux(['west']);
$dayStart = new DateTimeImmutable('today 06:00', new DateTimeZone('Europe/Berlin'));
foreach ($pastListed as $row) {
    if (new DateTimeImmutable((string)$row['starts_at']) < $dayStart) throw new RuntimeException('Schichten, die vor Bürotagbeginn (heute 6:00 Uhr) beginnen, dürfen nicht mehr gelistet werden.');
}
if ($parts[0]['parent_shift_id'] !== null || $parts[1]['parent_shift_id'] !== null || $parts[0]['state'] !== 'open' || $parts[1]['state'] !== 'open') throw new RuntimeException('Geteilte Schichten müssen komplett getrennte, offene Schichten ohne Elternbezug sein.');

$accepted = $workflow->accept((int)$parts[0]['id'], 'assistant-a', 'ASN', '2026-10-27T10:00:00+01:00', true, 'dispatcher-a');
if ($accepted['state'] !== 'mediated' || $accepted['assigned_uid'] !== 'assistant-a' || $accepted['ma_label'] !== 'ASN') throw new RuntimeException('Eine Schicht wird nicht genau einer AS vermittelt.');

$reassignedEarly = $workflow->accept((int)$parts[0]['id'], 'assistant-b', 'EBA', '2026-10-27T10:01:00+01:00', false, 'dispatcher-b');
if ($reassignedEarly['assigned_uid'] !== 'assistant-b' || $reassignedEarly['state'] !== 'mediated') throw new RuntimeException('Vermittelte Schicht muss bis HyCare+Akte erneut vermittelbar bleiben.');

$reopened = $workflow->reopen((int)$parts[0]['id'], 'dispatcher-b');
if ($reopened['state'] !== 'open' || $reopened['assigned_uid'] !== null || count($store->audit) < 3) throw new RuntimeException('Wiederöffnung oder Audit ist unvollständig.');

$first = $workflow->addContact((int)$parts[1]['id'], 'assistant-a', 'phone', 'declined', '2026-10-27T10:10:00+01:00', 'dispatcher-a', null, 'Kann vielleicht ab 17 Uhr');
$second = $workflow->addContact((int)$parts[1]['id'], 'assistant-a', 'sms', 'open', '2026-10-27T11:10:00+01:00', 'dispatcher-b', null, 'Übermorgen erneut anfragen');
if ($first['id'] === $second['id'] || count($store->contacts) !== 2) throw new RuntimeException('Mehrere Kontaktversuche je AS und Schicht müssen erhalten bleiben.');

try {
    $workflow->confirmDocumentation((int)$parts[1]['id'], 'hycare', '2026-10-27T12:00:00+01:00', 'dispatcher-a');
    throw new RuntimeException('Dokumentation ohne vermittelte Assistenz darf nicht möglich sein.');
} catch (DomainException) {
}
$assigned = $workflow->accept((int)$parts[1]['id'], 'assistant-a', 'ASN', '2026-10-27T12:05:00+01:00', true, 'dispatcher-a');
$documented = $workflow->confirmDocumentation((int)$parts[1]['id'], 'hycare', '2026-10-27T12:10:00+01:00', 'dispatcher-a');
if ($documented['state'] !== 'mediated' || $documented['assigned_uid'] !== 'assistant-a' || $documented['hycare_at'] === null) throw new RuntimeException('Dokumentation nach Vermittlung muss gesetzt bleiben.');

$night = $workflow->create('TeamA', 'west', '2030-06-03T22:00:00+02:00', '2030-06-03T06:00:00+02:00', 'Nachtschicht', null, 'dispatcher-a');
if ($night['starts_at'] !== '2030-06-03T22:00:00+02:00' || $night['ends_at'] !== '2030-06-04T06:00:00+02:00') throw new RuntimeException('Nachtschicht (bis kleiner als von) muss am nächsten Tag enden.');

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

// Erneute Vermittlung und Freitext-AS (z. B. "abgesagt", "privat")
$reassigned = $workflow->accept((int)$parts[1]['id'], 'assistant-a', 'Neu', '2026-10-27T12:15:00+01:00', false, 'dispatcher-a');
if ($reassigned['ma_label'] !== 'Neu' || $reassigned['state'] !== 'mediated') throw new RuntimeException('Eine vermittelte Schicht muss erneut vermittelbar bleiben.');
$clearedLabel = $workflow->accept((int)$parts[1]['id'], 'assistant-a', '', '2026-10-27T12:16:00+01:00', false, 'dispatcher-a');
if ($clearedLabel['ma_label'] !== null) throw new RuntimeException('Ein geleertes MA-Kürzel muss als null gespeichert werden.');
$freeText = $workflow->update((int)$parts[0]['id'], 'privat', 'EBA', null, 'dispatcher-a');
if ($freeText['state'] !== 'mediated' || $freeText['assigned_uid'] !== 'privat') throw new RuntimeException('Freitext-AS (z. B. privat/abgesagt) muss übernommen werden.');
if (($freeText['mediated_at'] ?? null) === null) throw new RuntimeException('Die Besetzungseintragung muss mit Zeitstempel gespeichert werden.');
$mediatedAt = $freeText['mediated_at'];
$kurzelOnly = $workflow->update((int)$parts[0]['id'], null, 'ABC', null, 'dispatcher-a');
if ($kurzelOnly['ma_label'] !== 'ABC' || $kurzelOnly['mediated_at'] !== $mediatedAt) throw new RuntimeException('Eine reine Kürzel-Änderung darf die Besetzungszeit nicht verändern.');
foreach (['AB', 'ABCDEFG', 'AB1', 'EB/ASN'] as $badKurzel) {
    try {
        $workflow->update((int)$parts[0]['id'], null, $badKurzel, null, 'dispatcher-a');
        throw new RuntimeException("Kürzel '{$badKurzel}' darf nicht gespeichert werden.");
    } catch (InvalidArgumentException) {
    }
}
$umlaut = $workflow->update((int)$parts[0]['id'], null, 'ÄÖÜ', null, 'dispatcher-a');
if ($umlaut['ma_label'] !== 'ÄÖÜ') throw new RuntimeException('Umlaute müssen als Buchstaben im Kürzel erlaubt sein.');
$commented = $workflow->update((int)$parts[0]['id'], null, null, 'Kurzer Vermerk', 'dispatcher-a');
if ($commented['comment'] !== 'Kurzer Vermerk' || $commented['assigned_uid'] !== 'privat') throw new RuntimeException('Kommentar-Update darf nur das Kommentarfeld ändern.');
try {
    $workflow->update((int)$parts[0]['id'], '', null, null, 'dispatcher-a');
    throw new RuntimeException('Eine leere Assistenz darf nicht gespeichert werden.');
} catch (InvalidArgumentException) {
}
try {
    $workflow->update((int)$parts[0]['id'], null, null, str_repeat('x', 2001), 'dispatcher-a');
    throw new RuntimeException('Ein zu langer Kommentar darf nicht gespeichert werden.');
} catch (InvalidArgumentException) {
}

// Sperre der Assistenz nach HyCare- UND Akteneintrag
$workflow->confirmDocumentation((int)$parts[1]['id'], 'paper', '2026-10-27T12:20:00+01:00', 'dispatcher-a');
try {
    $workflow->update((int)$parts[1]['id'], 'assistant-b', null, null, 'dispatcher-a');
    throw new RuntimeException('Nach HyCare- und Akteneintrag darf die Assistenz nicht mehr geändert werden.');
} catch (DomainException) {
}
$sameUid = $workflow->update((int)$parts[1]['id'], 'assistant-a', 'ABC', null, 'dispatcher-a');
if ($sameUid['ma_label'] !== 'ABC' || $sameUid['assigned_uid'] !== 'assistant-a') throw new RuntimeException('Kürzel bleibt nach HyCare/Akte änderbar, wenn die Assistenz gleich bleibt.');
$commentAfterLock = $workflow->update((int)$parts[1]['id'], null, null, 'Nachträglich', 'dispatcher-a');
if ($commentAfterLock['comment'] !== 'Nachträglich') throw new RuntimeException('Kommentar bleibt nach HyCare/Akte änderbar.');

// Wiederöffnung setzt die Dokumentationsmarkierungen zurück und aktiviert die Bearbeitung erneut
$workflow->reopen((int)$parts[1]['id'], 'dispatcher-a');
$afterReopen = $store->get((int)$parts[1]['id']);
if ($afterReopen['hycare_at'] !== null || $afterReopen['paper_at'] !== null || $afterReopen['state'] !== 'open') throw new RuntimeException('Wiederöffnung muss HyCare-/Akte-Markierungen zurücksetzen, damit das AS-Feld wieder aktiv ist.');
$reassignedAfterReopen = $workflow->update((int)$parts[1]['id'], 'assistant-b', 'EBA', null, 'dispatcher-a');
if ($reassignedAfterReopen['assigned_uid'] !== 'assistant-b' || $reassignedAfterReopen['state'] !== 'mediated') throw new RuntimeException('Nach Wiederöffnung muss die AS wieder eintragbar sein.');

// Split-Ursprung bleibt unveränderlich
try {
    $workflow->update((int)$shift['id'], null, null, 'x', 'dispatcher-a');
    throw new RuntimeException('Der Split-Ursprung darf nicht verändert werden.');
} catch (DomainException) {
}

// Bearbeitung ist ab Schichtbeginn gesperrt (AS, Kürzel, Kommentar)
$past = $workflow->create('TeamA', 'west', '2020-01-01T08:00:00+01:00', '2020-01-01T20:00:00+01:00', '', null, 'dispatcher-a');
try {
    $workflow->update((int)$past['id'], 'assistant-a', null, null, 'dispatcher-a');
    throw new RuntimeException('Eine begonnene Schicht darf nicht mehr besetzt werden.');
} catch (DomainException) {
}
try {
    $workflow->update((int)$past['id'], null, 'ASN', null, 'dispatcher-a');
    throw new RuntimeException('Das Kürzel einer begonnenen Schicht darf nicht mehr geändert werden.');
} catch (DomainException) {
}
try {
    $workflow->update((int)$past['id'], null, null, 'x', 'dispatcher-a');
    throw new RuntimeException('Der Kommentar einer begonnenen Schicht darf nicht mehr geändert werden.');
} catch (DomainException) {
}
try {
    $workflow->accept((int)$past['id'], 'assistant-a', 'ASN', '2020-01-01T09:00:00+01:00', false, 'dispatcher-a');
    throw new RuntimeException('Eine begonnene Schicht darf nicht mehr über accept besetzt werden.');
} catch (DomainException) {
}

$listedWithPast = $store->listForBureaux(['west']);
foreach ($listedWithPast as $row) {
    if ((int)$row['id'] === (int)$past['id']) throw new RuntimeException('Eine vor heute 0:00 Uhr beendete (vergangene) Schicht darf nicht mehr gelistet werden.');
}
// Am Schichtbeginn orientieren: beginnt eine Schicht ab Bürotagbeginn (heute 6:00 Uhr), bleibt sie sichtbar — egal wann sie endet
$todayStart = (new DateTimeImmutable('today 06:00', new DateTimeZone('Europe/Berlin')))->setTimezone(new DateTimeZone('UTC'));
$todayStarted = $workflow->create('TeamA', 'west', $todayStart->modify('+1 hour')->format(DATE_ATOM), $todayStart->modify('+5 hours')->format(DATE_ATOM), '', null, 'dispatcher-a');
$earlyToday = $workflow->create('TeamA', 'west', $todayStart->modify('-4 hours')->format(DATE_ATOM), $todayStart->modify('+2 hours')->format(DATE_ATOM), '', null, 'dispatcher-a');
$listedIds = array_column($store->listForBureaux(['west']), 'id');
if (!in_array((int)$todayStarted['id'], $listedIds, true)) throw new RuntimeException('Eine ab Bürotagbeginn (heute 6:00 Uhr) beginnende Schicht muss weiterhin gelistet werden, auch wenn sie noch läuft.');
if (in_array((int)$earlyToday['id'], $listedIds, true)) throw new RuntimeException('Eine vor Bürotagbeginn beginnende Schicht darf nicht mehr gelistet werden, auch wenn sie noch läuft.');

// Kontaktergebnisse not_reached (Tel n.e.) und ab (Tel AB) sind zulässig, unbekannte Ergebnisse nicht
$contact = $workflow->addContact((int)$todayStarted['id'], 'assistant-a', 'phone', 'not_reached', '2030-06-03T07:00:00+02:00', 'dispatcher-a', null, 'n.e.');
if ($contact['outcome'] !== 'not_reached') throw new RuntimeException('not_reached muss als Kontaktergebnis erlaubt sein.');
$contactAb = $workflow->addContact((int)$todayStarted['id'], 'assistant-a', 'phone', 'ab', '2030-06-03T07:05:00+02:00', 'dispatcher-a', null, 'AB');
if ($contactAb['outcome'] !== 'ab') throw new RuntimeException('ab (Anrufbeantworter) muss als Kontaktergebnis erlaubt sein.');
try {
    $workflow->addContact((int)$todayStarted['id'], 'assistant-a', 'phone', 'vielleicht', '2030-06-03T07:00:00+02:00', 'dispatcher-a', null, null);
    throw new RuntimeException('Unbekannte Kontaktergebnisse dürfen nicht gespeichert werden.');
} catch (InvalidArgumentException) {
}

// MTV-Marker ("Mail versendet"): Prefix MTV <dd.mm> <Kürzel des MA>| ganz am Anfang des Kommentars, auch nach Schichtbeginn
$futureStart = (new DateTimeImmutable('tomorrow 08:00', new DateTimeZone('Europe/Berlin')))->setTimezone(new DateTimeZone('UTC'));
$mtvShift = $workflow->create('TeamA', 'west', $futureStart->format(DATE_ATOM), $futureStart->modify('+4 hours')->format(DATE_ATOM), '', null, 'dispatcher-a');
$workflow->accept((int)$mtvShift['id'], 'assistant-a', 'EBA', '2030-06-03T06:30:00+02:00', true, 'dispatcher-a');
$workflow->update((int)$mtvShift['id'], null, null, 'alter Kommentar', 'dispatcher-a');
$marked = $workflow->markMailSent((int)$mtvShift['id'], '26.11.', 'EBA', 'dispatcher-a');
if (!str_starts_with((string)$marked['comment'], 'MTV 26.11. EBA|alter Kommentar')) {
    throw new RuntimeException('Der MTV-Marker muss ganz am Anfang des Kommentars stehen (MTV <dd.mm> <Kürzel>| + bisheriger Kommentar).');
}
$markedAgain = $workflow->markMailSent((int)$mtvShift['id'], '27.11.', 'EBA', 'dispatcher-a');
if (!str_starts_with((string)$markedAgain['comment'], 'MTV 27.11. EBA|MTV 26.11. EBA|')) {
    throw new RuntimeException('Erneutes Markieren stellt den neuen MTV-Marker vorne an.');
}
if (!in_array('mail_sent_marked', array_column($store->audit, 'action'), true)) {
    throw new RuntimeException('Der MTV-Marker muss als Audit-Ereignis protokolliert werden.');
}
$splitOrigin = $workflow->create('TeamA', 'west', $todayStart->modify('+6 hours')->format(DATE_ATOM), $todayStart->modify('+10 hours')->format(DATE_ATOM), '', null, 'dispatcher-a');
$workflow->split((int)$splitOrigin['id'], [$todayStart->modify('+8 hours')->format(DATE_ATOM)], 'dispatcher-a');
try {
    $workflow->markMailSent((int)$splitOrigin['id'], '26.11.', 'EBA', 'dispatcher-a');
    throw new RuntimeException('Der Split-Ursprung darf nicht mit MTV markiert werden.');
} catch (InvalidArgumentException) {
}

// Konfigurierter Bürotagbeginn (z. B. 08:00 Uhr) wird als Stichtag am Schichtbeginn respektiert
$customStore = new InMemoryShiftStore('08:00');
$customWorkflow = new ShiftWorkflow($customStore);
$dayStart8 = (new DateTimeImmutable('today 08:00', new DateTimeZone('Europe/Berlin')))->setTimezone(new DateTimeZone('UTC'));
$startsBefore8 = $customWorkflow->create('TeamA', 'west', $dayStart8->modify('-1 hour')->format(DATE_ATOM), $dayStart8->modify('+3 hours')->format(DATE_ATOM), '', null, 'dispatcher-a');
$startsAfter8 = $customWorkflow->create('TeamA', 'west', $dayStart8->modify('+1 hour')->format(DATE_ATOM), $dayStart8->modify('+4 hours')->format(DATE_ATOM), '', null, 'dispatcher-a');
$customIds = array_column($customStore->listForBureaux(['west']), 'id');
if (in_array((int)$startsBefore8['id'], $customIds, true)) throw new RuntimeException('Mit Bürotagbeginn 08:00 Uhr darf eine um 07:00 Uhr beginnende Schicht nicht gelistet werden, auch wenn sie noch läuft.');
if (!in_array((int)$startsAfter8['id'], $customIds, true)) throw new RuntimeException('Mit Bürotagbeginn 08:00 Uhr muss eine um 09:00 Uhr beginnende Schicht gelistet werden.');

// Grenze genau am Schichtbeginn; HyCare/Akte und Wiederöffnung bleiben danach möglich
$workflow->setClock(static fn(): DateTimeImmutable => new DateTimeImmutable('2030-06-03T07:59:59+02:00'));
$boundary = $workflow->create('TeamB', 'west', '2030-06-03T08:00:00+02:00', '2030-06-03T12:00:00+02:00', '', null, 'dispatcher-a');
$workflow->accept((int)$boundary['id'], 'assistant-a', 'ASN', '2030-06-03T07:59:59+02:00', false, 'dispatcher-a');
$workflow->setClock(static fn(): DateTimeImmutable => new DateTimeImmutable('2030-06-03T08:00:00+02:00'));
try {
    $workflow->update((int)$boundary['id'], 'assistant-b', null, null, 'dispatcher-a');
    throw new RuntimeException('Ab Schichtbeginn darf nicht mehr bearbeitet werden.');
} catch (DomainException) {
}
$workflow->confirmDocumentation((int)$boundary['id'], 'hycare', '2030-06-03T09:00:00+02:00', 'dispatcher-a');
$workflow->reopen((int)$boundary['id'], 'dispatcher-a');
$workflow->setClock(static fn(): DateTimeImmutable => new DateTimeImmutable());

echo "flz_akut shift workflow tests passed\n";
