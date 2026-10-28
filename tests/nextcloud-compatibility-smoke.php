<?php

declare(strict_types=1);

return [
    'providerRegistrations' => [
        'flz_data_protection' => [
            OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent::class,
            OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent::class,
            OCA\FlzDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent::class,
        ],
        'flz_permission_matrix' => [
            OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent::class,
        ],
    ],
    'uiPath' => '/index.php/apps/flz_akut/',
    'preGrantUiStatuses' => [200],
    'postGrantUiStatuses' => [200],
    // Native Nextcloud administration intentionally grants no domain access.
    'grantService' => null,
    'permissionProbe' => null,
    'apiSmokes' => [
        ['/index.php/apps/flz_akut/api/state', [200, 403, 503]],
    ],
];
