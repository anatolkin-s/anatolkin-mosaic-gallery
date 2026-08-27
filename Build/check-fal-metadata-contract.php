<?php
declare(strict_types=1);

/**
 * Structural checks for the gallery Fluid FAL metadata contract.
 * Run: php Build/check-fal-metadata-contract.php
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
    if (!str_contains($controller, "'metadata' => \$metadata")) {
        $failures[] = 'GalleryController items must expose a metadata array key';
    }

    foreach (['title', 'caption', 'description', 'alternative', 'copyright'] as $key) {
        if (!preg_match("/'" . preg_quote($key, '/') . "'\\s*=>\\s*\\(string\\)\\(\\\$meta\\['" . preg_quote($key, '/') . "'\\]/s*\\?\\?\\s*''\\)/", $controller)) {
            $failures[] = 'metadata subset must include ' . $key . ' from localized \$meta';
        }
    }

    if (!preg_match(
        '/\\\$metadata\\[\'caption\'\\]\\s*!==\\s*\'\'\\s*\\?\\s*\\\$metadata\\[\'caption\'\\]\\s*:\\s*\\(\\\$metadata\\[\'title\'\\]\\s*!==\\s*\'\'\\s*\\?\\s*\\\$metadata\\[\'title\'\\]\\s*:\\s*\\\$metadata\\[\'description\'\\]\\)/s',
        $controller
    )) {
        $failures[] = 'FAL caption precedence must be caption -> title -> description';
    }

    if (preg_match(
        '/\\\$title\\s*!==\\s*\'\'\\s*\\?\\s*\\\$title\\s*:\\s*\\(\\\$captionMeta\\s*!==\\s*\'\'\\s*\\?\\s*\\\$captionMeta\\s*:\\s*\\\$description\\)/',
        $controller
    )) {
        $failures[] = 'Legacy title-first caption precedence must not remain';
    }

    $overridePos = strpos($controller, "\$fileOverride = \$metadataOverrides");
    $captionAssignPos = strpos($controller, "'caption' => (string)\$caption");
    $metadataBuildPos = strpos($controller, "'title' => (string)(\$meta['title']");
    if ($overridePos === false || $captionAssignPos === false || $metadataBuildPos === false
        || !($metadataBuildPos < $overridePos && $overridePos < $captionAssignPos)
    ) {
        $failures[] = 'Gallery-specific overrides must be applied after inherited metadata and before item assignment';
    }

    if (!preg_match("/'data'\\s*=>\\s*\\\$this->resolveContentData\\(\\)/", $controller)) {
        $failures[] = 'Issue #8 data Fluid contract must remain intact';
    }

    if (substr_count($controller, 'function getLocalizedMeta(') !== 1) {
        $failures[] = 'Must reuse a single getLocalizedMeta() localization path';
    }

    if (!str_contains($controller, "\$alt = \$metadata['alternative'] ?: \$caption;")) {
        $failures[] = 'Inherited alt fallback must use $metadata[\'alternative\'] ?: $caption to preserve PHP falsy semantics';
    }

    if (preg_match(
        '/\\\$alt\\s*=\\s*\\\$metadata\\[\'alternative\'\\]\\s*!==\\s*\'\'/',
        $controller
    )) {
        $failures[] = 'Inherited alt fallback must not use a non-empty-string comparison';
    }
}

if ($failures === []) {
    fwrite(STDOUT, "FAL metadata Fluid contract checks passed.\n");
    exit(0);
}

fwrite(STDERR, "FAL metadata Fluid contract checks failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
