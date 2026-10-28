<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\App\IAppManager;
use OCP\Server;

/** Aktivierungs- und versionsgeprüfter Consumer des öffentlichen LocalBase-V1-Vertrags. */
class OrganizationGateway {
    public function __construct(private IAppManager $apps) {}
    public function snapshot(): array {
        if(!in_array('localbase',$this->apps->getEnabledApps(),true))return['status'=>'MISSING','roles'=>[],'areas'=>[]];
        $class='OCA\\LocalBase\\PublicApi\\V1\\OrganizationSnapshotService';
        if(!class_exists($class))return['status'=>'INCOMPATIBLE','roles'=>[],'areas'=>[]];
        try{$snapshot=Server::get($class)->snapshot();if($snapshot->contractVersion()!=='1.0'||!$snapshot->isValid())return['status'=>'INCOMPATIBLE','roles'=>[],'areas'=>[]];return['status'=>'AVAILABLE','roles'=>$snapshot->roles(),'areas'=>$snapshot->areas()];}catch(\Throwable){return['status'=>'UNAVAILABLE','roles'=>[],'areas'=>[]];}
    }
}
