<?php

declare(strict_types=1);

namespace OCP {
    interface IAppConfig {
        public function getValueString(string $appId, string $key, string $default): string;
        public function setValueString(string $appId, string $key, string $value): void;
    }
    interface IGroupManager {
        public function get(string $gid): ?object;
        public function getUserGroupIds(object $user): array;
    }
    interface IUserSession { public function getUser(): ?object; }
}

namespace {
    use OCA\FlzAkut\Service\AccessPolicy;
    use OCA\FlzAkut\Service\AccessService;
    use OCA\FlzAkut\Service\AkutCapability;
    use OCA\FlzAkut\Service\OrganizationGateway;
    use OCA\FlzAkut\Service\PermissionSettingsService;

    require_once dirname(__DIR__) . '/bootstrap.php';

    $snapshot = [
        'status' => 'AVAILABLE',
        'roles' => [
            'office' => ['groupId' => 'Buero', 'label' => 'Büroorganisation'],
            'eb' => ['groupId' => 'EB', 'label' => 'Einsatzbegleitung'],
            'bl' => ['groupId' => 'BL', 'label' => 'Büroleitung'],
            'deputy_bl' => ['groupId' => 'StvBL', 'label' => 'Stellvertretende Büroleitung'],
        ],
        'areas' => ['nord' => ['groupId' => 'Bereich-Nordost', 'label' => 'Nord']],
    ];
    $organization = new class($snapshot) extends OrganizationGateway {
        public function __construct(private array $value) {}
        public function snapshot(): array { return $this->value; }
    };
    $config = new class implements \OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default): string { return $this->values[$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$key] = $value; }
    };
    $groups = new class implements \OCP\IGroupManager {
        public array $actorGroups = ['Buero', 'Bereich-Nordost'];
        public array $existing = ['Buero', 'EB', 'BL', 'StvBL', 'Bereich-Nordost'];
        public function get(string $gid): ?object { return in_array($gid, $this->existing, true) ? (object)['gid' => $gid] : null; }
        public function getUserGroupIds(object $user): array { return $this->actorGroups; }
    };
    $session = new class implements \OCP\IUserSession {
        public function getUser(): ?object { return new class { public function getUID(): string { return 'actor'; } }; }
    };

    $settings = new PermissionSettingsService($config, $organization, $groups);
    foreach (AkutCapability::actionCapabilities() as $capability) {
        if ($settings->roleKeys($capability) !== ['office', 'eb', 'bl', 'deputy_bl']) {
            throw new RuntimeException("{$capability} besitzt nicht die vier bestätigten Defaultrollen.");
        }
    }
    if (in_array('as', PermissionSettingsService::ASSIGNABLE_ROLE_KEYS, true)) {
        throw new RuntimeException('AS darf keine ausführende Akutrolle sein.');
    }

    $access = new AccessService($session, $groups, $organization, new AccessPolicy(), $settings);
    if ($access->bureauKeys(AkutCapability::READ) !== ['nord']) {
        throw new RuntimeException('BO mit Leseberechtigung muss sein Büro lesen können.');
    }

    $settings->update(array_fill_keys(AkutCapability::actionCapabilities(), ['bl']));
    if ($access->bureauKeys(AkutCapability::CREATE) !== []) {
        throw new RuntimeException('BO ohne konkrete Anlegeberechtigung wurde nicht abgewiesen.');
    }
    try {
        $access->assertBureau(AkutCapability::CREATE, 'nord');
        throw new RuntimeException('Abgewiesene Aktion wurde zugelassen.');
    } catch (DomainException) {}

    $groups->actorGroups = ['BL', 'Bereich-Nordost'];
    if ($access->bureauKeys(AkutCapability::CREATE) !== ['nord']) {
        throw new RuntimeException('BL mit konkreter Anlegeberechtigung muss sein Büro bearbeiten können.');
    }

    $groups->existing = ['Buero', 'EB', 'StvBL', 'Bereich-Nordost'];
    if ($access->bureauKeys(AkutCapability::CREATE) !== []) {
        throw new RuntimeException('Eine fehlende konfigurierte Rollengruppe muss fail-closed bleiben.');
    }

    $groups->existing[] = 'BL';
    $before = $config->values;
    try {
        $settings->update(array_fill_keys(AkutCapability::actionCapabilities(), ['as']));
        throw new RuntimeException('AS wurde als ausführende Akutrolle akzeptiert.');
    } catch (DomainException) {}
    if ($config->values !== $before) {
        throw new RuntimeException('Eine ungültige Rechtezuordnung darf keine Teiländerung speichern.');
    }

    echo "flz_akut capability access tests passed\n";
}
