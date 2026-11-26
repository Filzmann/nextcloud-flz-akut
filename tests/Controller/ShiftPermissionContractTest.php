<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/lib/Controller/ShiftController.php');
if ($source === false) throw new RuntimeException('ShiftController konnte nicht gelesen werden.');

$contracts = [
    'state' => 'AkutCapability::READ',
    'create' => 'AkutCapability::CREATE',
    'split' => 'AkutCapability::SPLIT',
    'accept' => 'AkutCapability::ASSIGN',
    'update' => 'AkutCapability::ASSIGN',
    'reopen' => 'AkutCapability::REOPEN',
    'contact' => 'AkutCapability::CONTACT',
    'saveAssistantMeta' => 'AkutCapability::CONTACT',
    'mailSent' => 'AkutCapability::CREATE',
];
foreach ($contracts as $method => $capability) {
    $pattern = '/public function ' . preg_quote($method, '/') . '\([^}]+?' . preg_quote($capability, '/') . '/s';
    if (preg_match($pattern, $source) !== 1) {
        throw new RuntimeException("{$method} prüft nicht serverseitig {$capability}.");
    }
}
if (preg_match('/public function update\\([^}]+?AkutCapability::CREATE/s', $source) !== 1) {
    throw new RuntimeException('update prüft serverseitig nicht AkutCapability::CREATE für das Kommentarfeld.');
}
foreach (['AkutCapability::CONFIRM_HYCARE', 'AkutCapability::CONFIRM_PAPER'] as $capability) {
    if (!str_contains($source, $capability)) {
        throw new RuntimeException("Dokumentationsbestätigung prüft nicht getrennt {$capability}.");
    }
}
if (!str_contains($source, 'assertBureau(') || !str_contains($source, 'authorizedShift(')) {
    throw new RuntimeException('Objekt- und Büroscope werden nicht gemeinsam serverseitig geprüft.');
}

echo "flz_akut shift permission controller contract tests passed\n";
