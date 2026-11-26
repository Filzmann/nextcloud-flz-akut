<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Controller;

use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Service\MailTemplateService;
use OCA\FlzAkut\Service\OfficeDayService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

final class AdminSettingsController extends Controller {
    public function __construct(IRequest $request,private MailTemplateService $mailTemplates,private OfficeDayService $officeDay){parent::__construct(Application::APP_ID,$request);}
    public function saveMailTemplate(string $template):DataResponse{try{return new DataResponse(['ok'=>true,'template'=>$this->mailTemplates->save($template)]);}catch(\InvalidArgumentException $e){return new DataResponse(['ok'=>false,'error'=>$e->getMessage()],400);}}
    public function saveOfficeDayStart(string $officeDayStart):DataResponse{try{return new DataResponse(['ok'=>true,'officeDayStart'=>$this->officeDay->save($officeDayStart)]);}catch(\InvalidArgumentException $e){return new DataResponse(['ok'=>false,'error'=>$e->getMessage()],400);}}
}
