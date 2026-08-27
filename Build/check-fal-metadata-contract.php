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
    $normalized = preg_replace('/\s+/', ' ', $controller);
    if (!is_string($normalized)) {
        $failures[] = 'Unable to normalize GalleryController.php for structural checks';
        $normalized = '';
    }

    if (!str_contains($controller, "'metadata' => \$metadata")) {
        $failures[] = 'GalleryController items must expose a metadata array key';
    }

    foreach (['title', 'caption', 'description', 'alternative', 'copyright'] as $key) {
        $assignment = "'" . $key . "' => (string)(\$meta['" . $key . "'] ?? '')";
        if ($normalized === '' || !str_contains($normalized, $assignment)) {
            $failures[] = 'metadata subset must include ' . $key . ' from localized \$meta';
        }
    }

    $captionFirst = "\$metadata['caption'] !== '' ? \$metadata['caption'] : (\$metadata['title'] !== '' ? \$metadata['title'] : \$metadata['description'])";
    if ($normalized === '' || !str_contains($normalized, $captionFirst)) {
        $failures[] = 'FAL caption precedence must be caption -> title -> description';
    }

    $legacyTitleFirst = "\$title !== '' ? \$title : (\$captionMeta !== '' ? \$captionMeta : \$description)";
    if ($normalized !== '' && str_contains($normalized, $legacyTitleFirst)) {
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

    if (!str_contains($normalized, "'data' => \$this->resolveContentData()")) {
        $failures[] = 'Issue #8 data Fluid contract must remain intact';
    }

    if (substr_count($controller, 'function getLocalizedMeta(') !== 1) {
        $failures[] = 'Must reuse a single getLocalizedMeta() localization path';
    }

    if (!str_contains($controller, "\$alt = \$metadata['alternative'] ?: \$caption;")) {
        $failures[] = 'Inherited alt fallback must use $metadata[\'alternative\'] ?: $caption to preserve PHP falsy semantics';
    }

    if (str_contains($normalized, "\$alt = \$metadata['alternative'] !== ''")) {
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
