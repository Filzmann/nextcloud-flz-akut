<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCA\FlzOrganization\PublicApi\V1\Contract;
use OCA\FlzOrganization\PublicApi\V1\HolidayCalendarProvider;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use Throwable;

/** Keeps unavailable canonical holiday data visibly unavailable. */
final class HolidayGateway
{
    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger,
    )
    {
    }

    /** @return array{status:string,dates:array,timezone:?string,cacheStatus?:string,source?:string} */
    public function year(int $year): array
    {
        if (!in_array('flz_organization', $this->apps->getEnabledApps(), true))
        {
            return $this->unavailable('MISSING');
        }

        try
        {
            if (!class_exists(Contract::class)
                || !method_exists(Contract::class, 'supports')
                || !Contract::supports('1.0')
                || !class_exists(HolidayCalendarProvider::class))
            {
                return $this->unavailable('INCOMPATIBLE');
            }
            $provider = Server::get(HolidayCalendarProvider::class);
            if (!$provider instanceof HolidayCalendarProvider)
            {
                return $this->unavailable('INCOMPATIBLE');
            }
            $snapshot = $provider->calendar($year);
            foreach (['contractVersion', 'isUsable', 'timezone', 'publicHolidayDates', 'cacheStatus'] as $method)
            {
                if (!method_exists($snapshot, $method))
                {
                    return $this->unavailable('INCOMPATIBLE');
                }
            }
            if ($snapshot->contractVersion() !== '1.0')
            {
                return $this->unavailable('INCOMPATIBLE');
            }
            $timezone = $snapshot->timezone();
            if (!is_string($timezone) || $timezone === '')
            {
                return $this->unavailable('INCOMPATIBLE');
            }
            if ($snapshot->isUsable() !== true)
            {
                return $this->unavailable('UNAVAILABLE', $timezone);
            }
            $dates = $snapshot->publicHolidayDates();
            $cacheStatus = $snapshot->cacheStatus();
            if (!is_array($dates) || !in_array($cacheStatus, ['fresh', 'current', 'stale'], true))
            {
                return $this->unavailable('INCOMPATIBLE');
            }
            return [
                'status' => 'AVAILABLE',
                'dates' => $dates,
                'timezone' => $timezone,
                'cacheStatus' => $cacheStatus,
                'source' => 'flz_organization',
            ];
        }
        catch (Throwable $error)
        {
            $this->logger->warning('Akut-Feiertags-Provider ist nicht verfügbar.', [
                'app' => 'flz_akut',
                'provider' => 'flz_organization',
                'exception_class' => $error::class,
            ]);
            return $this->unavailable('UNAVAILABLE');
        }
    }

    /** @return array{status:string,dates:array,timezone:?string} */
    private function unavailable(string $status, ?string $timezone = null): array
    {
        return [
            'status' => $status,
            'dates' => [],
            'timezone' => $timezone,
        ];
    }
}
