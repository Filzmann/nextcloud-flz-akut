<?php

declare(strict_types=1);

namespace OCP {
    interface IAppConfig {
        public function getValueString(string $appId, string $key, string $default): string;
        public function setValueString(string $appId, string $key, string $value): void;
    }
    interface IConfig {
        public function getSystemValueString(string $key, string $default = ''): string;
    }
}

namespace {
    use OCA\FlzAkut\Service\OfficeDayService;

    require_once dirname(__DIR__) . '/bootstrap.php';

    $config = new class implements \OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default): string { return $this->values[$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$key] = $value; }
    };
    $system = new class implements \OCP\IConfig {
        public array $values = ['timezone' => 'Europe/Berlin'];
        public function getSystemValueString(string $key, string $default = ''): string { return $this->values[$key] ?? $default; }
    };
    $service = new OfficeDayService($config, $system);

    if ($service->timezone() !== 'Europe/Berlin') {
        throw new RuntimeException('Die Zeitzone muss aus den Nextcloud-Systemeinstellungen gelesen werden.');
    }
    $system->values['timezone'] = 'Mars/Olympus';
    if ($service->timezone() !== 'UTC') {
        throw new RuntimeException('Eine ungültige Instanz-Zeitzone muss auf UTC zurückfallen.');
    }
    $system->values['timezone'] = 'Europe/Berlin';

    if ($service->start() !== '06:00') {
        throw new RuntimeException('Ohne gespeicherten Wert muss der Standard-Bürotagbeginn 06:00 geliefert werden.');
    }

    $saved = $service->save('07:30');
    if ($saved !== '07:30' || $service->start() !== '07:30') {
        throw new RuntimeException('Ein gültiger Bürotagbeginn muss gespeichert und ausgelesen werden.');
    }

    $reset = $service->save('  ');
    if ($reset !== '06:00' || $service->start() !== '06:00') {
        throw new RuntimeException('Ein leerer Bürotagbeginn muss auf den Standard 06:00 zurückgesetzt werden.');
    }

    foreach (['6:00', '24:00', '12:60', 'abc', '06:00:00', '-1:00'] as $invalid) {
        try {
            $service->save($invalid);
            throw new RuntimeException("Ein ungültiger Bürotagbeginn ({$invalid}) darf nicht gespeichert werden.");
        } catch (InvalidArgumentException) {
        }
    }
    if ($service->start() !== '06:00') {
        throw new RuntimeException('Ein abgelehnter Bürotagbeginn darf den gespeicherten Wert nicht verändern.');
    }

    $service->save('06:00');
    $expected = (new DateTimeImmutable('today 06:00', new DateTimeZone('Europe/Berlin')))->setTimezone(new DateTimeZone('UTC'));
    $actual = $service->startTodayUtc();
    if ($actual->format('Y-m-d H:i:s') !== $expected->format('Y-m-d H:i:s') || $actual->format('P') !== '+00:00') {
        throw new RuntimeException('Der Bürotagbeginn muss heute 06:00 Europe/Berlin als UTC liefern.');
    }

    $service->save('23:45');
    $expected = (new DateTimeImmutable('today 23:45', new DateTimeZone('Europe/Berlin')))->setTimezone(new DateTimeZone('UTC'));
    if ($service->startTodayUtc()->format('Y-m-d H:i:s') !== $expected->format('Y-m-d H:i:s')) {
        throw new RuntimeException('Ein konfigurierter Bürotagbeginn muss für startTodayUtc übernommen werden.');
    }

    echo "flz_akut office day service tests passed\n";
}
