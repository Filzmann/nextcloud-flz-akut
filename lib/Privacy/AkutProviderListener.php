<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Privacy;

use OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;

final class AkutProviderListener implements IEventListener {
    public function __construct(private AkutPersonalDataProvider $personal,private AkutProcessingMetadataProvider $metadata,private AkutRetentionProvider $retention){}
    public function handle(Event $event):void{if($event instanceof RegisterPersonalDataProvidersEvent)$event->register($this->personal);elseif($event instanceof RegisterProcessingMetadataProvidersEvent)$event->register($this->metadata);elseif($event instanceof RegisterRetentionProvidersEvent)$event->register($this->retention);}
}
