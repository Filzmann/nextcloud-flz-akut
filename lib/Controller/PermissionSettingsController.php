<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Controller;

use DomainException;
use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Service\PermissionSettingsService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use Psr\Log\LoggerInterface;
use Throwable;

final class PermissionSettingsController extends Controller {
    public function __construct(IRequest $request,private PermissionSettingsService $settings,private LoggerInterface $logger){parent::__construct(Application::APP_ID,$request);}
    public function save(array $permissions):DataResponse{try{return new DataResponse(['permissions'=>$this->settings->update($permissions)]);}catch(DomainException $error){return new DataResponse(['error'=>$error->getMessage()],422);}catch(Throwable $error){$this->logger->error('Akutberechtigungen konnten nicht gespeichert werden.',['app'=>Application::APP_ID,'exceptionClass'=>$error::class]);return new DataResponse(['error'=>'Die Akutberechtigungen konnten nicht gespeichert werden.'],Http::STATUS_INTERNAL_SERVER_ERROR);}}
}
