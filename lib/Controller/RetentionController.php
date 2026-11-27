<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Controller;

use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Service\RetentionSettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IL10N;
use OCP\IRequest;

final class RetentionController extends Controller {
    public function __construct(IRequest $request,private RetentionSettingsService $settings,private IL10N $l10n){parent::__construct(Application::APP_ID,$request);}
    #[NoAdminRequired, \OCP\AppFramework\Http\Attribute\NoCSRFRequired]
    public function show():DataResponse{return $this->settings->isDpo()?new DataResponse(['days'=>$this->settings->days(),'minimum'=>30,'maximum'=>365]):new DataResponse(['error'=>$this->l10n->t('Kein Zugriff.')],403);}
    #[NoAdminRequired]
    public function save(int $days):DataResponse{try{return new DataResponse(['days'=>$this->settings->save($days)]);}catch(\InvalidArgumentException $e){return new DataResponse(['error'=>$this->l10n->t($e->getMessage())],400);}catch(\DomainException $e){return new DataResponse(['error'=>$this->l10n->t($e->getMessage())],403);}}
}
