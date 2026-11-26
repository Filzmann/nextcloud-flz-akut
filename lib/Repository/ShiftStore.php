<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Repository;

interface ShiftStore {
    public function insert(array $shift): array;
    public function get(int $id): ?array;
    public function update(int $id, array $changes): array;
    public function addContact(array $attempt): array;
    /** @param list<int> $shiftIds @return list<array> */
    public function listContactsForShifts(array $shiftIds): array;
    public function addAudit(array $event): void;
    /** @param list<string> $bureauKeys */
    public function listForBureaux(array $bureauKeys): array;
    public function deleteExpired(string $cutoff): int;
    public function retentionCandidates(string $evaluatedAt, int $limit): array;
}
