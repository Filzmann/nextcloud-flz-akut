<?php

declare(strict_types=1);

namespace OCA\FlzAkut\Service;

final class AkutCapability {
    public const READ = 'read';
    public const CREATE = 'create';
    public const SPLIT = 'split';
    public const CONTACT = 'contact';
    public const ASSIGN = 'assign';
    public const REOPEN = 'reopen';
    public const CONFIRM_HYCARE = 'confirmHycare';
    public const CONFIRM_PAPER = 'confirmPaper';

    public const DEFINITIONS = [
        self::READ => ['acute.shift.read', 'Akutschichten lesen', 'Offene und vermittelte Schichten im eigenen Büro lesen'],
        self::CREATE => ['acute.shift.create', 'Offene Schicht anlegen', 'Eine offene Schicht im eigenen Büro anlegen'],
        self::SPLIT => ['acute.shift.split', 'Schicht aufteilen', 'Eine offene Schicht im eigenen Büro aufteilen'],
        self::CONTACT => ['acute.contact.manage', 'Kontaktversuche dokumentieren', 'Kontaktversuche zu Team-AS im eigenen Büro dokumentieren'],
        self::ASSIGN => ['acute.shift.assign', 'AS vermitteln', 'Eine geeignete Team-AS einer offenen Schicht im eigenen Büro zuweisen'],
        self::REOPEN => ['acute.shift.reopen', 'Schicht erneut öffnen', 'Eine vermittelte Schicht im eigenen Büro erneut öffnen'],
        self::CONFIRM_HYCARE => ['acute.documentation.hycare.confirm', 'HyCare-Dokumentation bestätigen', 'Die HyCare-Dokumentation im eigenen Büro bestätigen'],
        self::CONFIRM_PAPER => ['acute.documentation.paper.confirm', 'Papierakte bestätigen', 'Die Papierakten-Dokumentation im eigenen Büro bestätigen'],
    ];

    /** @return list<string> */
    public static function actionCapabilities(): array {
        return array_keys(self::DEFINITIONS);
    }

    private function __construct() {}
}
