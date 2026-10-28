<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Permission;

use OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class AkutPermissionProviderListener implements IEventListener {
    public function __construct(private AkutPermissionProvider $provider){}
    public function handle(Event $event):void{if($event instanceof RegisterPermissionProvidersEvent)$event->register($this->provider);}
}
