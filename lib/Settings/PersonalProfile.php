<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Settings;

use OCA\FlzAkut\Service\MaKuerzelService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IUserSession;
use OCP\Settings\ISettings;

final class PersonalProfile implements ISettings {
    public function __construct(private IUserSession $session, private MaKuerzelService $kuerzel) {}

    public function getForm(): TemplateResponse {
        $user = $this->session->getUser();
        $uid = $user?->getUID() ?? '';
        $displayName = $user?->getDisplayName() ?? $uid;
        return new TemplateResponse('flz_akut', 'profile-personal', [
            'kuerzel' => $uid !== '' ? $this->kuerzel->effective($uid, $displayName) : '',
        ]);
    }

    public function getSection(): string { return 'flz_akut'; }
    public function getPriority(): int { return 10; }
}
