<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Permission;

use OCA\FlzAkut\Service\OrganizationGateway;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionCondition;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProvider;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderDescriptor;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderResult;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionRule;

final class AkutPermissionProvider implements PermissionProvider {
    public function __construct(private OrganizationGateway $organization){}
    public function descriptor():PermissionProviderDescriptor{return new PermissionProviderDescriptor('flz_akut','Akutschichtvermittlung','1.0',['permissions']);}
    public function collect():PermissionProviderResult{$snapshot=$this->organization->snapshot();if($snapshot['status']!=='AVAILABLE')return new PermissionProviderResult([],false,['LocalBase-Organisationsvertrag fehlt oder ist inkompatibel.']);$rules=[];foreach($snapshot['areas']as$key=>$area){foreach(['office','eb']as$role){if(!isset($snapshot['roles'][$role]))continue;$condition=PermissionCondition::all([PermissionCondition::group($snapshot['roles'][$role]['groupId']),PermissionCondition::group($area['groupId'])]);foreach([['acute.shift.read','Akutschichten lesen'],['acute.shift.manage','Akutschichten vermitteln']]as[$permission,$label])$rules[]=new PermissionRule('Akutschicht',$area['label'],$label,$permission,$label,'allow','bureau:'.$key,$condition,'flz_akut:AccessService','high');}}$rules[]=new PermissionRule('Datenschutzkonfiguration','Aufbewahrung','Aufbewahrungsfrist konfigurieren','acute.retention.manage','Aufbewahrung konfigurieren','allow','app-settings',PermissionCondition::group('Datenschutzbeauftragte'),'flz_akut:RetentionSettingsService','high');return new PermissionProviderResult($rules);}
}
