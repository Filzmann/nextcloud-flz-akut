<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\IGroupManager;
use OCP\IUserSession;

final class AccessService {
    public function __construct(private IUserSession $session,private IGroupManager $groups,private OrganizationGateway $organization,private AccessPolicy $policy){}
    public function currentUid():string{$user=$this->session->getUser();if($user===null)throw new \DomainException('Nicht angemeldet.');return(string)$user->getUID();}
    public function bureauKeys():array{$user=$this->session->getUser();if($user===null)return[];$snapshot=$this->organization->snapshot();if($snapshot['status']!=='AVAILABLE')return[];return$this->policy->bureauKeys(array_map('strval',$this->groups->getUserGroupIds($user)),$snapshot['roles'],$snapshot['areas']);}
    public function assertBureau(string $key):void{if(!in_array($key,$this->bureauKeys(),true))throw new \DomainException('Kein Zugriff auf dieses Büro.');}
}
