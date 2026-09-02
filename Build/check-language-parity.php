<?php
declare(strict_types=1);

/**
 * Localization parity gate for declared interface languages.
 * Run: php Build/check-language-parity.php
 */

$root = dirname(__DIR__);
$languageDir = $root . '/Resources/Private/Language';
$failures = [];

$families = [
    'frontend' => 'locallang.xlf',
    'backend' => 'locallang_be.xlf',
];
$locales = ['de', 'fr', 'es', 'ru'];
$supportedLanguages = ['en', 'de', 'fr', 'es', 'ru'];

/**
 * @return array{
 *   ids: list<string>,
 *   counts: array<string, int>,
 *   emptyTargets: int,
 *   sourceLanguage: string,
 *   targetLanguage: string,
 *   units: array<string, array{source: string, target: string}>
 * }
 */
function parseXliff(string $path): array
{
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = true;
    if (!@$dom->load($path)) {
        throw new RuntimeException('XML parse failed: ' . $path);
    }

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('x', 'urn:oasis:names:tc:xliff:document:1.2');

    $fileNodes = $xpath->query('//x:file');
    if ($fileNodes === false || $fileNodes->length === 0) {
        $fileNodes = $xpath->query('//file');
    }
    if ($fileNodes === false || $fileNodes->length !== 1) {
        throw new RuntimeException('Expected exactly one XLIFF file node: ' . $path);
    }

    /** @var DOMElement $file */
    $file = $fileNodes->item(0);
    $sourceLanguage = trim($file->getAttribute('source-language'));
    $targetLanguage = trim($file->getAttribute('target-language'));

    $unitNodes = $xpath->query('.//x:trans-unit', $file);
    if ($unitNodes === false || $unitNodes->length === 0) {
        $unitNodes = $xpath->query('.//trans-unit', $file);
    }
    if ($unitNodes === false) {
        throw new RuntimeException('Unable to query trans-unit nodes: ' . $path);
    }

    $ids = [];
    $units = [];
    $emptyTargets = 0;

    foreach ($unitNodes as $unitNode) {
        if (!$unitNode instanceof DOMElement) {
            continue;
        }
        $id = trim($unitNode->getAttribute('id'));
        if ($id === '') {
            throw new RuntimeException('Empty trans-unit id in: ' . $path);
        }
        $ids[] = $id;

        $sourceNodes = $unitNode->getElementsByTagName('source');
        $targetNodes = $unitNode->getElementsByTagName('target');
        $source = $sourceNodes->length > 0 ? trim((string)$sourceNodes->item(0)?->textContent) : '';
        $target = $targetNodes->length > 0 ? trim((string)$targetNodes->item(0)?->textContent) : '';

        if ($targetLanguage !== '') {
            if ($targetNodes->length === 0 || $target === '') {
                $emptyTargets++;
            }
        }

        $units[$id] = [
            'source' => $source,
            'target' => $target,
        ];
    }

    return [
        'ids' => array_values(array_unique($ids)),
        'counts' => array_count_values($ids),
        'emptyTargets' => $emptyTargets,
        'sourceLanguage' => $sourceLanguage,
        'targetLanguage' => $targetLanguage,
        'units' => $units,
    ];
}

$summary = [
    'frontend' => [],
    'backend' => [],
];
$totalEmptyTargets = 0;
$totalDuplicateIds = 0;
$extraIds = [];

foreach ($families as $familyKey => $fileName) {
    $enPath = $languageDir . '/' . $fileName;
    if (!is_file($enPath)) {
        $failures[] = 'Missing canonical English file: ' . $fileName;
        continue;
    }

    try {
        $en = parseXliff($enPath);
    } catch (Throwable $e) {
        $failures[] = $e->getMessage();
        continue;
    }

    if ($en['sourceLanguage'] !== 'en') {
        $failures[] = $fileName . ' must declare source-language=en';
    }
    if ($en['targetLanguage'] !== '') {
        $failures[] = $fileName . ' canonical English file must not declare target-language';
    }

    $enDupIds = array_keys(array_filter($en['counts'], static fn(int $count): bool => $count > 1));
    $totalDuplicateIds += count($enDupIds);
    if ($enDupIds !== []) {
        $failures[] = $fileName . ' has duplicate IDs: ' . implode(', ', $enDupIds);
    }

    $summary[$familyKey]['en'] = count($en['ids']);
    sort($en['ids']);

    foreach ($locales as $locale) {
        $localePath = $languageDir . '/' . $locale . '.' . $fileName;
        if (!is_file($localePath)) {
            $failures[] = 'Missing translated file: ' . $locale . '.' . $fileName;
            $summary[$familyKey][$locale . '_missing'] = count($en['ids']);
            continue;
        }

        try {
            $loc = parseXliff($localePath);
        } catch (Throwable $e) {
            $failures[] = $e->getMessage();
            $summary[$familyKey][$locale . '_missing'] = count($en['ids']);
            continue;
        }

        if ($loc['sourceLanguage'] !== 'en') {
            $failures[] = $locale . '.' . $fileName . ' must declare source-language=en';
        }
        if ($loc['targetLanguage'] !== $locale) {
            $failures[] = $locale . '.' . $fileName . ' must declare target-language=' . $locale;
        }

        $locDupIds = array_keys(array_filter($loc['counts'], static fn(int $count): bool => $count > 1));
        $totalDuplicateIds += count($locDupIds);
        if ($locDupIds !== []) {
            $failures[] = $locale . '.' . $fileName . ' has duplicate IDs: ' . implode(', ', $locDupIds);
        }

        $totalEmptyTargets += $loc['emptyTargets'];
        if ($loc['emptyTargets'] > 0) {
            $failures[] = $locale . '.' . $fileName . ' has ' . $loc['emptyTargets'] . ' empty target(s)';
        }

        $missing = array_values(array_diff($en['ids'], $loc['ids']));
        $extra = array_values(array_diff($loc['ids'], $en['ids']));
        $summary[$familyKey][$locale . '_missing'] = count($missing);

        if ($missing !== []) {
            $failures[] = $locale . '.' . $fileName . ' missing IDs: ' . implode(', ', $missing);
        }
        if ($extra !== []) {
            $extraIds[] = $locale . '.' . $fileName . ': ' . implode(', ', $extra);
            // Extra IDs are reported but do not fail by themselves unless empty/stale handling is required.
        }
    }
}

