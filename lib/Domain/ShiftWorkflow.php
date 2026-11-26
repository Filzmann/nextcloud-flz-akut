<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Domain;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use OCA\FlzAkut\Repository\ShiftStore;
use OCA\FlzAkut\Service\RetentionSettingsService;

final class ShiftWorkflow {
    /** @var callable():DateTimeImmutable */
    private $clock;

    public function __construct(private ShiftStore $store, private ?RetentionPolicy $retention = null, private ?RetentionSettingsService $retentionSettings = null) {
        $this->clock = static fn(): DateTimeImmutable => new DateTimeImmutable();
    }

    /** Test-Hook: Uhr für die Bearbeitungsgrenze am Schichtbeginn. */
    public function setClock(callable $clock): void { $this->clock = $clock; }

    private function now(): DateTimeImmutable { return ($this->clock)(); }

    public function create(string $teamCode, string $bureauKey, string $startsAt, string $endsAt, string $comment, ?string $sourceTemplateKey, string $actorUid): array {
        [$start, $end] = $this->interval($startsAt, $endsAt);
        $shift = $this->store->insert([
            'team_code' => $this->text($teamCode, 64, 'Teamcode'), 'bureau_key' => $this->text($bureauKey, 64, 'Büro'),
            'starts_at' => $start->format(DATE_ATOM), 'ends_at' => $end->format(DATE_ATOM), 'state' => 'open',
            'assigned_uid' => null, 'comment' => $this->optionalText($comment, 2000), 'source_template_key' => $sourceTemplateKey,
            'parent_shift_id' => null, 'ma_label' => null, 'mediated_at' => null, 'informed_asn' => false,
            'hycare_at' => null, 'hycare_by' => null, 'paper_at' => null, 'paper_by' => null,
            'created_by' => $this->text($actorUid, 255, 'Akteur'), 'expires_at' => ($this->retention ?? new RetentionPolicy())->expiresAt($end->format(DATE_ATOM), $this->retentionSettings?->days() ?? 180)->format(DATE_ATOM),
        ]);
        $this->audit($shift, 'created', $actorUid);
        return $shift;
    }

    /** @param list<string> $cutPoints @return list<array<string,mixed>> */
    public function split(int $id, array $cutPoints, string $actorUid): array {
        $original = $this->openShift($id);
        $start = new DateTimeImmutable($original['starts_at']); $end = new DateTimeImmutable($original['ends_at']);
        $cuts = array_map(static fn(string $cut): DateTimeImmutable => new DateTimeImmutable($cut), $cutPoints);
        usort($cuts, static fn(DateTimeImmutable $a, DateTimeImmutable $b): int => $a <=> $b);
        if ($cuts === []) throw new InvalidArgumentException('Mindestens eine Teilungszeit ist erforderlich.');
        $previous = $start;
        $boundaries = [...$cuts, $end]; $parts = [];
        foreach ($boundaries as $boundary) {
            if ($boundary <= $previous || $boundary > $end) throw new InvalidArgumentException('Teilungszeiten müssen strikt innerhalb der Schicht liegen.');
            $part = $original;
            unset($part['id']);
            $part['starts_at'] = $previous->format(DATE_ATOM); $part['ends_at'] = $boundary->format(DATE_ATOM);
            $part['parent_shift_id'] = null; $part['source_template_key'] = $original['source_template_key'];
            $part['expires_at'] = ($this->retention ?? new RetentionPolicy())->expiresAt($boundary->format(DATE_ATOM), $this->retentionSettings?->days() ?? 180)->format(DATE_ATOM);
            $parts[] = $this->store->insert($part); $previous = $boundary;
        }
        $this->store->update($id, ['state' => 'split']);
        $this->audit($original, 'split', $actorUid);
        return $parts;
    }

    public function accept(int $id, string $assistantUid, string $maLabel, string $mediatedAt, bool $informedAsn, string $actorUid): array {
        return $this->applyAssignment($this->assignable($id), $assistantUid, $maLabel, $mediatedAt, $informedAsn, $actorUid);
    }

