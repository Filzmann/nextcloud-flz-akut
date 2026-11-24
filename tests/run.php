<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use OCA\LocalBase\Tests\Support\PhpTestRunner;

PhpTestRunner::run(
    root: dirname(__DIR__),
    lintDirectories: ['lib'],
    testDirectories: ['tests'],
    testSuffixes: ['Test.php'],
    successMessage: 'flz_akut PHP tests passed',
    prependBootstrap: true,
);
