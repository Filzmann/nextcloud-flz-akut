<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

final class PersonalProfileSection implements IIconSection {
    public function __construct(private IURLGenerator $url, private IL10N $l10n) {}
    public function getIcon(): string { return $this->url->imagePath('flz_akut', 'app.svg'); }
    public function getID(): string { return 'flz_akut'; }
    public function getName(): string { return $this->l10n->t('Akutdienst'); }
    public function getPriority(): int { return 60; }
}
