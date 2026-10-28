<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\App\IAppManager;
use OCP\Server;

final class HolidayGateway {
    public function __construct(private IAppManager $apps) {}
    public function year(int $year): array {
        if(!in_array('localbase',$this->apps->getEnabledApps(),true))return['status'=>'MISSING','dates'=>[],'timezone'=>null];
        $class='OCA\\LocalBase\\PublicApi\\V1\\HolidayCalendarProvider';
        if(!class_exists($class))return['status'=>'INCOMPATIBLE','dates'=>[],'timezone'=>null];
        try{$snapshot=Server::get($class)->calendar($year);if($snapshot->contractVersion()!=='1.0'||!$snapshot->isUsable())return['status'=>'UNAVAILABLE','dates'=>[],'timezone'=>$snapshot->timezone()];return['status'=>'AVAILABLE','dates'=>$snapshot->publicHolidayDates(),'timezone'=>$snapshot->timezone(),'cacheStatus'=>$snapshot->cacheStatus()];}catch(\Throwable){return['status'=>'UNAVAILABLE','dates'=>[],'timezone'=>null];}
    }
}
