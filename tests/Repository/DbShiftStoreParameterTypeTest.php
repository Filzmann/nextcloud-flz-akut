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

    echo "flz_akut DB parameter type tests passed\n";
}
