<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\App\IAppManager;
use OCP\Server;

/**
 * Gateway für Feiertagsdaten.
 * 
 * Priorität:
 * 1. flz_organization (neue kanonische Quelle für Kalenderkontext und Feiertage)
 * 2. localbase (historischer Fallback für Kompatibilität)
 */
final class HolidayGateway {
    private const FLZ_ORGANIZATION_CLASS = 'OCA\\FlzOrganization\\PublicApi\\V1\\HolidayCalendarProvider';
    private const LOCALBASE_CLASS = 'OCA\\LocalBase\\PublicApi\\V1\\HolidayCalendarProvider';

    public function __construct(private IAppManager $apps) {}

    /**
     * Liefert Feiertage für ein bestimmtes Jahr.
     * 
     * @param int $year Das Jahr
     * @return array{status: string, dates: array, timezone: string|null, cacheStatus?: string}
     */
    public function year(int $year): array {
        // 1. Versuche flz_organization (neue kanonische Quelle)
        $flzOrgResult = $this->tryFlzOrganization($year);
        if ($flzOrgResult['status'] === 'AVAILABLE') {
            return $flzOrgResult;
        }

        // 2. Fallback zu localbase (historische Kompatibilität)
        return $this->tryLocalBase($year);
    }

    /**
     * Versucht, Feiertage von flz_organization zu holen.
     * 
     * @param int $year Das Jahr
     * @return array{status: string, dates: array, timezone: string|null, cacheStatus?: string}
     */
    private function tryFlzOrganization(int $year): array {
        if (!in_array('flz_organization', $this->apps->getEnabledApps(), true)) {
            return ['status' => 'MISSING', 'dates' => [], 'timezone' => null];
        }

        if (!class_exists(self::FLZ_ORGANIZATION_CLASS)) {
            return ['status' => 'INCOMPATIBLE', 'dates' => [], 'timezone' => null];
        }

        try {
            $provider = Server::get(self::FLZ_ORGANIZATION_CLASS);
            $snapshot = $provider->calendar($year);
            
            if ($snapshot->contractVersion() !== '1.0' || !$snapshot->isUsable()) {
                return ['status' => 'UNAVAILABLE', 'dates' => [], 'timezone' => $snapshot->timezone()];
            }

            return [
                'status' => 'AVAILABLE',
                'dates' => $snapshot->publicHolidayDates(),
                'timezone' => $snapshot->timezone(),
                'cacheStatus' => $snapshot->cacheStatus(),
                'source' => 'flz_organization'
            ];
        } catch (\Throwable $e) {
            return ['status' => 'UNAVAILABLE', 'dates' => [], 'timezone' => null];
        }
    }

    /**
     * Versucht, Feiertage von localbase zu holen (Fallback).
     * 
     * @param int $year Das Jahr
     * @return array{status: string, dates: array, timezone: string|null, cacheStatus?: string}
     */
    private function tryLocalBase(int $year): array {
        if (!in_array('localbase', $this->apps->getEnabledApps(), true)) {
            return ['status' => 'MISSING', 'dates' => [], 'timezone' => null];
        }

        if (!class_exists(self::LOCALBASE_CLASS)) {
            return ['status' => 'INCOMPATIBLE', 'dates' => [], 'timezone' => null];
        }

        try {
            $provider = Server::get(self::LOCALBASE_CLASS);
            $snapshot = $provider->calendar($year);
            
            if ($snapshot->contractVersion() !== '1.0' || !$snapshot->isUsable()) {
                return ['status' => 'UNAVAILABLE', 'dates' => [], 'timezone' => $snapshot->timezone()];
            }

            return [
                'status' => 'AVAILABLE',
                'dates' => $snapshot->publicHolidayDates(),
                'timezone' => $snapshot->timezone(),
                'cacheStatus' => $snapshot->cacheStatus(),
                'source' => 'localbase'
            ];
        } catch (\Throwable $e) {
            return ['status' => 'UNAVAILABLE', 'dates' => [], 'timezone' => null];
        }
    }
}
