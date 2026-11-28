<?php

declare(strict_types=1);

$appRoot = dirname(__DIR__);
$workspaceRoot = dirname($appRoot);

spl_autoload_register(static function (string $class) use ($appRoot, $workspaceRoot): void {
    $prefixes = [
        'OCA\\FlzAkut\\Tests\\' => $appRoot . '/tests/',
        'OCA\\FlzAkut\\' => $appRoot . '/lib/',
        'OCA\\LocalBase\\Tests\\' => $workspaceRoot . '/localbase/tests/',
        // Contract tests exercise the real optional provider through this test-only boundary.
        'OCA\\FlzOrganization\\' => $workspaceRoot . '/flz_organization/lib/',
        'OCA\\FlzDataProtection\\' => $workspaceRoot . '/flz_data_protection/lib/',
        'OCA\\FlzPermissionMatrix\\' => $workspaceRoot . '/flz_permission_matrix/lib/',
        'OCA\\LocalBase\\' => $workspaceRoot . '/localbase/lib/',
        'OCA\\FlzPlaner\\PublicApi\\V1\\' => $workspaceRoot . '/flzplaner/lib/PublicApi/V1/',
        'OCA\\FlzKapazitaet\\' => $workspaceRoot . '/flz_kapazitaet/lib/',
    ];
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $directory . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
        return;
    }
});
