<?php

declare(strict_types=1);

namespace OCP\Config {
    interface IUserConfig {
        public function getValueString(string $userId, string $appName, string $key, string $default = ''): string;
        public function setValueString(string $userId, string $appName, string $key, string $value): void;
        public function getUsersForUserValue(string $appName, string $key, string $value): array;
    }
}

namespace {
    use OCA\FlzAkut\Service\DuplicateKuerzelException;
    use OCA\FlzAkut\Service\MaKuerzelService;

    require_once dirname(__DIR__) . '/bootstrap.php';

    $config = new class implements \OCP\Config\IUserConfig {
        public array $store = [];
        public function getValueString(string $userId, string $appName, string $key, string $default = ''): string { return $this->store[$userId][$appName][$key] ?? $default; }
        public function setValueString(string $userId, string $appName, string $key, string $value): void { $this->store[$userId][$appName][$key] = $value; }
        public function getUsersForUserValue(string $appName, string $key, string $value): array {
            $out = [];
            foreach ($this->store as $uid => $apps) {
                if (($apps[$appName][$key] ?? null) === $value) $out[] = $uid;
            }
            return $out;
        }
    };
    $service = new MaKuerzelService($config);

    // Vorbelegung: erste zwei Buchstaben des Nachnamens, Fallback Initialen
    if ($service->derive('Tom Dispatcher') !== 'DI' || $service->derive('Anna Schmidt') !== 'SC' || $service->derive('A B') !== 'AB' || $service->derive('') !== '') {
        throw new RuntimeException('Vorbelegung: erste zwei Buchstaben des Nachnamens, sonst Initialen.');
    }
    // Format: max 4 Zeichen, Buchstaben groß/klein und Punkt, mindestens ein Buchstabe
    foreach (['D', 'ab', 'D.I', 'Mü.'] as $valid) {
        if (!$service->valid($valid)) throw new RuntimeException("Kürzel {$valid} muss gültig sein.");
    }
    foreach (['ABCDE', 'AB1', 'AB CD', '.', '..', ''] as $invalid) {
        if ($invalid !== '' && $service->valid($invalid)) throw new RuntimeException("Kürzel {$invalid} darf nicht gültig sein.");
    }
    foreach (['ABCDE', 'AB1', 'AB CD', '.'] as $invalid) {
        try {
            $service->save('u3', $invalid, 'Tom Dispatcher');
            throw new RuntimeException("Ungültiges Kürzel {$invalid} darf nicht gespeichert werden.");
        } catch (InvalidArgumentException) {
        }
    }
    // Eindeutigkeit (case-insensitiv): zweiter Mitarbeiter mit gleichem Kürzel -> DuplicateKuerzelException mit Vorschlägen
    if ($service->save('u1', 'EBA', 'Eba Beispiel') !== 'EBA') throw new RuntimeException('Erstbelegung muss erlaubt sein.');
    try {
        $service->save('u2', 'eba', 'Tom Dispatcher');
        throw new RuntimeException('Bereits vergebenes Kürzel (case-insensitiv) darf nicht erneut gespeichert werden.');
    } catch (DuplicateKuerzelException $e) {
        if ($e->suggestions === []) throw new RuntimeException('Bei Kollision müssen Vorschläge geliefert werden.');
        foreach ($e->suggestions as $suggestion) {
            if (!$service->valid($suggestion)) throw new RuntimeException("Vorschlag {$suggestion} ist ungültig.");
            if ($service->takenBy($suggestion, 'u2') !== []) throw new RuntimeException("Vorschlag {$suggestion} ist bereits vergeben.");
            if ($service->normalize($suggestion) === 'eba') throw new RuntimeException('Vorschlag darf nicht dem kollidierenden Kürzel entsprechen.');
        }
        if (count($e->suggestions) > 5) throw new RuntimeException('Höchstens 5 Vorschläge.');
    }
    // Eigene Änderung auf das eigene Kürzel ist erlaubt
    if ($service->save('u1', 'EBA', 'Eba Beispiel') !== 'EBA') throw new RuntimeException('Das eigene Kürzel darf unverändert bleiben.');
    // Anderes freies Kürzel ist erlaubt
    if ($service->save('u2', 'DI', 'Tom Dispatcher') !== 'DI') throw new RuntimeException('Freies Kürzel muss gespeichert werden.');
    // Gespeichertes Kürzel gewinnt gegenüber der Vorbelegung
    if ($service->effective('u1', 'Eba Beispiel') !== 'EBA' || $service->effective('u9', 'Tom Dispatcher') !== 'DI') {
        throw new RuntimeException('Gespeichertes Kürzel gewinnt; sonst Vorbelegung.');
    }
    // Leer löscht
    if ($service->save('u1', '') !== '' || $service->stored('u1') !== '') {
        throw new RuntimeException('Ein leeres Kürzel muss erlaubt sein und löscht den Eintrag.');
    }

    echo "flz_akut ma kürzel service tests passed\n";
}
