<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Settings;

use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Service\MailTemplateService;
use OCA\FlzAkut\Service\OfficeDayService;
use OCA\FlzAkut\Service\PermissionSettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

final class Admin implements ISettings {
    public function __construct(private PermissionSettingsService $settings, private MailTemplateService $mailTemplates, private OfficeDayService $officeDay) {}

    public function getForm(): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'admin', [
            'permissionSettings' => $this->settings->current(),
            'roleSelection' => $this->settings->selection(),
            'mailTemplate' => $this->mailTemplates->template(),
            'officeDayStart' => $this->officeDay->start(),
        ]);
    }

    public function getSection(): string { return Application::APP_ID; }
    public function getPriority(): int { return 30; }
}
