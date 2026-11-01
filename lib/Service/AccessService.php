<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\IGroupManager;
use OCP\IUserSession;

final class AccessService {
    public function __construct(private IUserSession $session,private IGroupManager $groups,private OrganizationGateway $organization,private AccessPolicy $policy,private PermissionSettingsService $permissions){}
    public function currentUid():string{$user=$this->session->getUser();if($user===null)throw new \DomainException('Nicht angemeldet.');return(string)$user->getUID();}
    public function bureauKeys(string $capability):array{$user=$this->session->getUser();if($user===null)return[];$snapshot=$this->organization->snapshot();if(($snapshot['status']??null)!=='AVAILABLE')return[];$roleGroups=$this->permissions->effectiveRoleGroups($capability);if($roleGroups===[])return[];return$this->policy->bureauKeys(array_map('strval',$this->groups->getUserGroupIds($user)),$roleGroups,$snapshot['areas']);}
    public function assertBureau(string $capability,string $key):void{if(!in_array($key,$this->bureauKeys($capability),true))throw new \DomainException('Diese Aktion ist für das Büro nicht erlaubt.');}
}
