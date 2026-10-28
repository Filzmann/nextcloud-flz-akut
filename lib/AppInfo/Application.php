<?php

declare(strict_types=1);

namespace OCA\FlzAkut\AppInfo;

use OCA\FlzAkut\Repository\DbShiftStore;
use OCA\FlzAkut\Repository\ShiftStore;
use OCA\FlzAkut\Privacy\AkutPrivacySource;
use OCA\FlzAkut\Privacy\NextcloudAkutPrivacySource;
use OCA\FlzAkut\Privacy\AkutProviderListener;
use OCA\FlzAkut\Permission\AkutPermissionProviderListener;
use OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterProcessingMetadataProvidersEvent;
use OCA\FlzDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
use OCA\FlzPermissionMatrix\PublicApi\V1\RegisterPermissionProvidersEvent;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;

final class Application extends App implements IBootstrap {
    public const APP_ID='flz_akut';
    public function __construct(array $urlParams=[]){parent::__construct(self::APP_ID,$urlParams);}
    public function register(IRegistrationContext $context):void{$context->registerServiceAlias(ShiftStore::class,DbShiftStore::class);$context->registerServiceAlias(AkutPrivacySource::class,NextcloudAkutPrivacySource::class);$context->registerEventListener(RegisterPersonalDataProvidersEvent::class,AkutProviderListener::class);$context->registerEventListener(RegisterProcessingMetadataProvidersEvent::class,AkutProviderListener::class);$context->registerEventListener(RegisterRetentionProvidersEvent::class,AkutProviderListener::class);$context->registerEventListener(RegisterPermissionProvidersEvent::class,AkutPermissionProviderListener::class);}
    public function boot(IBootContext $context):void{}
}
