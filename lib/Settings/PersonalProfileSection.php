<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Settings;

use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

final class PersonalProfileSection implements IIconSection {
    public function __construct(private IURLGenerator $url) {}
    public function getIcon(): string { return $this->url->imagePath('flz_akut', 'app.svg'); }
    public function getID(): string { return 'flz_akut'; }
    public function getName(): string { return 'Akutdienst'; }
    public function getPriority(): int { return 60; }
}
