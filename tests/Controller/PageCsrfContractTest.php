<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$page = file_get_contents($root . '/lib/Controller/PageController.php');
$shifts = file_get_contents($root . '/lib/Controller/ShiftController.php');
$retention = file_get_contents($root . '/lib/Controller/RetentionController.php');
$permissionSettings = file_get_contents($root . '/lib/Controller/PermissionSettingsController.php');
$routeDefinition = require $root . '/appinfo/routes.php';

if ($page === false || $shifts === false || $retention === false || $permissionSettings === false) {
    throw new RuntimeException('CSRF-Vertragsdateien konnten nicht gelesen werden.');
}

$attributesFor = static function (string $source, string $method): string {
    $pattern = '/(?<attributes>(?:#\[[^\]]*\]\s*)*)public function '
        . preg_quote($method, '/') . '\(/s';
    if (preg_match($pattern, $source, $matches) !== 1) {
        throw new RuntimeException("Controller-Methode {$method} fehlt.");
    }
    return $matches['attributes'];
};

$routes = [];
foreach ($routeDefinition['routes'] ?? [] as $route) {
    if (isset($route['name'])) {
        $routes[$route['name']] = $route;
    }
}

if (($routes['page#index']['verb'] ?? null) !== 'GET') {
    throw new RuntimeException('Der ausgenommene App-Einstieg ist keine reine GET-Route.');
}
if (!str_contains($attributesFor($page, 'index'), 'NoCSRFRequired')) {
    throw new RuntimeException('Der lesende App-Einstieg ist weiterhin durch die CSRF-Prüfung blockiert.');
}

foreach (['create', 'split', 'accept', 'reopen', 'contact', 'confirm'] as $method) {
    if (($routes['shift#' . $method]['verb'] ?? null) !== 'POST') {
        throw new RuntimeException("Schreibende Shift-Route {$method} ist nicht auf POST begrenzt.");
    }
    if (str_contains($attributesFor($shifts, $method), 'NoCSRFRequired')) {
        throw new RuntimeException("Schreibender Shift-Endpunkt {$method} umgeht den CSRF-Schutz.");
    }
}
if (($routes['retention#save']['verb'] ?? null) !== 'POST') {
    throw new RuntimeException('Die schreibende Retention-Route ist nicht auf POST begrenzt.');
}
if (str_contains($attributesFor($retention, 'save'), 'NoCSRFRequired')) {
    throw new RuntimeException('Schreibender Retention-Endpunkt umgeht den CSRF-Schutz.');
}
if (($routes['permission_settings#save']['verb'] ?? null) !== 'PUT') {
    throw new RuntimeException('Die Berechtigungszuordnung ist nicht auf PUT begrenzt.');
}
$permissionAttributes = $attributesFor($permissionSettings, 'save');
if (str_contains($permissionAttributes, 'NoCSRFRequired') || str_contains($permissionAttributes, 'NoAdminRequired')) {
    throw new RuntimeException('Die Berechtigungszuordnung umgeht CSRF- oder Nextcloud-Adminschutz.');
}

echo "flz_akut page CSRF contract tests passed\n";
