<?php

declare(strict_types=1);

namespace OCPApp {
    interface IAppManager {}
}

namespace OCP {
    interface IAppConfig {
        public function getValueInt(string $appId, string $key, int $default): int;
        public function setValueInt(string $appId, string $key, int $value): void;
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
    use OCA\FlzAkut\Permission\AkutPermissionProvider;
    use OCA\FlzAkut\Privacy\AkutPersonalDataProvider;
    use OCA\FlzAkut\Privacy\AkutPrivacySource;
    use OCA\FlzAkut\Privacy\AkutProcessingMetadataProvider;
    use OCA\FlzAkut\Privacy\AkutRetentionProvider;
    use OCA\FlzAkut\Service\OrganizationGateway;
    use OCA\FlzAkut\Service\PermissionSettingsService;
    use OCA\FlzAkut\Service\RetentionSettingsService;
    use OCA\FlzAkut\Tests\Support\InMemoryShiftStore;
    use OCA\FlzDataProtection\PublicApi\V1\DataSubjectRef;
    use OCA\FlzDataProtection\PublicApi\V1\PersonalDataRequest;
    use OCA\FlzDataProtection\PublicApi\V1\RetentionPreviewRequest;
    use OCA\FlzDataProtection\PublicApi\V1\Testing\PersonalDataProviderContractTestKit;

    require_once dirname(__DIR__) . '/bootstrap.php';

    $source = new class implements AkutPrivacySource {
        public function forSubject(string $uid, int $limit): array {
            if ($uid !== 'assistant-1') throw new RuntimeException('Provider queried a foreign subject.');
            return [[
                'kind' => 'shift', 'id' => 7, 'team_code' => 'TEAM-A',
                'starts_at' => '2026-10-28T08:00:00+01:00',
                'ends_at' => '2026-10-28T16:00:00+01:00', 'state' => 'assigned',
            ], [
                'kind' => 'user-config', 'id' => 0, 'key' => 'as_info', 'value' => '{"tags":["nacht"],"note":"nur nachts"}',
            ]];
        }
    };
    $personal = new AkutPersonalDataProvider($source);
    $request = new PersonalDataRequest(new DataSubjectRef('nextcloud-user', 'assistant-1'), 'de', 'Auskunft', 10, []);
    $page = PersonalDataProviderContractTestKit::verifyScenario($personal, $request);
    if ($page->status() !== 'complete' || count($page->entries()) !== 2 || $page->entries()[0]->reference() !== 'shift:7' || $page->entries()[1]->reference() !== 'user-config:as_info') {
        throw new RuntimeException('PersonalDataProvider verletzt den öffentlichen V1-Vertrag.');
    }
    $unsupported = $personal->collect(new PersonalDataRequest(new DataSubjectRef('external-person', 'x'), 'de', 'Auskunft', 10, []));
    if ($unsupported->status() !== 'not_applicable') throw new RuntimeException('Nicht unterstützte Subjects müssen datenlos bleiben.');

    $metadata = new AkutProcessingMetadataProvider();
    if ($metadata->descriptor()->contractVersion() !== '1.0' || $metadata->catalog()->appId() !== 'flz_akut') {
        throw new RuntimeException('ProcessingMetadataProvider verletzt den öffentlichen V1-Vertrag.');
    }

    $availableGateway = new class extends OrganizationGateway {
        public function __construct() {}
        public function snapshot(): array {
            return [
                'status' => 'AVAILABLE',
                'roles' => [
                    'office' => ['groupId' => 'Buero', 'label' => 'Büroorganisation'],
                    'eb' => ['groupId' => 'EB', 'label' => 'Einsatzbegleitung'],
                    'bl' => ['groupId' => 'BL', 'label' => 'Büroleitung'],
                    'deputy_bl' => ['groupId' => 'StvBL', 'label' => 'Stellvertretende Büroleitung'],
                ],
                'areas' => ['nord' => ['groupId' => 'Bereich-Nordost', 'label' => 'Nord']],
            ];
        }
    };
    $config = new class implements \OCP\IAppConfig {
        public array $values = [];
        public function getValueInt(string $appId, string $key, int $default): int { return 180; }
        public function setValueInt(string $appId, string $key, int $value): void {}
        public function getValueString(string $appId, string $key, string $default): string { return $this->values[$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$key] = $value; }
    };
    $groups = new class implements \OCP\IGroupManager {
        public function get(string $gid): ?object { return in_array($gid, ['Buero', 'EB', 'BL', 'StvBL'], true) ? (object)['gid' => $gid] : null; }
        public function getUserGroupIds(object $user): array { return []; }
    };
    $permissionSettings = new PermissionSettingsService($config, $availableGateway, $groups);
    $permissions = (new AkutPermissionProvider($availableGateway, $permissionSettings))->collect();
    if (!$permissions->complete() || count($permissions->rules()) !== 10) throw new RuntimeException('Verfügbare Organisation muss acht getrennte Büroregeln und zwei Einstellungsregeln liefern.');
    foreach (array_slice($permissions->rules(), 0, 8) as $rule) {
        if ($rule->condition()->operator() !== 'all' || count($rule->condition()->children()) !== 2) {
            throw new RuntimeException('Bürorechte müssen Rolle und Bürobereich gemeinsam verlangen.');
        }
        if ($rule->condition()->children()[0]->operator() !== 'any' || count($rule->condition()->children()[0]->children()) !== 4) {
            throw new RuntimeException('Jedes Bürorecht muss die vier bestätigten Defaultrollen alternativ auswerten.');
        }
    }
    $missingGateway = new class extends OrganizationGateway {
        public function __construct() {}
        public function snapshot(): array { return ['status' => 'MISSING', 'roles' => [], 'areas' => []]; }
    };
    $deniedSettings = new PermissionSettingsService($config, $missingGateway, $groups);
    $denied = (new AkutPermissionProvider($missingGateway, $deniedSettings))->collect();
    if ($denied->complete() || $denied->rules() !== []) throw new RuntimeException('Fehlende Organisation darf keine Rechte behaupten.');

    $session = new class implements \OCP\IUserSession { public function getUser(): ?object { return null; } };
    $retention = new AkutRetentionProvider(new InMemoryShiftStore(), new RetentionSettingsService($config, $groups, $session));
    if ($retention->policies()[0]->durationDays() !== 180) throw new RuntimeException('RetentionProvider muss die App-Einstellung projizieren.');
    $preview = $retention->preview(new RetentionPreviewRequest('acute_shift_case_retention', '2026-10-27T12:00:00+01:00', 10));
    if ($preview->status() !== 'not_applicable' || $preview->candidates() !== []) throw new RuntimeException('Leere Retention-Vorschau darf keine Kandidaten erfinden.');
    try {
        $retention->preview(new RetentionPreviewRequest('foreign_policy', '2026-10-27T12:00:00+01:00', 10));
        throw new RuntimeException('Fremde Retention-Policy wurde akzeptiert.');
    } catch (InvalidArgumentException) {}

    echo "flz_akut provider contract tests passed\n";
}
