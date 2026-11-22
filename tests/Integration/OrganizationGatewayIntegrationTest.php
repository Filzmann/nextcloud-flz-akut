<?php

declare(strict_types=1);

namespace OCP\App
{
    interface IAppManager
    {
        public function getEnabledApps(): array;
    }
}

namespace OCP
{
    final class Server
    {
        public static array $services = [];
        public static array $requested = [];

        public static function get(string $class): object
        {
            self::$requested[] = $class;
            return self::$services[$class] ?? throw new \RuntimeException('Synthetic unresolved service.');
        }
    }
}

namespace Psr\Log
{
    interface LoggerInterface
    {
        public function warning(string $message, array $context = []): void;
    }
}

namespace OCA\FlzOrganization\PublicApi\V1
{
    final class Contract
    {
        public static bool $supported = true;

        public static function supports(string $version): bool
        {
            return self::$supported && $version === '1.0';
        }
    }

    final class OrganizationSnapshotService
    {
        public function __construct(private object $value)
        {
        }
        public function snapshot(): object
        {
            if ($this->value instanceof \Throwable)
            {
                throw $this->value;
            }
            return $this->value;
        }
    }

    final class HolidayCalendarProvider
    {
        public function __construct(private object $value)
        {
        }
        public function calendar(int $year): object
        {
            if ($this->value instanceof \Throwable)
            {
                throw $this->value;
            }
            return $this->value;
        }
    }
}

namespace
{
    use OCA\FlzAkut\Service\HolidayGateway;
    use OCA\FlzAkut\Service\OrganizationGateway;
    use OCA\FlzOrganization\PublicApi\V1\Contract;
    use OCA\FlzOrganization\PublicApi\V1\HolidayCalendarProvider;
    use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService;
    use OCP\App\IAppManager;
    use OCP\Server;
    use Psr\Log\LoggerInterface;

    require_once dirname(__DIR__) . '/bootstrap.php';

    $apps = new class implements IAppManager
    {
        public array $enabled = ['flz_organization', 'localbase'];
        public function getEnabledApps(): array
        {
            return $this->enabled;
        }
    };
    $logger = new class implements LoggerInterface
    {
        public array $warnings = [];
        public function warning(string $message, array $context = []): void
        {
            $this->warnings[] = [$message, $context];
        }
    };
    $organizationSnapshot = static fn(bool $valid = true, string $version = '1.0'): object => new class($valid, $version)
    {
        public function __construct(private bool $valid, private string $version)
        {
        }
        public function contractVersion(): string
        {
            return $this->version;
        }
        public function isValid(): bool
        {
            return $this->valid;
        }
        public function roles(): array
        {
            return ['office' => ['groupId' => 'synthetic-office', 'label' => 'Office']];
        }
        public function areas(): array
        {
            return ['north' => ['groupId' => 'synthetic-north', 'label' => 'North']];
        }
    };
    $holidaySnapshot = static fn(bool $usable = true, string $version = '1.0'): object => new class($usable, $version)
    {
        public function __construct(private bool $usable, private string $version)
        {
        }
        public function contractVersion(): string
        {
            return $this->version;
        }
        public function isUsable(): bool
        {
            return $this->usable;
        }
        public function publicHolidayDates(): array
        {
            return ['2026-12-25'];
        }
        public function timezone(): string
        {
            return 'Europe/Berlin';
        }
        public function cacheStatus(): string
        {
            return $this->usable ? 'stale' : 'unavailable';
        }
    };
    $assert = static function (bool $condition, string $message): void
    {
        if (!$condition)
        {
            throw new RuntimeException($message);
        }
    };