    public function update(int $id, ?string $assistantUid, ?string $maLabel, ?string $comment, string $actorUid): array {
        $shift = $this->assignable($id);
        if ($assistantUid === null && $maLabel === null && $comment === null) throw new InvalidArgumentException('Es wurde kein änderbares Feld übermittelt.');
        if ($assistantUid !== null || $maLabel !== null) {
            $shift = $this->applyAssignment($shift, $assistantUid, $maLabel, null, null, $actorUid);
        }
        if ($comment !== null) {
            $shift = $this->store->update($id, ['comment' => $this->optionalText($comment, 2000)]);
            $this->audit($shift, 'comment_updated', $actorUid);
        }
        return $shift;
    }

    /**
     * MTV-Marker ("Mail versendet") ganz am Anfang des Kommentars — reiner Dokumentationsvermerk,
     * daher auch nach Schichtbeginn zulässig (die Sperre gilt der Zuweisung, nicht dem Kommentar).
     */
    public function markMailSent(int $id, string $day, string $actorKuerzel, string $actorUid): array {
        $shift = $this->required($id);
        if (($shift['state'] ?? '') === 'split') throw new InvalidArgumentException('Der Split-Ursprung ist unveränderlich.');
        $prefix = 'MTV ' . $day . ' ' . trim($actorKuerzel) . '|';
        $updated = $this->store->update($id, ['comment' => $this->optionalText($prefix . (string)($shift['comment'] ?? ''), 2000)]);
        $this->audit($updated, 'mail_sent_marked', $actorUid);
        return $updated;
    }

    public function reopen(int $id, string $actorUid): array {
        $shift = $this->required($id);
        if ($shift['state'] !== 'mediated') throw new DomainException('Nur vermittelte Schichten können erneut geöffnet werden.');
        $updated = $this->store->update($id, ['state' => 'open', 'assigned_uid' => null, 'ma_label' => null, 'mediated_at' => null, 'informed_asn' => false, 'hycare_at' => null, 'hycare_by' => null, 'paper_at' => null, 'paper_by' => null]);
        $this->audit($shift, 'reopened', $actorUid);
        return $updated;
    }

    public function addContact(int $shiftId, string $assistantUid, string $channel, string $outcome, string $contactedAt, string $actorUid, ?string $reason, ?string $note): array {
        $this->required($shiftId);
        if (!in_array($channel, ['phone', 'sms'], true)) throw new InvalidArgumentException('Kontaktkanal ist ungültig.');
        if (!in_array($outcome, ['open', 'accepted', 'declined', 'not_reached', 'ab'], true)) throw new InvalidArgumentException('Kontaktergebnis ist ungültig.');
        return $this->store->addContact([
            'shift_id' => $shiftId, 'assistant_uid' => $this->text($assistantUid, 255, 'Assistenz'), 'channel' => $channel, 'outcome' => $outcome,
            'contacted_at' => (new DateTimeImmutable($contactedAt))->format(DATE_ATOM), 'actor_uid' => $this->text($actorUid, 255, 'Akteur'),
            'reason' => $reason === null ? null : $this->optionalText($reason, 80), 'note' => $note === null ? null : $this->optionalText($note, 500),
        ]);
    }

    public function confirmDocumentation(int $id, string $kind, string $at, string $actorUid): array {
        $shift = $this->required($id);
        if (!in_array($kind, ['hycare', 'paper'], true)) throw new InvalidArgumentException('Dokumentationsziel ist ungültig.');
        if (($shift['assigned_uid'] ?? null) === null || $shift['assigned_uid'] === '') throw new DomainException('HyCare-/Akteneintrag ist erst nach Vermittlung einer Assistenz möglich.');
        $updated = $this->store->update($id, [$kind . '_at' => (new DateTimeImmutable($at))->format(DATE_ATOM), $kind . '_by' => $actorUid]);
        $this->audit($shift, $kind . '_confirmed', $actorUid);
        return $updated;
    }

