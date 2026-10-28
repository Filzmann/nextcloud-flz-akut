<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\App\IAppManager;
use OCP\Server;

final class PlanerCatalogGateway {
    public function __construct(private IAppManager $apps) {}
    public function teams(array $bureauKeys): array {
        if(!in_array('flzplaner',$this->apps->getEnabledApps(),true))return['status'=>'MISSING','teams'=>[]];
        $class='OCA\\FlzPlaner\\PublicApi\\V1\\AcuteShiftCatalogProvider';
        if(!class_exists($class))return['status'=>'INCOMPATIBLE','teams'=>[]];
        try{$teams=Server::get($class)->teamsForBureaux($bureauKeys);$result=[];foreach($teams as $team){if($team->contractVersion()!=='1.0')return['status'=>'INCOMPATIBLE','teams'=>[]];$result[]=['teamCode'=>$team->teamCode(),'displayName'=>$team->displayName(),'bureauKey'=>$team->bureauKey(),'shiftTemplates'=>$team->shiftTemplates(),'assistants'=>$team->assistants()];}return['status'=>'AVAILABLE','teams'=>$result];}catch(\Throwable){return['status'=>'UNAVAILABLE','teams'=>[]];}
    }
}