$issue11Ids = [
    'flexform.permissionsHelp',
    'permissions.category.general',
    'permissions.category.design',
];
foreach (
    [
        'source', 'folder', 'recursive', 'sortBy', 'sortDir', 'gap', 'layoutMode', 'maxItemsPerRow', 'maxWidth',
        'enableLightbox', 'showCaptions', 'captionAlign', 'useFalCaptions', 'enableLoadMore', 'loadMoreUseFrameStyle',
        'itemsPerPage', 'loadStep', 'captions', 'designPreset', 'frameColor', 'frameAccentColor', 'frameWidth',
        'frameStyle', 'borderRadius', 'shadow', 'backgroundColor', 'captionColor', 'applyTo', 'lbOverlay',
        'lbOverlayAlpha', 'lbNavColor', 'lbCloseColor', 'lbCaptionColor', 'lbCaptionBg', 'lbCaptionBgAlpha',
        'lbCaptionAlign', 'lbCaptionSize', 'lbCaptionStyle', 'designOverrides',
    ] as $field
) {
    $issue11Ids[] = 'permissions.hide.settings.' . $field;
}

try {
    $backendEn = parseXliff($languageDir . '/locallang_be.xlf');
    foreach ($issue11Ids as $id) {
        if (!isset($backendEn['units'][$id])) {
            $failures[] = 'Canonical English backend is missing Issue #11 ID: ' . $id;
        }
    }
    foreach ($locales as $locale) {
        $backendLoc = parseXliff($languageDir . '/' . $locale . '.locallang_be.xlf');
        foreach ($issue11Ids as $id) {
            if (!isset($backendLoc['units'][$id])) {
                $failures[] = strtoupper($locale) . ' backend is missing Issue #11 ID: ' . $id;
                continue;
            }
            if (trim($backendLoc['units'][$id]['target']) === '') {
                $failures[] = strtoupper($locale) . ' backend has empty Issue #11 target: ' . $id;
            }
        }
    }
} catch (Throwable $e) {
    $failures[] = 'Issue #11 coverage audit failed: ' . $e->getMessage();
}

if ($failures !== []) {
    fwrite(STDERR, "LANGUAGE_PARITY: FAIL\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
    }
    if ($extraIds !== []) {
        fwrite(STDERR, "EXTRA_IDS_REPORTED:\n");
        foreach ($extraIds as $extra) {
            fwrite(STDERR, $extra . "\n");
        }
    }
    exit(1);
}

fwrite(STDOUT, "LANGUAGE_PARITY: PASS\n");
fwrite(STDOUT, 'SUPPORTED_LANGUAGES=' . implode(',', $supportedLanguages) . "\n");
fwrite(STDOUT, 'FRONTEND_EN_IDS=' . ($summary['frontend']['en'] ?? 0) . "\n");
fwrite(STDOUT, 'FRONTEND_DE_MISSING=' . ($summary['frontend']['de_missing'] ?? 0) . "\n");
fwrite(STDOUT, 'FRONTEND_FR_MISSING=' . ($summary['frontend']['fr_missing'] ?? 0) . "\n");
fwrite(STDOUT, 'FRONTEND_ES_MISSING=' . ($summary['frontend']['es_missing'] ?? 0) . "\n");
fwrite(STDOUT, 'FRONTEND_RU_MISSING=' . ($summary['frontend']['ru_missing'] ?? 0) . "\n");
fwrite(STDOUT, 'BACKEND_EN_IDS=' . ($summary['backend']['en'] ?? 0) . "\n");
fwrite(STDOUT, 'BACKEND_DE_MISSING=' . ($summary['backend']['de_missing'] ?? 0) . "\n");
fwrite(STDOUT, 'BACKEND_FR_MISSING=' . ($summary['backend']['fr_missing'] ?? 0) . "\n");
fwrite(STDOUT, 'BACKEND_ES_MISSING=' . ($summary['backend']['es_missing'] ?? 0) . "\n");
fwrite(STDOUT, 'BACKEND_RU_MISSING=' . ($summary['backend']['ru_missing'] ?? 0) . "\n");
fwrite(STDOUT, 'EMPTY_TARGETS=' . $totalEmptyTargets . "\n");
fwrite(STDOUT, 'DUPLICATE_IDS=' . $totalDuplicateIds . "\n");
fwrite(STDOUT, 'ISSUE11_STRINGS=' . count($issue11Ids) . "\n");
if ($extraIds !== []) {
    fwrite(STDOUT, "EXTRA_IDS:\n");
    foreach ($extraIds as $extra) {
        fwrite(STDOUT, $extra . "\n");
    }
} else {
    fwrite(STDOUT, "EXTRA_IDS=0\n");
}
exit(0);
