#!/usr/bin/env bash
set -euo pipefail

if [[ ! -f .ddev/config.yaml || ${PWD##*/} != nextcloud-dev ]]; then
    printf '%s\n' 'Run this read-only probe from the documented nextcloud-dev root.' >&2
    exit 2
fi

ddev exec -d /var/www/html/html php <<'PHP'
<?php
declare(strict_types=1);
define('OC_CONSOLE', true);
require '/var/www/html/html/lib/base.php';
set_exception_handler(static function (Throwable $error): void
{
    fwrite(STDERR, 'Akut provider probe failed: ' . $error::class . PHP_EOL);
    exit(1);
});
$gateway = OCP\Server::get(OCA\FlzAkut\Service\OrganizationGateway::class);
$calendar = OCP\Server::get(OCA\FlzAkut\Service\HolidayGateway::class);
$snapshot = $gateway->snapshot();
if (!in_array($snapshot['status'], ['AVAILABLE', 'MISSING', 'INVALID', 'INCOMPATIBLE', 'UNAVAILABLE'], true))
{
    throw new RuntimeException('Unknown provider state.');
}
if ($snapshot['status'] === 'AVAILABLE' && ($snapshot['source'] ?? null) !== 'flz_organization')
{
    throw new RuntimeException('Unexpected organization source.');
}
if ($snapshot['status'] !== 'AVAILABLE' && ($snapshot['roles'] !== [] || $snapshot['areas'] !== []))
{
    throw new RuntimeException('Unavailable provider exposed mappings.');
}
echo json_encode([
    'organizationStatus' => $snapshot['status'],
    'holidayGatewayResolved' => $calendar instanceof OCA\FlzAkut\Service\HolidayGateway,
], JSON_THROW_ON_ERROR), PHP_EOL;
PHP
