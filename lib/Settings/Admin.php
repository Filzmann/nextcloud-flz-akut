<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Settings;

use OCA\FlzAkut\AppInfo\Application;
use OCA\FlzAkut\Service\PermissionSettingsService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

final class Admin implements ISettings {
    public function __construct(private PermissionSettingsService $settings) {}

    public function getForm(): TemplateResponse {
        return new TemplateResponse(Application::APP_ID, 'admin', [
            'permissionSettings' => $this->settings->current(),
            'roleSelection' => $this->settings->selection(),
        ]);
    }

    public function getSection(): string { return Application::APP_ID; }
    public function getPriority(): int { return 30; }
}
