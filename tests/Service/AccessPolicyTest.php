<?php

declare(strict_types=1);

use OCA\FlzAkut\Service\AccessPolicy;

require_once dirname(__DIR__) . '/bootstrap.php';

$roles=['office'=>['groupId'=>'Buero','label'=>'Büro'],'eb'=>['groupId'=>'EB','label'=>'EB']];
$areas=['west'=>['groupId'=>'Bereich-West','label'=>'West'],'south'=>['groupId'=>'Bereich-Sued','label'=>'Süd']];
$policy=new AccessPolicy();
if($policy->bureauKeys(['Buero','Bereich-West'],['Buero'],$areas)!==['west'])throw new RuntimeException('BO muss mit der konkreten Berechtigung nur sein Büro sehen.');
if($policy->bureauKeys(['EB','Bereich-West','Bereich-Sued'],['EB'],$areas)!==['south','west'])throw new RuntimeException('EB mit Berechtigung und Mehrbüro-Zuordnung muss beide Büros sehen.');
if($policy->bureauKeys(['Bereich-West'],['Buero','EB'],$areas)!==[])throw new RuntimeException('Bereichsmitgliedschaft ohne berechtigte Rolle darf keinen Zugriff erteilen.');
if($policy->bureauKeys(['Buero','Bereich-West'],['BL'],$areas)!==[])throw new RuntimeException('Eine Organigrammrolle ohne konkrete Berechtigung darf keinen Zugriff erteilen.');

echo "flz_akut access policy tests passed\n";
