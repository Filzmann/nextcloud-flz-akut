<?php

declare(strict_types=1);

namespace OCP\Config {
    interface IUserConfig {
        public function getValueString(string $userId, string $appName, string $key, string $default = ''): string;
        public function setValueString(string $userId, string $appName, string $key, string $value): void;
    }
}

namespace {
    use OCA\FlzAkut\Service\AssistantInfoService;

    require_once dirname(__DIR__) . '/bootstrap.php';

    $config = new class implements \OCP\Config\IUserConfig {
        public array $store = [];
        public function getValueString(string $userId, string $appName, string $key, string $default = ''): string { return $this->store[$userId][$appName][$key] ?? $default; }
        public function setValueString(string $userId, string $appName, string $key, string $value): void { $this->store[$userId][$appName][$key] = $value; }
    };
    $service = new AssistantInfoService($config);

    // Leere Ausgangswerte
    if ($service->info('as1') !== ['tags' => [], 'note' => ''] || $service->comment('as1') !== '') {
        throw new RuntimeException('Ohne gespeicherte Werte müssen leere Info/leerer Kommentar geliefert werden.');
    }
    // Roundtrip mit Tag-Filterung
    $info = $service->saveInfo('as1', ['nacht', 'bogus', 'tag'], 'Nur nachts');
    $info2 = $service->saveInfo('as1', ['spaet', 'frueh', 'bogus'], 'Nur spät');
    if ($info2['tags'] !== ['spaet', 'frueh']) throw new RuntimeException('Tag nur Spät (spaet) muss erlaubt und einsortiert werden: ' . json_encode($info2));
    if ($info !== ['tags' => ['nacht', 'tag'], 'note' => 'Nur nachts']) {
        throw new RuntimeException('Tags müssen auf die zulässigen Werte gefiltert werden.');
    }
    if ($service->info('as1') !== $info2) {
        throw new RuntimeException('Die gespeicherte Info muss direkt beim AS-Nutzer wieder lesbar sein.');
    }
    // Längenkappe
    $long = $service->saveInfo('as2', [], str_repeat('x', 5000));
    if (strlen($long['note']) !== AssistantInfoService::MAX_NOTE) {
        throw new RuntimeException('Die Freitext-Notiz muss auf MAX_NOTE gekürzt werden.');
    }
    if ($service->saveComment('as1', 'ruft zurück') !== 'ruft zurück' || $service->comment('as1') !== 'ruft zurück') {
        throw new RuntimeException('Der interne Kommentar muss direkt beim AS-Nutzer gespeichert und gelesen werden.');
    }
    if (strlen($service->saveComment('as1', str_repeat('y', 5000))) !== AssistantInfoService::MAX_COMMENT) {
        throw new RuntimeException('Der Kommentar muss auf MAX_COMMENT gekürzt werden.');
    }
    // Kaputte gespeicherte Daten fallen auf leere Info zurück
    $config->setValueString('as3', AssistantInfoService::APP_ID, AssistantInfoService::KEY_INFO, '{kaputt');
    if ($service->info('as3') !== ['tags' => [], 'note' => '']) {
        throw new RuntimeException('Ungültiges JSON muss auf eine leere Info zurückfallen.');
    }

    echo "flz_akut assistant info service tests passed\n";
}
