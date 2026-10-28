<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

final class Version000001Date202610270001 extends SimpleMigrationStep {
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        $schema = $schemaClosure();
        if (!$schema->hasTable('flz_akut_shifts')) {
            $t = $schema->createTable('flz_akut_shifts');
            $t->addColumn('id', Types::BIGINT, ['autoincrement'=>true,'notnull'=>true,'unsigned'=>true]);
            foreach (['team_code'=>64,'bureau_key'=>64,'state'=>16,'assigned_uid'=>255,'source_template_key'=>64,'ma_label'=>255,'hycare_by'=>255,'paper_by'=>255,'created_by'=>255] as $name=>$length) $t->addColumn($name, Types::STRING, ['length'=>$length,'notnull'=>in_array($name,['team_code','bureau_key','state','created_by'],true)]);
            foreach (['starts_at','ends_at','mediated_at','hycare_at','paper_at','expires_at'] as $name) $t->addColumn($name, Types::DATETIME_IMMUTABLE, ['notnull'=>in_array($name,['starts_at','ends_at','expires_at'],true)]);
            $t->addColumn('comment', Types::TEXT, ['notnull'=>true,'default'=>'']);
            $t->addColumn('parent_shift_id', Types::BIGINT, ['notnull'=>false,'unsigned'=>true]);
            $t->addColumn('informed_asn', Types::BOOLEAN, ['notnull'=>true,'default'=>false]);
            $t->setPrimaryKey(['id']); $t->addIndex(['bureau_key','starts_at'],'flz_akut_bureau_start'); $t->addIndex(['expires_at'],'flz_akut_retention'); $t->addIndex(['parent_shift_id'],'flz_akut_parent');
        }
        if (!$schema->hasTable('flz_akut_contacts')) {
            $t=$schema->createTable('flz_akut_contacts'); $t->addColumn('id',Types::BIGINT,['autoincrement'=>true,'notnull'=>true,'unsigned'=>true]); $t->addColumn('shift_id',Types::BIGINT,['notnull'=>true,'unsigned'=>true]);
            foreach(['assistant_uid'=>255,'channel'=>16,'outcome'=>16,'actor_uid'=>255,'reason'=>80] as $name=>$length)$t->addColumn($name,Types::STRING,['length'=>$length,'notnull'=>$name!=='reason']);
            $t->addColumn('contacted_at',Types::DATETIME_IMMUTABLE,['notnull'=>true]); $t->addColumn('note',Types::STRING,['length'=>500,'notnull'=>false]); $t->setPrimaryKey(['id']); $t->addIndex(['shift_id','assistant_uid'],'flz_akut_contact_shift_as');
        }
        if (!$schema->hasTable('flz_akut_audit')) {
            $t=$schema->createTable('flz_akut_audit'); $t->addColumn('id',Types::BIGINT,['autoincrement'=>true,'notnull'=>true,'unsigned'=>true]); $t->addColumn('shift_id',Types::BIGINT,['notnull'=>true,'unsigned'=>true]); $t->addColumn('action',Types::STRING,['length'=>64,'notnull'=>true]); $t->addColumn('actor_uid',Types::STRING,['length'=>255,'notnull'=>true]); $t->addColumn('occurred_at',Types::DATETIME_IMMUTABLE,['notnull'=>true]); $t->setPrimaryKey(['id']); $t->addIndex(['shift_id'],'flz_akut_audit_shift');
        }
        return $schema;
    }
}
