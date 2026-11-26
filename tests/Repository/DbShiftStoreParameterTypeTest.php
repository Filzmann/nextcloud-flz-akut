<?php

declare(strict_types=1);

namespace OCP\DB\QueryBuilder {
    interface IQueryBuilder {
        public const PARAM_INT = 1;
        public const PARAM_BOOL = 'boolean';
        public const PARAM_DATETIME_IMMUTABLE = 'datetime_immutable';
    }
}

namespace {
    use OCA\FlzAkut\Repository\DbShiftStore;

    require_once dirname(__DIR__) . '/bootstrap.php';

    $store = (new ReflectionClass(DbShiftStore::class))->newInstanceWithoutConstructor();
    $dbType = new ReflectionMethod(DbShiftStore::class, 'dbType');

    if ($dbType->invoke($store, 'starts_at') !== 'datetime_immutable') {
        throw new RuntimeException('Nextcloud-Datetime-Parametertypen müssen unverändert an den QueryBuilder weitergegeben werden.');
    }
    if ($dbType->invoke($store, 'parent_shift_id') !== 1) {
        throw new RuntimeException('Ganzzahlige Fremdschlüssel müssen als Integer gebunden werden.');
    }
    if ($dbType->invoke($store, 'informed_asn') !== 'boolean') {
        throw new RuntimeException('Boolesche Falschwerte müssen explizit als Boolean gebunden werden.');
    }
    if ($dbType->invoke($store, 'comment') !== null) {
        throw new RuntimeException('Textfelder dürfen keinen abweichenden DBAL-Parametertyp erzwingen.');
    }

    // Datetime-Binding und -Lesen müssen explizit in UTC konvertieren (Vertrag gegen Zeitzonen-Drift)
    $dbValue = new ReflectionMethod(DbShiftStore::class, 'dbValue');
    $dt = $dbValue->invoke($store, 'starts_at', '2026-11-27T19:00:00+01:00');
    if (!$dt instanceof DateTimeImmutable || $dt->format('P') !== '+00:00' || $dt->format('Y-m-d H:i:s') !== '2026-11-27 18:00:00') {
        throw new RuntimeException('Datetime-Werte müssen explizit nach UTC konvertiert gebunden werden.');
    }
    $normalize = new ReflectionMethod(DbShiftStore::class, 'normalize');
    $row = $normalize->invoke($store, ['id' => '5', 'starts_at' => new DateTimeImmutable('2026-11-27 19:00:00', new DateTimeZone('Europe/Berlin')), 'informed_asn' => 0]);
    if ($row['starts_at'] !== '2026-11-27T18:00:00+00:00' || $row['informed_asn'] !== false) {
        throw new RuntimeException('Beim Lesen müssen Datetime-Werte als UTC ausgeliefert werden.');
    }

    // Der Split-Ursprung (state 'split') darf nicht mehr gelistet werden
    $source = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/Repository/DbShiftStore.php');
    if (!str_contains($source, "neq('state'") || !str_contains($source, "'split'")) {
        throw new RuntimeException('listForBureaux muss den technischen Split-Ursprung (state split) ausfiltern.');
    }
    // vergangene Schichten (beginnt vor Bürotagbeginn heute) dürfen nicht mehr gelistet werden — am Schichtbeginn orientieren, nicht am Ende
    if (!str_contains($source, "gte('starts_at'") || !str_contains($source, "officeDayStartUtc")) {
        throw new RuntimeException('listForBureaux darf nur Schichten listen, die nicht vor Bürotagbeginn heute starten (gte starts_at officeDayStartUtc).');
    }
    // listContactsForShifts: Schicht-IDs als Integer-Array gebunden, kontaktierte Zeit wird als UTC ausgeliefert
    if (!str_contains($source, 'listContactsForShifts') || !str_contains($source, 'PARAM_INT_ARRAY') || !str_contains($source, "'flz_akut_contacts'")) {
        throw new RuntimeException('listContactsForShifts muss Kontakte anhand gebundener Integer-Schicht-IDs aus flz_akut_contacts lesen.');
    }

    echo "flz_akut DB parameter type tests passed\n";
}
