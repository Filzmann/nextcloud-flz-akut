<?php

declare(strict_types=1);

namespace OCP\Settings {
    if (!interface_exists(ISection::class)) { interface ISection { public function getID(): string; public function getName(): string; public function getPriority(): int; } }
    if (!interface_exists(IIconSection::class)) { interface IIconSection extends ISection { public function getIcon(): string; } }
    if (!interface_exists(ISettings::class)) { interface ISettings { public function getForm(); public function getSection(): string; public function getPriority(): int; } }
}

namespace {
    require_once dirname(__DIR__, 2) . '/tests/bootstrap.php';

    $root = dirname(__DIR__, 2);

    // Persönliche Einstellungen (/settings/user): Kürzel-Sektion muss in info.xml registriert und auflösbar sein
    $info = simplexml_load_file($root . '/appinfo/info.xml');
    if ($info === false) throw new RuntimeException('info.xml ist nicht wohlgeformt.');
    $personal = (string)($info->settings->personal ?? '');
    $personalSection = (string)($info->settings->{'personal-section'} ?? '');
    if ($personal !== 'OCA\\FlzAkut\\Settings\\PersonalProfile'
        || $personalSection !== 'OCA\\FlzAkut\\Settings\\PersonalProfileSection') {
        throw new RuntimeException('Persönliche Einstellungen (Kürzel) sind in info.xml nicht registriert.');
    }
    foreach ([$personal, $personalSection] as $class) {
        if (!class_exists($class)) throw new RuntimeException('Settings-Klasse fehlt: ' . $class);
    }
    if (!is_subclass_of($personal, \OCP\Settings\ISettings::class)) {
        throw new RuntimeException('PersonalProfile implementiert ISettings nicht.');
    }
    if (!is_subclass_of($personalSection, \OCP\Settings\IIconSection::class)) {
        throw new RuntimeException('PersonalProfileSection implementiert IIconSection nicht.');
    }

    // Schreib-Route für das Kürzel muss existieren und CSRF-geschützt sein (kein NoCSRFRequired)
    $routes = require $root . '/appinfo/routes.php';
    $routeNames = array_map(static fn(array $r): string => $r['name'], $routes['routes']);
    if (!in_array('profile#save_kuerzel', $routeNames, true)) {
        throw new RuntimeException('Route profile#save_kuerzel fehlt.');
    }
    $controller = (string)file_get_contents($root . '/lib/Controller/ProfileController.php');
    if (str_contains($controller, 'NoCSRFRequired')) {
        throw new RuntimeException('Profil-Schreibzugriff darf nicht CSRF-frei sein.');
    }
    if (!str_contains($controller, 'NoAdminRequired')) {
        throw new RuntimeException('Profil-Endpunkt muss NoAdminRequired tragen.');
    }

    // Template + JS müssen vorhanden sein
    foreach (['templates/profile-personal.php', 'js/profile-personal.js'] as $asset) {
        if (!is_file($root . '/' . $asset)) throw new RuntimeException('Asset fehlt: ' . $asset);
    }

    echo "PersonalSettingsContractTest: OK\n";
}
