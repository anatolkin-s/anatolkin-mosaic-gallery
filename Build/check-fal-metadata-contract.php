<?php
declare(strict_types=1);

/**
 * Structural checks for the gallery Fluid FAL metadata contract.
 *
 * Inherited metadata must come from TYPO3 native FAL:
 * File → MetaDataAspect → MetaDataRepository → EnrichFileMetaDataEvent
 * (frontend FileMetadataOverlayAspect applies language/workspace overlays).
 *
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
$assembler = readFileOrFail($root . '/Classes/Service/GalleryItemAssembler.php', $failures);

if ($assembler !== '') {
    $normalized = preg_replace('/\s+/', ' ', $assembler);
    if (!is_string($normalized)) {
        $failures[] = 'Unable to normalize GalleryItemAssembler.php for structural checks';
        $normalized = '';
    }

    if ($normalized === '' || !str_contains($normalized, '$file->getMetaData()->get()')) {
        $failures[] = 'GalleryItemAssembler must load inherited metadata via $file->getMetaData()->get()';
    }

    if (!str_contains($assembler, "'metadata' => \$metadata")) {
        $failures[] = 'GalleryItemAssembler items must expose a metadata array key';
    }

    if (!str_contains($assembler, "'file' => \$file")) {
        $failures[] = 'GalleryItemAssembler items must expose the File object as file';
    }

    if (!str_contains($assembler, "'caption' => (string)\$caption")) {
        $failures[] = 'GalleryItemAssembler items must expose resolved caption';
    }

    if (!str_contains($assembler, "'alt' => (string)\$alt")) {
        $failures[] = 'GalleryItemAssembler items must expose resolved alt';
    }

    foreach (['title', 'caption', 'description', 'alternative', 'copyright'] as $key) {
        $assignment = "'" . $key . "' => (string)(\$meta['" . $key . "'] ?? '')";
        if ($normalized === '' || !str_contains($normalized, $assignment)) {
            $failures[] = 'metadata subset must include ' . $key . ' from native $meta';
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

    $overridePos = strpos($assembler, '$fileOverride = $metadataOverrides');
    $captionAssignPos = strpos($assembler, "'caption' => (string)\$caption");
    $metadataBuildPos = strpos($assembler, "'title' => (string)(\$meta['title']");
    if ($overridePos === false || $captionAssignPos === false || $metadataBuildPos === false
        || !($metadataBuildPos < $overridePos && $overridePos < $captionAssignPos)
    ) {
        $failures[] = 'Gallery-specific overrides must be applied after inherited metadata and before item assignment';
    }

    if (!str_contains($normalized, "\$alt = \$metadata['alternative'] ?: \$caption;")) {
        $failures[] = 'Inherited alt fallback must use $metadata[\'alternative\'] ?: $caption to preserve PHP falsy semantics';
    }

    if (str_contains($normalized, "\$alt = \$metadata['alternative'] !== ''")) {
        $failures[] = 'Inherited alt fallback must not use a non-empty-string comparison';
    }
}

if ($controller !== '') {
    if (!str_contains($controller, "'data' => \$this->resolveContentData()")) {
        $failures[] = 'Issue #8 data Fluid contract must remain intact';
    }

    if (str_contains($controller, 'function getLocalizedMeta(')) {
        $failures[] = 'Custom getLocalizedMeta() must be removed; use native FAL MetaDataAspect';
    }

    if (str_contains($controller, '\\PDO::PARAM_INT') || str_contains($controller, 'PDO::PARAM_INT')) {
        $failures[] = 'GalleryController must not use PDO::PARAM_INT';
    }

    if (str_contains($controller, "getQueryBuilderForTable('sys_file_metadata')")
        || str_contains($controller, 'getQueryBuilderForTable("sys_file_metadata")')
    ) {
        $failures[] = 'GalleryController must not query sys_file_metadata directly';
    }

    if (str_contains($controller, 'ConnectionPool')) {
        $failures[] = 'GalleryController must not retain ConnectionPool after removing custom metadata SQL';
    }
}

// Caption fallback fixture: description-only metadata resolves caption to description.
$meta = [
    'title' => '',
    'caption' => '',
    'description' => 'Photo by chuttersnap on Unsplash',
    'alternative' => '',
    'copyright' => '',
];
$metadata = [
    'title' => (string)($meta['title'] ?? ''),
    'caption' => (string)($meta['caption'] ?? ''),
    'description' => (string)($meta['description'] ?? ''),
    'alternative' => (string)($meta['alternative'] ?? ''),
    'copyright' => (string)($meta['copyright'] ?? ''),
];
$caption = $metadata['caption'] !== ''
    ? $metadata['caption']
    : ($metadata['title'] !== '' ? $metadata['title'] : $metadata['description']);
if ($metadata['description'] !== 'Photo by chuttersnap on Unsplash') {
    $failures[] = 'Fixture metadata.description must remain the Unsplash description string';
}
if ($caption !== 'Photo by chuttersnap on Unsplash') {
    $failures[] = 'Description-only native metadata must resolve caption via caption → title → description';
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