    private function openShift(int $id): array { $shift = $this->required($id); if ($shift['state'] !== 'open') throw new DomainException('Die Schicht ist nicht offen.'); return $shift; }
    private function assignable(int $id): array {
        $shift = $this->required($id);
        if ($shift['state'] === 'split') throw new DomainException('Der Ursprung einer Teilung ist nicht mehr veränderbar.');
        if (new DateTimeImmutable((string)$shift['starts_at']) <= $this->now()) throw new DomainException('Die Schicht hat begonnen; die Bearbeitung ist gesperrt.');
        return $shift;
    }
    private function applyAssignment(array $shift, ?string $assistantUid, ?string $maLabel, ?string $mediatedAt, ?bool $informedAsn, string $actorUid): array {
        $changes = [];
        if ($assistantUid !== null) {
            $assigned = $this->text($assistantUid, 255, 'Assistenz');
            $locked = ($shift['hycare_at'] ?? null) !== null && ($shift['paper_at'] ?? null) !== null;
            if ($locked && $assigned !== ($shift['assigned_uid'] ?? null)) throw new DomainException('Nach HyCare- und Akteneintrag ist die Vermittlung abgeschlossen; die Assistenz ist nicht mehr änderbar.');
            $reassigned = $shift['state'] === 'open' || $assigned !== ($shift['assigned_uid'] ?? null);
            $changes['assigned_uid'] = $assigned;
            if ($shift['state'] === 'open') $changes['state'] = 'mediated';
            if ($reassigned) $changes['mediated_at'] = ($mediatedAt !== null ? new DateTimeImmutable($mediatedAt) : $this->now())->format(DATE_ATOM);
        }
        $changes['ma_label'] = $maLabel !== null ? $this->kurzel($maLabel) : ($shift['ma_label'] ?? null);
        if ($informedAsn !== null) $changes['informed_asn'] = $informedAsn;
        $updated = $this->store->update((int)$shift['id'], $changes);
        $this->audit($shift, $assistantUid !== null ? 'mediated' : 'updated', $actorUid);
        return $updated;
    }
    private function required(int $id): array { $shift = $this->store->get($id); if ($shift === null) throw new DomainException('Schicht nicht gefunden.'); return $shift; }
    private function interval(string $startsAt, string $endsAt): array { $start = new DateTimeImmutable($startsAt); $end = new DateTimeImmutable($endsAt); if ($end < $start) $end = $end->modify('+1 day'); if ($end <= $start) throw new InvalidArgumentException('Schichtende muss nach dem Beginn liegen.'); return [$start, $end]; }
    private function text(string $value, int $max, string $name): string { $value = trim($value); if ($value === '' || strlen($value) > $max) throw new InvalidArgumentException($name . ' ist ungültig.'); return $value; }
    private function optionalText(string $value, int $max): string { $value = trim($value); if (strlen($value) > $max) throw new InvalidArgumentException('Text ist zu lang.'); return $value; }
    private function nullableText(string $value, int $max): ?string { $value = trim($value); if (strlen($value) > $max) throw new InvalidArgumentException('Text ist zu lang.'); return $value === '' ? null : $value; }
    private function kurzel(?string $value): ?string {
        if ($value === null) return null;
        $value = trim($value);
        if ($value === '') return null;
        if (!preg_match('/^\p{L}{3,6}$/u', $value)) throw new InvalidArgumentException('Das Kürzel muss aus 3 bis 6 Buchstaben bestehen.');
        return $value;
    }
    private function audit(array $shift, string $action, string $actor): void { $this->store->addAudit(['shift_id' => $shift['id'], 'action' => $action, 'actor_uid' => $actor, 'occurred_at' => (new DateTimeImmutable())->format(DATE_ATOM)]); }
}
