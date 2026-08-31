<?php declare(strict_types=1);

/**
 * Bootstrap for Translator module tests.
 *
 * Reuses the Common module test harness (the only sanctioned test dependency
 * besides Omeka core). The Translator and TranslatorTest namespaces are
 * registered from this module's composer.json autoload and autoload-dev by the
 * helper.
 */

require_once dirname(__DIR__, 3) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    ['Common', 'Translator'],
    'TranslatorTest',
    __DIR__ . '/TranslatorTest'
);
