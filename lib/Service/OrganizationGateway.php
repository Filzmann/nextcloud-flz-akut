<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCA\FlzOrganization\PublicApi\V1\Contract;
use OCA\FlzOrganization\PublicApi\V1\OrganizationSnapshotService;
use OCP\App\IAppManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use Throwable;

/** Reads only the canonical, activation- and version-checked organization. */
class OrganizationGateway
{
    public function __construct(
        private IAppManager $apps,
        private LoggerInterface $logger,
    )
    {
    }

    /** @return array{status:string,roles:array,areas:array,source?:string} */
    public function snapshot(): array
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
                || !class_exists(OrganizationSnapshotService::class))
            {
                return $this->unavailable('INCOMPATIBLE');
            }
            $service = Server::get(OrganizationSnapshotService::class);
            if (!$service instanceof OrganizationSnapshotService)
            {
                return $this->unavailable('INCOMPATIBLE');
            }
            $snapshot = $service->snapshot();
            foreach (['contractVersion', 'isValid', 'roles', 'areas'] as $method)
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
            if ($snapshot->isValid() !== true)
            {
                return $this->unavailable('INVALID');
            }
            $roles = $snapshot->roles();
            $areas = $snapshot->areas();
            if (!is_array($roles) || !is_array($areas))
            {
                return $this->unavailable('INCOMPATIBLE');
            }
            return [
                'status' => 'AVAILABLE',
                'roles' => $roles,
                'areas' => $areas,
                'source' => 'flz_organization',
            ];
        }
        catch (Throwable $error)
        {
            $this->logger->warning('Akut organization provider unavailable', [
                'app' => 'flz_akut',
                'provider' => 'flz_organization',
                'exception_class' => $error::class,
            ]);
            return $this->unavailable('UNAVAILABLE');
        }
    }

    /** @return array{status:string,roles:array,areas:array} */
    private function unavailable(string $status): array
    {
        return [
            'status' => $status,
            'roles' => [],
            'areas' => [],
        ];
    }
}
