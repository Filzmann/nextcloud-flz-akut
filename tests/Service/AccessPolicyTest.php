<?php

declare(strict_types=1);

use OCA\FlzAkut\Service\AccessPolicy;

require_once dirname(__DIR__) . '/bootstrap.php';

$roles=['office'=>['groupId'=>'flz-Buero','label'=>'Büro'],'eb'=>['groupId'=>'flz-EB','label'=>'EB']];
$areas=['west'=>['groupId'=>'flz-Bereich-West','label'=>'West'],'south'=>['groupId'=>'flz-Bereich-Sued','label'=>'Süd']];
$policy=new AccessPolicy();
if($policy->bureauKeys(['flz-Buero','flz-Bereich-West'],$roles,$areas)!==['west'])throw new RuntimeException('BO muss nur sein Büro sehen.');
if($policy->bureauKeys(['flz-EB','flz-Bereich-West','flz-Bereich-Sued'],$roles,$areas)!==['south','west'])throw new RuntimeException('EB mit Mehrbüro-Zuordnung muss beide Büros sehen.');
if($policy->bureauKeys(['flz-Bereich-West'],$roles,$areas)!==[])throw new RuntimeException('Bereichsmitgliedschaft ohne BO/EB-Rolle darf keinen Zugriff erteilen.');

echo "flz_akut access policy tests passed\n";
