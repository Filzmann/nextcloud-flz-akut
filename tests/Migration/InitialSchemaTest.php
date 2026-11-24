<?php

declare(strict_types=1);

namespace OCP\DB
{
    interface ISchemaWrapper
    {
        public function hasTable(string $name): bool;
        public function createTable(string $name): object;
    }

    final class Types
    {
        public const BIGINT = 'bigint';
        public const STRING = 'string';
        public const DATETIME_IMMUTABLE = 'datetime_immutable';
        public const TEXT = 'text';
        public const BOOLEAN = 'boolean';
    }
}

namespace OCP\Migration
{
    interface IOutput
    {
    }

    class SimpleMigrationStep
    {
    }
}

namespace
{
    use OCA\FlzAkut\Migration\Version000001Date202610270001;
    use OCP\DB\ISchemaWrapper;
    use OCP\DB\Types;
    use OCP\Migration\IOutput;

    require_once dirname(__DIR__) . '/bootstrap.php';

    final class RecordedTable
    {
        public array $columns = [];
        public array $primaryKey = [];
        public array $indices = [];

        public function addColumn(string $name, string $type, array $options): void
        {
            if (isset($this->columns[$name]))
            {
                throw new RuntimeException('Eine Spalte wurde mehrfach angelegt.');
            }
            $this->columns[$name] = ['type' => $type, 'options' => $options];
        }

        public function setPrimaryKey(array $columns): void
        {
            $this->primaryKey = $columns;
        }

        public function addIndex(array $columns, string $name): void
        {
            $this->indices[$name] = $columns;
        }
    }

    final class RecordedSchema implements ISchemaWrapper
    {
        public array $tables = [];
        public array $created = [];

        public function hasTable(string $name): bool
        {
            return isset($this->tables[$name]);
        }

        public function createTable(string $name): object
        {
            if ($this->hasTable($name))
            {
                throw new RuntimeException('Eine vorhandene Tabelle wurde erneut angelegt.');
            }
            $this->created[] = $name;
            return $this->tables[$name] = new RecordedTable();
        }
    }

    $assert = static function (bool $condition, string $message): void
    {
        if (!$condition)
        {
            throw new RuntimeException($message);
        }
    };
    $output = new class implements IOutput {};
    $migration = new Version000001Date202610270001();
    $schema = new RecordedSchema();

    $assert($migration->changeSchema($output, static fn () => $schema, []) === $schema, 'Die Migration muss das bearbeitete Schema zurückgeben.');
    $assert($schema->created === ['flz_akut_shifts', 'flz_akut_contacts', 'flz_akut_audit'], 'Die frische Installation muss alle drei app-eigenen Tabellen anlegen.');
    foreach ($schema->tables as $table)
    {
        $assert($table->primaryKey === ['id'], 'Jede Fachtabelle braucht einen eindeutigen Primärschlüssel.');
        $assert($table->columns['id'] === ['type' => Types::BIGINT, 'options' => ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]], 'Jede neue Identität muss automatisch und nicht-null vergeben werden.');
    }

    $shifts = $schema->tables['flz_akut_shifts'];
    $assert($shifts->columns['bureau_key']['options']['notnull'] && $shifts->columns['created_by']['options']['notnull'], 'Büro und anlegender Akteur dürfen nicht fehlen.');
    $assert(!$shifts->columns['assigned_uid']['options']['notnull'], 'Eine offene Schicht darf noch keine Assistenzzuweisung verlangen.');
    $assert($shifts->columns['starts_at']['type'] === Types::DATETIME_IMMUTABLE && $shifts->columns['expires_at']['options']['notnull'], 'Schichtzeit und Löschgrenze müssen fachlich typisiert und verpflichtend sein.');
    $assert($shifts->columns['informed_asn']['options']['default'] === false, 'Die Information an ASN darf nicht vorbelegt bestätigt werden.');
    $assert($shifts->indices['flz_akut_retention'] === ['expires_at'] && $shifts->indices['flz_akut_bureau_start'] === ['bureau_key', 'starts_at'], 'Retention und Büroabfrage brauchen die vorgesehenen Indizes.');

    $contacts = $schema->tables['flz_akut_contacts'];
    $assert($contacts->columns['shift_id']['options']['notnull'] && $contacts->columns['actor_uid']['options']['notnull'], 'Kontaktversuche müssen Schicht und Akteur zugeordnet sein.');
    $assert($contacts->columns['note']['options']['length'] === 500 && !$contacts->columns['note']['options']['notnull'], 'Eine Kontaktbemerkung bleibt optional und begrenzt.');
    $assert($contacts->indices['flz_akut_contact_shift_as'] === ['shift_id', 'assistant_uid'], 'Die Kontaktabfrage muss Schicht und Assistenz gemeinsam unterstützen.');
    $assert($schema->tables['flz_akut_audit']->columns['occurred_at']['options']['notnull'], 'Ein Auditeintrag braucht seinen Zeitpunkt.');

    $before = serialize($schema->tables);
    $migration->changeSchema($output, static fn () => $schema, []);
    $assert(serialize($schema->tables) === $before && count($schema->created) === 3, 'Eine Wiederholung darf keine Tabellen oder Datenstrukturen verändern.');

    $partial = new RecordedSchema();
    $existing = new RecordedTable();
    $existing->columns['legacy_marker'] = ['type' => Types::STRING, 'options' => []];
    $partial->tables['flz_akut_shifts'] = $existing;
    $migration->changeSchema($output, static fn () => $partial, []);
    $assert($partial->tables['flz_akut_shifts'] === $existing && array_keys($existing->columns) === ['legacy_marker'], 'Eine bereits vorhandene Tabelle darf durch den Initialschritt nicht überschrieben werden.');
    $assert($partial->created === ['flz_akut_contacts', 'flz_akut_audit'], 'Bei teilweisem Schema dürfen ausschließlich fehlende Tabellen hinzukommen.');

    echo "flz_akut initial schema characterization passed\n";
}
