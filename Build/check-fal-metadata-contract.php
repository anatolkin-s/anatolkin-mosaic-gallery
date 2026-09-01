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
$resolver = readFileOrFail($root . '/Classes/Service/GalleryInheritedMetadataResolver.php', $failures);

if ($assembler !== '') {
    $normalized = preg_replace('/\s+/', ' ', $assembler);
    if (!is_string($normalized)) {
        $failures[] = 'Unable to normalize GalleryItemAssembler.php for structural checks';
        $normalized = '';
    }

    if ($normalized === '' || !str_contains($normalized, '$file->getMetaData()->get()')) {
        $failures[] = 'GalleryItemAssembler must load inherited metadata via $file->getMetaData()->get()';
    }

    if (!str_contains($assembler, 'GalleryInheritedMetadataResolver')) {
        $failures[] = 'GalleryItemAssembler must resolve inherited caption/alt via GalleryInheritedMetadataResolver';
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

    if ($normalized !== '' && str_contains($normalized, "\$metadata['caption'] !== '' ? \$metadata['caption'] : (\$metadata['title'] !== '' ? \$metadata['title'] : \$metadata['description'])")) {
        $failures[] = 'Folder caption must not use caption -> title -> description fallback';
    }

    if ($normalized !== '' && str_contains($normalized, "\$alt = \$metadata['alternative'] ?: \$caption;")) {
        $failures[] = 'Inherited alt must not synthesize from caption/title/description';
    }

    if (!preg_match('/\$inheritedCaption = \(string\)\$metadata\[\'title\'\]/s', $assembler)) {
        $failures[] = 'Folder caption inherit must use localized File Title only';
    }

    if (!preg_match('/getProperty\(\'title\'\)/s', $assembler)) {
        $failures[] = 'Manual caption inherit must use TYPO3 FileReference Title via getProperty()';
    }

    if (!preg_match('/getProperty\(\'alternative\'\)/s', $assembler)) {
        $failures[] = 'Manual alt inherit must use TYPO3 FileReference Alternative via getProperty()';
    }

    if (preg_match('/getProperty\(\'description\'\)[\s\S]{0,200}resolveCaption/s', $assembler)) {
        $failures[] = 'Manual caption must not inherit FileReference Description';
    }

    $overridePos = strpos($assembler, '$fileOverride = $metadataOverrides');
    $captionAssignPos = strpos($assembler, "'caption' => (string)\$caption");
    $metadataBuildPos = strpos($assembler, "'title' => (string)(\$meta['title']");
    if ($overridePos === false || $captionAssignPos === false || $metadataBuildPos === false
        || !($metadataBuildPos < $overridePos && $overridePos < $captionAssignPos)
    ) {
        $failures[] = 'Gallery-specific overrides must be applied after inherited metadata and before item assignment';
    }
}

if ($resolver !== '') {
    if (!str_contains($resolver, 'resolveCaption(string $inheritedTitle')) {
        $failures[] = 'GalleryInheritedMetadataResolver caption inherit must use inherited Title only';
    }
    if (str_contains($resolver, 'description')) {
        $failures[] = 'GalleryInheritedMetadataResolver must not reference Description for caption inheritance';
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
        || str_contains($controller, "getQueryBuilderForTable(\"sys_file_metadata\")")
    ) {
        $failures[] = 'GalleryController must not query sys_file_metadata directly';
    }

    if (str_contains($controller, 'ConnectionPool')) {
        $failures[] = 'GalleryController must not retain ConnectionPool after removing custom metadata SQL';
    }
}

$resolverTest = $root . '/Build/test-gallery-inherited-metadata-resolver.php';
if (!is_file($resolverTest)) {
    $failures[] = 'Missing executable inherited metadata resolver test';
} else {
    $output = [];
    $exitCode = 0;
    exec('php ' . escapeshellarg($resolverTest) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        $failures[] = 'Inherited metadata resolver executable test failed: ' . implode("\n", $output);
    }
}

// Title-only caption fixture: description-only metadata must not resolve caption.
$meta = [
    'title' => '',
    'caption' => '',
    'description' => 'Photo by chuttersnap on Unsplash',
    'alternative' => '',
    'copyright' => '',
];
$caption = (string)($meta['title'] ?? '');
if ($caption !== '') {
    $failures[] = 'Description-only native metadata must not resolve caption when File Title is empty';
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
