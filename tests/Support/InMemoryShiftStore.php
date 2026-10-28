<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Tests\Support;

use OCA\FlzAkut\Repository\ShiftStore;

final class InMemoryShiftStore implements ShiftStore {
    public array $shifts = [];
    public array $contacts = [];
    public array $audit = [];
    private int $nextId = 1;

    public function insert(array $shift): array { $shift['id'] = $this->nextId++; return $this->shifts[$shift['id']] = $shift; }
    public function get(int $id): ?array { return $this->shifts[$id] ?? null; }
    public function update(int $id, array $changes): array { return $this->shifts[$id] = array_replace($this->shifts[$id], $changes); }
    public function addContact(array $attempt): array { $attempt['id'] = count($this->contacts) + 1; $this->contacts[] = $attempt; return $attempt; }
    public function addAudit(array $event): void { $this->audit[] = $event; }
    public function listForBureaux(array $bureauKeys): array { return array_values(array_filter($this->shifts, static fn(array $s): bool => in_array($s['bureau_key'], $bureauKeys, true))); }
    public function deleteExpired(string $cutoff): int { return 0; }
    public function retentionCandidates(string $evaluatedAt, int $limit): array { return []; }
}
