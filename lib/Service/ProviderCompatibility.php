<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

final class ProviderCompatibility {
    public function __construct(private string $supportedVersion) {}
    public function status(bool $enabled, ?string $version): string {
        if (!$enabled) return 'MISSING';
        return $version === $this->supportedVersion ? 'AVAILABLE' : 'INCOMPATIBLE';
    }
}
