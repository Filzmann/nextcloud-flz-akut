<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

use OCP\IAppConfig;

final class MailTemplateService {
    private const APP_ID = 'flz_akut';
    private const KEY = 'mail_template';
    public const MAX_LENGTH = 4000;
    public const DEFAULT_TEMPLATE = "Liebe Kolleg*innen,\n\nfür das ASN-Team {team} sind aktuell folgende Schichten offen:\n\n{shifts}\n\nBitte meldet euch, wenn ihr eine davon übernehmen könnt.\n\nLG\n{sender}";

    public function __construct(private IAppConfig $config) {}

    public function template(): string {
        $value = trim($this->config->getValueString(self::APP_ID, self::KEY, ''));
        return $value === '' ? self::DEFAULT_TEMPLATE : $value;
    }

    public function save(string $template): string {
        $template = trim($template);
        if (strlen($template) > self::MAX_LENGTH) throw new \InvalidArgumentException('Die Mail-Vorlage darf höchstens ' . self::MAX_LENGTH . ' Zeichen lang sein.');
        $stored = $template === '' ? self::DEFAULT_TEMPLATE : $template;
        $this->config->setValueString(self::APP_ID, self::KEY, $stored);
        return $stored;
    }
}
