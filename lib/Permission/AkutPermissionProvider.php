<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Permission;

use OCA\FlzAkut\Service\OrganizationGateway;
use OCA\FlzAkut\Service\AkutCapability;
use OCA\FlzAkut\Service\PermissionSettingsService;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionCondition;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProvider;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderDescriptor;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionProviderResult;
use OCA\FlzPermissionMatrix\PublicApi\V1\PermissionRule;

final class AkutPermissionProvider implements PermissionProvider {
    public function __construct(private OrganizationGateway $organization,private PermissionSettingsService $settings){}
    public function descriptor():PermissionProviderDescriptor{return new PermissionProviderDescriptor('flz_akut','Akutschichtvermittlung','1.0',['permissions']);}
    public function collect():PermissionProviderResult{$snapshot=$this->organization->snapshot();if(($snapshot['status']??null)!=='AVAILABLE')return new PermissionProviderResult([],false,['LocalBase-Organisationsvertrag fehlt oder ist inkompatibel.']);$rules=[];$complete=true;foreach($snapshot['areas']as$key=>$area){foreach(AkutCapability::DEFINITIONS as$capability=>[$permission,$label,$detail]){$groups=$this->settings->effectiveRoleGroups($capability);if(!$this->settings->capabilityComplete($capability))$complete=false;if($groups===[])continue;$roleCondition=count($groups)===1?PermissionCondition::group($groups[0]):PermissionCondition::any(array_map(static fn(string $groupId):PermissionCondition=>PermissionCondition::group($groupId),$groups));$condition=PermissionCondition::all([$roleCondition,PermissionCondition::group($area['groupId'])]);$rules[]=new PermissionRule('Akutschicht',$area['label'],$detail,$permission,$label,'allow','bureau:'.$key,$condition,'flz_akut:AccessService','high');}}$rules[]=new PermissionRule('Datenschutzkonfiguration','Aufbewahrung','Aufbewahrungsfrist konfigurieren','acute.retention.manage','Aufbewahrung konfigurieren','allow','app-settings',PermissionCondition::group('Datenschutzbeauftragte'),'flz_akut:RetentionSettingsService','high');$rules[]=new PermissionRule('App-Konfiguration','Berechtigungen','Organigrammrollen den Akutberechtigungen zuordnen','acute.permissions.manage','Akutberechtigungen konfigurieren','allow','app-settings',PermissionCondition::nextcloudAdmin(),'flz_akut:PermissionSettingsService','high');$selection=$this->settings->selection();$warnings=$complete&&$selection['complete']?[]:[$selection['message']!==''?$selection['message']:'Mindestens eine Akutberechtigung ist unvollständig.'];return new PermissionProviderResult($rules,$complete&&$selection['complete'],$warnings);}
}
