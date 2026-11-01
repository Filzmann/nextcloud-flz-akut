<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use DomainException;
use JsonException;
use OCP\IAppConfig;
use OCP\IGroupManager;

final class PermissionSettingsService {
    private const APP_ID = 'flz_akut';
    public const ASSIGNABLE_ROLE_KEYS = ['office', 'eb', 'bl', 'deputy_bl'];
    public const DEFAULT_ROLE_KEYS = ['office', 'eb', 'bl', 'deputy_bl'];

    private const CONFIG_PREFIX = 'permission_roles_';

    public function __construct(
        private IAppConfig $config,
        private OrganizationGateway $organization,
        private IGroupManager $groups,
    ) {}

    /** @return array<string,list<string>> */
    public function current(): array {
        $result = [];
        foreach (AkutCapability::actionCapabilities() as $capability) {
            $result[$capability] = $this->roleKeys($capability);
        }
        return $result;
    }

    /** @return list<string> */
    public function roleKeys(string $capability): array {
        if (!isset(AkutCapability::DEFINITIONS[$capability])) return [];
        return self::decode($this->config->getValueString(
            self::APP_ID,
            self::CONFIG_PREFIX . $capability,
            self::encode(self::DEFAULT_ROLE_KEYS),
        )) ?? [];
    }

    /** @return list<string> */
    public function effectiveRoleGroups(string $capability): array {
        if (!isset(AkutCapability::DEFINITIONS[$capability])) return [];
        $stored = $this->config->getValueString(
            self::APP_ID,
            self::CONFIG_PREFIX . $capability,
            self::encode(self::DEFAULT_ROLE_KEYS),
        );
        $roleKeys = self::decode($stored);
        if ($roleKeys === null) return [];
        $selection = $this->selection();
        if (!$selection['available']) return [];
        $byKey = [];
        foreach ($selection['options'] as $option) $byKey[$option['roleKey']] = $option;
        $groupIds = [];
        foreach ($roleKeys as $roleKey) {
            $option = $byKey[$roleKey] ?? null;
            if ($option === null || !$option['exists']) return [];
            $groupIds[$option['groupId']] = true;
        }
        return array_keys($groupIds);
    }

    public function capabilityComplete(string $capability): bool {
        if (!isset(AkutCapability::DEFINITIONS[$capability])) return false;
        $stored = $this->config->getValueString(
            self::APP_ID,
            self::CONFIG_PREFIX . $capability,
            self::encode(self::DEFAULT_ROLE_KEYS),
        );
        $roleKeys = self::decode($stored);
        if ($roleKeys === null) return false;
        if ($roleKeys === []) return $this->selection()['available'];
        return count($this->effectiveRoleGroups($capability)) > 0;
    }

    /** @return array{available:bool,complete:bool,message:string,options:list<array{roleKey:string,label:string,groupId:string,exists:bool}>} */
    public function selection(): array {
        $snapshot = $this->organization->snapshot();
        if (($snapshot['status'] ?? null) !== 'AVAILABLE' || !is_array($snapshot['roles'] ?? null)) {
            return ['available' => false, 'complete' => false, 'message' => 'Das LocalBase-Organigramm ist nicht verfügbar. Akutberechtigungen bleiben deaktiviert.', 'options' => []];
        }
        $options = [];
        foreach (self::ASSIGNABLE_ROLE_KEYS as $roleKey) {
            $role = $snapshot['roles'][$roleKey] ?? null;
            $groupId = is_array($role) ? trim((string)($role['groupId'] ?? '')) : '';
            $label = is_array($role) ? trim((string)($role['label'] ?? '')) : '';
            if ($groupId === '' || $label === '') {
                return ['available' => false, 'complete' => false, 'message' => 'Das Organigramm enthält nicht alle erforderlichen Akutrollen. Akutberechtigungen bleiben deaktiviert.', 'options' => []];
            }
            $options[] = ['roleKey' => $roleKey, 'label' => $label, 'groupId' => $groupId, 'exists' => $this->groups->get($groupId) !== null];
        }
        $complete = true;
        foreach (AkutCapability::actionCapabilities() as $capability) {
            $stored = $this->config->getValueString(self::APP_ID, self::CONFIG_PREFIX . $capability, self::encode(self::DEFAULT_ROLE_KEYS));
            $roleKeys = self::decode($stored);
            if ($roleKeys === null) { $complete = false; break; }
            foreach ($roleKeys as $roleKey) {
                foreach ($options as $option) {
                    if ($option['roleKey'] === $roleKey && $option['exists']) continue 2;
                }
                $complete = false;
                break 2;
            }
        }
        return [
            'available' => true,
            'complete' => $complete,
            'message' => $complete ? '' : 'Mindestens eine Rechtezuordnung ist ungültig oder ihre Nextcloud-Gruppe fehlt. Die betroffene Berechtigung bleibt deaktiviert.',
            'options' => $options,
        ];
    }

    /** @param array<string,mixed> $permissions @return array<string,list<string>> */
    public function update(array $permissions): array {
        $expected = AkutCapability::actionCapabilities();
        if (array_diff(array_keys($permissions), $expected) !== [] || array_diff($expected, array_keys($permissions)) !== []) {
            throw new DomainException('Die Akut-Rechtezuordnung ist unvollständig oder enthält unbekannte Berechtigungen.');
        }
        $selection = $this->selection();
        if (!$selection['available']) throw new DomainException($selection['message']);
        $selectable = [];
        foreach ($selection['options'] as $option) if ($option['exists']) $selectable[$option['roleKey']] = true;

        $validated = [];
        foreach ($expected as $capability) {
            $roles = $permissions[$capability];
            if (!is_array($roles) || !array_is_list($roles)) throw new DomainException('Berechtigungen müssen als Rollenliste übermittelt werden.');
            $unique = [];
            foreach ($roles as $roleKey) {
                if (!is_string($roleKey) || !isset($selectable[$roleKey])) {
                    throw new DomainException('Eine Akutberechtigung enthält eine unbekannte oder nicht verfügbare Organigrammrolle.');
                }
                $unique[$roleKey] = true;
            }
            $validated[$capability] = array_values(array_filter(self::ASSIGNABLE_ROLE_KEYS, static fn(string $roleKey): bool => isset($unique[$roleKey])));
        }
        foreach ($validated as $capability => $roleKeys) {
            $this->config->setValueString(self::APP_ID, self::CONFIG_PREFIX . $capability, self::encode($roleKeys));
        }
        return $this->current();
    }

    /** @return list<string>|null */
    private static function decode(string $value): ?array {
        try { $decoded = json_decode($value, true, 8, JSON_THROW_ON_ERROR); }
        catch (JsonException) { return null; }
        if (!is_array($decoded) || !array_is_list($decoded)) return null;
        $unique = [];
        foreach ($decoded as $roleKey) {
            if (!is_string($roleKey) || !in_array($roleKey, self::ASSIGNABLE_ROLE_KEYS, true)) return null;
            $unique[$roleKey] = true;
        }
        return array_values(array_filter(self::ASSIGNABLE_ROLE_KEYS, static fn(string $roleKey): bool => isset($unique[$roleKey])));
    }

    /** @param list<string> $roleKeys */
    private static function encode(array $roleKeys): string {
        return json_encode(array_values($roleKeys), JSON_THROW_ON_ERROR);
    }
}
