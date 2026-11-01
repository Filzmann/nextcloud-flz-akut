<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$source = (string)file_get_contents($root . '/lib/Controller/PermissionSettingsController.php');
$definition = require $root . '/appinfo/routes.php';
$route = null;
foreach ($definition['routes'] ?? [] as $candidate) {
    if (($candidate['name'] ?? null) === 'permission_settings#save') $route = $candidate;
}
if (($route['verb'] ?? null) !== 'PUT') {
    throw new RuntimeException('Die Berechtigungszuordnung ist nicht auf PUT begrenzt.');
}
if (!str_contains($source, 'public function save(')) {
    throw new RuntimeException('Der Endpunkt für Akutberechtigungen fehlt.');
}
$beforeSave = strstr($source, 'public function save(', true);
if ($beforeSave === false || str_contains(substr($beforeSave, -200), 'NoCSRFRequired') || str_contains(substr($beforeSave, -200), 'NoAdminRequired')) {
    throw new RuntimeException('Die Berechtigungszuordnung umgeht CSRF- oder Nextcloud-Adminschutz.');
}
if (!str_contains($source, '$this->settings->update($permissions)')) {
    throw new RuntimeException('Der Admin-Endpunkt verwendet nicht die validierende Rechtezuordnung.');
}

echo "flz_akut permission settings security contract tests passed\n";
