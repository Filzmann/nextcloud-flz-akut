<?php

declare(strict_types=1);

namespace OCP {
    interface IAppConfig {
        public function getValueString(string $appId, string $key, string $default): string;
        public function setValueString(string $appId, string $key, string $value): void;
    }
}

namespace {
    use OCA\FlzAkut\Service\MailTemplateService;

    require_once dirname(__DIR__) . '/bootstrap.php';

    $config = new class implements \OCP\IAppConfig {
        public array $values = [];
        public function getValueString(string $appId, string $key, string $default): string { return $this->values[$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$key] = $value; }
    };
    $service = new MailTemplateService($config);

    $default = $service->template();
    foreach (['{team}', '{shifts}', '{sender}'] as $placeholder) {
        if (!str_contains($default, $placeholder)) {
            throw new RuntimeException("Die Standard-Mailvorlage muss den Platzhalter {$placeholder} enthalten.");
        }
    }
    if (!str_starts_with($default, 'Liebe Kolleg*innen,') || !str_ends_with($default, "LG\n{sender}")) {
        throw new RuntimeException('Die Standard-Mailvorlage muss mit Sammelanrede beginnen und mit LG {sender} enden.');
    }

    $saved = $service->save("Hallo {recipients}, offen: {shifts} für {team} am {date}.");
    if ($saved !== "Hallo {recipients}, offen: {shifts} für {team} am {date}." || $service->template() !== $saved) {
        throw new RuntimeException('Eine gespeicherte Mailvorlage muss ausgelesen werden.');
    }

    $reset = $service->save('   ');
    if ($reset !== $default || $service->template() !== $default) {
        throw new RuntimeException('Eine leere Vorlage muss auf den Standard zurückgesetzt werden.');
    }

    try {
        $service->save(str_repeat('x', MailTemplateService::MAX_LENGTH + 1));
        throw new RuntimeException('Eine zu lange Mailvorlage darf nicht gespeichert werden.');
    } catch (InvalidArgumentException) {
    }
    if ($service->template() !== $default) {
        throw new RuntimeException('Eine abgelehnte Vorlage darf die gespeicherte Vorlage nicht verändern.');
    }

    echo "flz_akut mail template service tests passed\n";
}
