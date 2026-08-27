<?php
declare(strict_types=1);

/**
 * Structural checks for the gallery Fluid tt_content data contract.
 * Run: php Build/check-fluid-data-contract.php
 */

$root = dirname(__DIR__);
$failures = [];

function readFileOrFail(string $path, array &$failures): string
{
    if (!is_file($path)) {
        $failures[] = 'Missing file: ' . $path;
        return '';
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        $failures[] = 'Unreadable file: ' . $path;
        return '';
    }
    return $contents;
}

$controller = readFileOrFail($root . '/Classes/Controller/GalleryController.php', $failures);

if ($controller !== '') {
    if (!preg_match("/'data'\s*=>\s*\\\$this->resolveContentData\(\)/", $controller)) {
        $failures[] = 'GalleryController must assign data via resolveContentData() in assignMultiple()';
    }

    if (!str_contains($controller, 'private function resolveContentData(): array')) {
        $failures[] = 'GalleryController must define resolveContentData()';
    }

    if (!preg_match(
        '/private function resolveContentData\(\): array\s*\{[^}]*getAttribute\([\'"]currentContentObject[\'"]\)[^}]*->data/s',
        $controller
    )) {
        $failures[] = 'resolveContentData() must read currentContentObject->data without transformation';
    }

    if (preg_match(
        '/private function resolveContentData\(\): array\s*\{[^}]*getLanguageOverlay/s',
        $controller
    )) {
        $failures[] = 'resolveContentData() must not perform a manual language overlay';
    }

    if (preg_match(
        '/private function resolveContentData\(\): array\s*\{[^}]*ConnectionPool/s',
        $controller
    )) {
        $failures[] = 'resolveContentData() must not query tt_content from the database';
    }
}

if ($failures === []) {
    fwrite(STDOUT, "Fluid tt_content data contract checks passed.\n");
    exit(0);
}

fwrite(STDERR, "Fluid tt_content data contract checks failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
