<?php

declare(strict_types=1);

// Regression: Falsche OCP-Imports (z. B. OCP\IUserConfig statt OCP\Config\IUserConfig) brechen die
// DI zur Laufzeit mit HTML-Fehlerseiten statt JSON. Jeder OCP-Import in lib/ muss bekannt sein
// (aus flz_akut@HEAD oder aus den lib/-Verzeichnissen der Sibling-Apps im Workspace).

$root = dirname(__DIR__, 2);
$known = [];

$head = shell_exec('git -C ' . escapeshellarg($root) . ' grep -h "^use OCP" HEAD -- lib/ 2>/dev/null') ?: '';
foreach (preg_split('/\r?\n/', $head) as $line) {
    if (preg_match('/^use (OCP\\\\[^;]+);/', $line, $m)) $known[$m[1]] = true;
}
foreach (glob(dirname($root) . '/*/lib', GLOB_ONLYDIR) ?: [] as $siblingLib) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($siblingLib, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') continue;
        $content = (string)file_get_contents($file->getPathname());
        if (preg_match_all('/^use (OCP\\\\[^;]+);/m', $content, $matches)) {
            foreach ($matches[1] as $fqcn) $known[$fqcn] = true;
        }
    }
}
if (count($known) < 20) throw new RuntimeException('Die Referenzliste der OCP-Interfaces konnte nicht aufgebaut werden.');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/lib', FilesystemIterator::SKIP_DOTS));
$checked = 0;
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') continue;
    $content = (string)file_get_contents($file->getPathname());
    if (preg_match_all('/^use (OCP\\\\[^;]+);/m', $content, $matches)) {
        foreach ($matches[1] as $fqcn) {
            $checked++;
            if (!isset($known[$fqcn])) {
                throw new RuntimeException('Unbekannter OCP-Import in ' . $file->getPathname() . ': ' . $fqcn . ' (DI würde zur Laufzeit scheitern).');
            }
        }
    }
}
if ($checked < 20) throw new RuntimeException('Zu wenige OCP-Imports geprüft — Test ist wirkungslos.');
if (str_contains((string)file_get_contents($root . '/lib/Service/MaKuerzelService.php'), 'use OCP\\IUserConfig;')) {
    throw new RuntimeException('MaKuerzelService muss OCP\\Config\\IUserConfig verwenden.');
}

echo "flz_akut OCP import contract tests passed ({$checked} imports geprüft)\n";
