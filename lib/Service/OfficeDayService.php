<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\IAppConfig;
use OCP\IConfig;

final class OfficeDayService {
    private const APP_ID = 'flz_akut';
    private const KEY = 'office_day_start';
    public const DEFAULT_START = '06:00';

    public function __construct(private IAppConfig $config, private IConfig $systemConfig) {}

    // Instanz-Zeitzone aus den Nextcloud-Systemeinstellungen (config.php 'timezone'), Fallback UTC
    public function timezone(): string {
        $tz = trim($this->systemConfig->getSystemValueString('timezone', 'UTC'));
        return in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : 'UTC';
    }

    public function start(): string {
        $value = trim($this->config->getValueString(self::APP_ID, self::KEY, ''));
        return $value === '' ? self::DEFAULT_START : $value;
    }

    public function save(string $start): string {
        $start = trim($start);
        if ($start === '') $start = self::DEFAULT_START;
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $start) !== 1) {
            throw new \InvalidArgumentException('Der Bürotagbeginn muss als Uhrzeit im Format HH:MM (00:00 bis 23:59) angegeben werden.');
        }
        $this->config->setValueString(self::APP_ID, self::KEY, $start);
        return $start;
    }

    // Bürotagbeginn heute in der Instanz-Zeitzone als UTC für Datenbankvergleiche
    public function startTodayUtc(): \DateTimeImmutable {
        return (new \DateTimeImmutable('today ' . $this->start(), new \DateTimeZone($this->timezone())))->setTimezone(new \DateTimeZone('UTC'));
    }
}
