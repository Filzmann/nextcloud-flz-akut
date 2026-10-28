<?php

declare(strict_types=1);

use OCA\FlzAkut\Service\ProviderCompatibility;

require_once dirname(__DIR__) . '/bootstrap.php';

$compatibility = new ProviderCompatibility('1.0');
if ($compatibility->status(false, null) !== 'MISSING') throw new RuntimeException('Deaktivierter Provider muss sichtbar fehlen.');
if ($compatibility->status(true, null) !== 'INCOMPATIBLE') throw new RuntimeException('Provider ohne Vertrag muss inkompatibel sein.');
if ($compatibility->status(true, '2.0') !== 'INCOMPATIBLE') throw new RuntimeException('Unbekannte Vertragsversion muss inkompatibel sein.');
if ($compatibility->status(true, '1.0') !== 'AVAILABLE') throw new RuntimeException('Exakt unterstützte Vertragsversion muss verfügbar sein.');

echo "flz_akut provider compatibility tests passed\n";