    Server::$services = [
        OrganizationSnapshotService::class => new OrganizationSnapshotService($organizationSnapshot()),
        HolidayCalendarProvider::class => new HolidayCalendarProvider($holidaySnapshot()),
    ];
    $organization = new OrganizationGateway($apps, $logger);
    $holidays = new HolidayGateway($apps, $logger);
    $resolved = $organization->snapshot();
    $assert($resolved['status'] === 'AVAILABLE' && $resolved['roles']['office']['groupId'] === 'synthetic-office', 'Enabled V1 organization must expose the canonical role mapping.');
    $assert($resolved['source'] === 'flz_organization', 'Organization ownership must remain explicit.');
    $calendar = $holidays->year(2026);
    $assert($calendar['status'] === 'AVAILABLE' && $calendar['dates'] === ['2026-12-25'], 'The usable canonical calendar must expose its holiday dates.');
    $assert($calendar['cacheStatus'] === 'stale', 'A stale but usable calendar must retain its freshness warning.');

    $apps->enabled = ['localbase'];
    Server::$requested = [];
    $assert($organization->snapshot() === ['status' => 'MISSING', 'roles' => [], 'areas' => []], 'Disabled canonical organization must remain missing even with LocalBase enabled.');
    $assert($holidays->year(2026) === ['status' => 'MISSING', 'dates' => [], 'timezone' => null], 'Disabled canonical calendar must not fall back to LocalBase.');
    $assert(Server::$requested === [], 'A disabled canonical provider must not resolve any alternative service.');

    $apps->enabled = ['flz_organization', 'localbase'];
    Contract::$supported = false;
    Server::$requested = [];
    $assert($organization->snapshot()['status'] === 'INCOMPATIBLE', 'An incompatible contract handshake must reject organization data.');
    $assert($holidays->year(2026)['status'] === 'INCOMPATIBLE', 'An incompatible contract handshake must reject calendar data.');
    $assert(Server::$requested === [], 'An incompatible handshake must fail before service resolution.');
    Contract::$supported = true;

    Server::$services[OrganizationSnapshotService::class] = new OrganizationSnapshotService($organizationSnapshot(false));
    $assert($organization->snapshot() === ['status' => 'INVALID', 'roles' => [], 'areas' => []], 'Invalid organization snapshots must not expose role or bureau mappings.');
    Server::$services[HolidayCalendarProvider::class] = new HolidayCalendarProvider($holidaySnapshot(false));
    $assert($holidays->year(2026) === ['status' => 'UNAVAILABLE', 'dates' => [], 'timezone' => 'Europe/Berlin'], 'Unavailable canonical holidays must remain unavailable without another source.');

    Server::$services[OrganizationSnapshotService::class] = new OrganizationSnapshotService($organizationSnapshot(version: '2.0'));
    Server::$services[HolidayCalendarProvider::class] = new HolidayCalendarProvider($holidaySnapshot(version: '2.0'));
    $assert($organization->snapshot()['status'] === 'INCOMPATIBLE', 'Unknown organization snapshot versions must fail closed.');
    $assert($holidays->year(2026)['status'] === 'INCOMPATIBLE', 'Unknown calendar snapshot versions must fail closed.');

    Server::$services[OrganizationSnapshotService::class] = new OrganizationSnapshotService(new RuntimeException('synthetic-sensitive-detail'));
    Server::$services[HolidayCalendarProvider::class] = new HolidayCalendarProvider(new RuntimeException('synthetic-sensitive-detail'));
    $assert($organization->snapshot() === ['status' => 'UNAVAILABLE', 'roles' => [], 'areas' => []], 'Organization exceptions must fail closed.');
    $assert($holidays->year(2026) === ['status' => 'UNAVAILABLE', 'dates' => [], 'timezone' => null], 'Calendar exceptions must fail closed.');
    $assert(count($logger->warnings) === 2, 'Both provider failures must be diagnosable.');
    $assert(!str_contains(json_encode($logger->warnings, JSON_THROW_ON_ERROR), 'synthetic-sensitive-detail'), 'Provider diagnostics must not copy exception payloads.');
    foreach (Server::$requested as $class)
    {
        $assert(!str_contains($class, 'LocalBase'), 'No canonical-provider failure may resolve LocalBase.');
    }
    echo "flz_akut canonical organization and holiday gateway tests passed\n";
}
