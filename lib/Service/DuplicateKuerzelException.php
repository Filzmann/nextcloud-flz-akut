<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

/** Wird geworfen, wenn ein Kürzel bereits einem anderen Mitarbeiter gehört; enthält Vorschläge. */
final class DuplicateKuerzelException extends \DomainException {
    /** @param list<string> $suggestions */
    public function __construct(public readonly array $suggestions) {
        parent::__construct('Dieses Kürzel ist bereits vergeben.');
    }
}
