<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IUserSession;

final class RetentionSettingsService {
    private const APP_ID='flz_akut';
    private const KEY='retention_days';
    public function __construct(private IAppConfig $config,private IGroupManager $groups,private IUserSession $session){}
    public function days():int{$days=$this->config->getValueInt(self::APP_ID,self::KEY,180);return max(30,min(365,$days));}
    public function save(int $days):int{$this->assertDpo();if($days<30||$days>365)throw new \InvalidArgumentException('Die Aufbewahrung muss zwischen 30 und 365 Tagen liegen.');$this->config->setValueInt(self::APP_ID,self::KEY,$days);return$days;}
    public function isDpo():bool{$user=$this->session->getUser();return$user!==null&&in_array('Datenschutzbeauftragte',$this->groups->getUserGroupIds($user),true);}
    private function assertDpo():void{if(!$this->isDpo())throw new \DomainException('Nur Datenschutzbeauftragte dürfen die Aufbewahrung konfigurieren.');}
}
