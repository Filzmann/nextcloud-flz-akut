<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\App\IAppManager;
use OCP\Server;

/**
 * Aktivierungs- und versionsgeprüfter Consumer der öffentlichen Organisations-V1-Verträge.
 * 
 * Priorität:
 * 1. flz_organization (neue kanonische Quelle für FLZ-Organisationsverwaltung)
 * 2. localbase (historischer Fallback für Kompatibilität)
 * 
 * Fehlende, deaktivierte oder inkompatible Provider bleiben sichtbar und erteilen
 * keine Rechte. UI-Sichtbarkeit erteilt keine Rechte.
 */
class OrganizationGateway {
    private const FLZ_ORGANIZATION_CLASS = 'OCA\\FlzOrganization\\PublicApi\\V1\\OrganizationSnapshotService';
    private const LOCALBASE_CLASS = 'OCA\\LocalBase\\PublicApi\\V1\\OrganizationSnapshotService';

    public function __construct(private IAppManager $apps) {}

    /**
     * Liefert den Organisations-Snapshot von flz_organization oder localbase.
     * 
     * @return array{status: string, roles: array, areas: array}
     */
    public function snapshot(): array {
        // 1. Versuche flz_organization (neue kanonische Quelle)
        $flzOrgResult = $this->tryFlzOrganization();
        if ($flzOrgResult['status'] === 'AVAILABLE') {
            return $flzOrgResult;
        }

        // 2. Fallback zu localbase (historische Kompatibilität)
        return $this->tryLocalBase();
    }

    /**
     * Versucht, den Snapshot von flz_organization zu holen.
     * 
     * @return array{status: string, roles: array, areas: array}
     */
    private function tryFlzOrganization(): array {
        if (!in_array('flz_organization', $this->apps->getEnabledApps(), true)) {
            return ['status' => 'MISSING', 'roles' => [], 'areas' => []];
        }

        if (!class_exists(self::FLZ_ORGANIZATION_CLASS)) {
            return ['status' => 'INCOMPATIBLE', 'roles' => [], 'areas' => []];
        }

        try {
            $snapshotService = Server::get(self::FLZ_ORGANIZATION_CLASS);
            $snapshot = $snapshotService->snapshot();
            
            // Prüfe Vertragsversion und Validität
            if ($snapshot->contractVersion() !== '1.0' || !$snapshot->isValid()) {
                return ['status' => 'INCOMPATIBLE', 'roles' => [], 'areas' => []];
            }

            return [
                'status' => 'AVAILABLE',
                'roles' => $snapshot->roles(),
                'areas' => $snapshot->areas(),
                'source' => 'flz_organization'
            ];
        } catch (\Throwable $e) {
            return ['status' => 'UNAVAILABLE', 'roles' => [], 'areas' => []];
        }
    }

    /**
     * Versucht, den Snapshot von localbase zu holen (Fallback).
     * 
     * @return array{status: string, roles: array, areas: array}
     */
    private function tryLocalBase(): array {
        if (!in_array('localbase', $this->apps->getEnabledApps(), true)) {
            return ['status' => 'MISSING', 'roles' => [], 'areas' => []];
        }

        if (!class_exists(self::LOCALBASE_CLASS)) {
            return ['status' => 'INCOMPATIBLE', 'roles' => [], 'areas' => []];
        }

        try {
            $snapshotService = Server::get(self::LOCALBASE_CLASS);
            $snapshot = $snapshotService->snapshot();
            
            if ($snapshot->contractVersion() !== '1.0' || !$snapshot->isValid()) {
                return ['status' => 'INCOMPATIBLE', 'roles' => [], 'areas' => []];
            }

            return [
                'status' => 'AVAILABLE',
                'roles' => $snapshot->roles(),
                'areas' => $snapshot->areas(),
                'source' => 'localbase'
            ];
        } catch (\Throwable $e) {
            return ['status' => 'UNAVAILABLE', 'roles' => [], 'areas' => []];
        }
    }
}
