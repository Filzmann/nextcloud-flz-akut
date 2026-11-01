<?php

declare(strict_types=1);

use OCA\FlzAkut\Service\AccessPolicy;

require_once dirname(__DIR__) . '/bootstrap.php';

$roles=['office'=>['groupId'=>'flz-Buero','label'=>'Büro'],'eb'=>['groupId'=>'flz-EB','label'=>'EB']];
$areas=['west'=>['groupId'=>'flz-Bereich-West','label'=>'West'],'south'=>['groupId'=>'flz-Bereich-Sued','label'=>'Süd']];
$policy=new AccessPolicy();
if($policy->bureauKeys(['flz-Buero','flz-Bereich-West'],['flz-Buero'],$areas)!==['west'])throw new RuntimeException('BO muss mit der konkreten Berechtigung nur sein Büro sehen.');
if($policy->bureauKeys(['flz-EB','flz-Bereich-West','flz-Bereich-Sued'],['flz-EB'],$areas)!==['south','west'])throw new RuntimeException('EB mit Berechtigung und Mehrbüro-Zuordnung muss beide Büros sehen.');
if($policy->bureauKeys(['flz-Bereich-West'],['flz-Buero','flz-EB'],$areas)!==[])throw new RuntimeException('Bereichsmitgliedschaft ohne berechtigte Rolle darf keinen Zugriff erteilen.');
if($policy->bureauKeys(['flz-Buero','flz-Bereich-West'],['flz-BL'],$areas)!==[])throw new RuntimeException('Eine Organigrammrolle ohne konkrete Berechtigung darf keinen Zugriff erteilen.');

echo "flz_akut access policy tests passed\n";
