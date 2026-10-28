<?php

declare(strict_types=1);

namespace OCA\FlzAkut\BackgroundJob;

use OCA\FlzAkut\Repository\ShiftStore;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

final class RetentionJob extends TimedJob {
    public function __construct(private ITimeFactory $clock,private ShiftStore $store){parent::__construct($clock);$this->setInterval(86400);}
    protected function run($argument):void{$this->store->deleteExpired((new \DateTimeImmutable('@'.$this->clock->getTime()))->format(DATE_ATOM));}
}
