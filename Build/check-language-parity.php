<?php
declare(strict_types=1);

/**
 * Localization parity gate for declared interface languages (0.6.2+).
 * Run: php Build/check-language-parity.php
 */

$root = dirname(__DIR__);
$languageDir = $root . '/Resources/Private/Language';
$failures = [];

$families = [
    'frontend' => 'locallang.xlf',
    'backend' => 'locallang_be.xlf',
];

$locales = [
    'de', 'fr', 'es', 'ru',
    'ar', 'he', 'fa',
    'it', 'nl', 'pl', 'pt', 'pt_BR', 'uk', 'cs', 'sk', 'da', 'sv', 'no', 'fi',
    'tr', 'ro', 'hu', 'el', 'bg', 'hr', 'sr',
    'ja', 'ko', 'zh', 'zh_CN', 'hi', 'vi', 'th', 'ka',
];
$supportedLanguages = array_merge(['en'], $locales);
$rtlLocales = ['ar', 'he', 'fa'];

$identicalAllowlistExact = [
    'Anatolkin Mosaic Gallery',
    'Anatolkin Mosaic Gallery (Assets & Masonry)',
    'TYPO3',
    'GLightbox',
    'FileReference',
    'Fileadmin',
    'fileadmin',
    'Bootstrap Native',
    'Masonry',
    'Lightbox',
    'Mosaic',
    'Module Permissions',
    'Custom module options',
];

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

/**
 * @return list<string>
 */
function extractPlaceholders(string $text): array
{
    preg_match_all('/%(?:\d+\$)?[sdifFucoxXeEgG]/', $text, $matches);
    $found = $matches[0] ?? [];
    sort($found);

    return $found;
}

/**
 * @param array<string, array{source: string, target: string}> $units
 * @param list<string> $allowExact
 * @return list<string>
 */
function findSuspiciousIdenticalTargets(array $units, array $allowExact): array
{
    $suspicious = [];
    foreach ($units as $id => $pair) {
        $source = $pair['source'];
        $target = $pair['target'];
        if ($source === '' || $source !== $target) {
            continue;
        }
        if (in_array($source, $allowExact, true)) {
            continue;
        }
        // Short technical tokens / single words that are commonly shared.
        if (preg_match('/^[A-Za-z0-9 ._\/\-]{1,20}$/', $source) === 1
            && preg_match('/^(TYPO3|GLightbox|FileReference|Fileadmin|fileadmin|Masonry|Lightbox|Mosaic|Gallery|Bootstrap Native)$/i', $source) === 1
        ) {
            continue;
        }
        // Pure numbers / punctuation-only.
        if (preg_match('/^[\d\s\p{P}]+$/u', $source) === 1) {
            continue;
        }
        $suspicious[] = $id . '=' . $source;
    }

    return $suspicious;
}

/**
 * Detect unexpected Unicode bidi control characters.
 */
function containsUnexpectedBidiControls(string $text): bool
{
    return preg_match('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $text) === 1;
}

$summary = [
    'frontend' => [],
    'backend' => [],
];
$totalEmptyTargets = 0;
$totalDuplicateIds = 0;
$totalMissingIds = 0;
$totalExtraIds = 0;
$totalSourceMismatches = 0;
$totalPlaceholderMismatches = 0;
$totalTargetLanguageMismatches = 0;
$totalIdenticalSourceTarget = 0;
$extraIds = [];
$suspiciousIdentical = [];
$rtlParityPass = true;

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
            $totalMissingIds += count($en['ids']);
            if (in_array($locale, $rtlLocales, true)) {
                $rtlParityPass = false;
            }
            continue;
        }

        try {
            $loc = parseXliff($localePath);
        } catch (Throwable $e) {
            $failures[] = $e->getMessage();
            $summary[$familyKey][$locale . '_missing'] = count($en['ids']);
            $totalMissingIds += count($en['ids']);
            if (in_array($locale, $rtlLocales, true)) {
                $rtlParityPass = false;
            }
            continue;
        }

        if ($loc['sourceLanguage'] !== 'en') {
            $failures[] = $locale . '.' . $fileName . ' must declare source-language=en';
        }
        if ($loc['targetLanguage'] !== $locale) {
            $totalTargetLanguageMismatches++;
            $failures[] = $locale . '.' . $fileName . ' must declare target-language=' . $locale
                . ' (got ' . $loc['targetLanguage'] . ')';
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
        $totalMissingIds += count($missing);
        $totalExtraIds += count($extra);

        if ($missing !== []) {
            $failures[] = $locale . '.' . $fileName . ' missing IDs count=' . count($missing);
            if (in_array($locale, $rtlLocales, true)) {
                $rtlParityPass = false;
            }
        }
        if ($extra !== []) {
            $extraIds[] = $locale . '.' . $fileName . ': ' . implode(', ', $extra);
            $failures[] = $locale . '.' . $fileName . ' has EXTRA IDs count=' . count($extra);
        }

        foreach ($en['units'] as $id => $enPair) {
            if (!isset($loc['units'][$id])) {
                continue;
            }
            $locPair = $loc['units'][$id];
            if ($locPair['source'] !== $enPair['source']) {
                $totalSourceMismatches++;
                $failures[] = $locale . '.' . $fileName . ' source mismatch for ' . $id;
            }
            $enPlaceholders = extractPlaceholders($enPair['source']);
            $locPlaceholders = extractPlaceholders($locPair['target']);
            if ($enPlaceholders !== $locPlaceholders) {
                $totalPlaceholderMismatches++;
                $failures[] = $locale . '.' . $fileName . ' placeholder mismatch for ' . $id;
            }
            if (containsUnexpectedBidiControls($locPair['target'])) {
                $failures[] = $locale . '.' . $fileName . ' unexpected bidi controls in ' . $id;
            }
            if ($locPair['source'] !== '' && $locPair['source'] === $locPair['target']) {
                $totalIdenticalSourceTarget++;
            }
        }

        $suspicious = findSuspiciousIdenticalTargets($loc['units'], $identicalAllowlistExact);
        foreach ($suspicious as $item) {
            $suspiciousIdentical[] = $locale . '.' . $fileName . ':' . $item;
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

$issue11Coverage = [];
try {
    $backendEn = parseXliff($languageDir . '/locallang_be.xlf');
    foreach ($issue11Ids as $id) {
        if (!isset($backendEn['units'][$id])) {
            $failures[] = 'Canonical English backend is missing Issue #11 ID: ' . $id;
        }
    }
    foreach ($locales as $locale) {
        $backendLoc = parseXliff($languageDir . '/' . $locale . '.locallang_be.xlf');
        $covered = 0;
        foreach ($issue11Ids as $id) {
            if (!isset($backendLoc['units'][$id])) {
                $failures[] = strtoupper($locale) . ' backend is missing Issue #11 ID: ' . $id;
                continue;
            }
            if (trim($backendLoc['units'][$id]['target']) === '') {
                $failures[] = strtoupper($locale) . ' backend has empty Issue #11 target: ' . $id;
                continue;
            }
            $covered++;
        }
        $issue11Coverage[$locale] = $covered;
        if ($covered !== count($issue11Ids) && in_array($locale, $rtlLocales, true)) {
            $rtlParityPass = false;
        }
    }
} catch (Throwable $e) {
    $failures[] = 'Issue #11 coverage audit failed: ' . $e->getMessage();
    $rtlParityPass = false;
}

if ($totalExtraIds > 0) {
    // EXTRA_IDS already counted as failures above.
}

if ($failures !== []) {
    fwrite(STDERR, "LANGUAGE_PARITY: FAIL\n");
    $shown = 0;
    foreach ($failures as $failure) {
        fwrite(STDERR, $failure . "\n");
        $shown++;
        if ($shown >= 80) {
            fwrite(STDERR, '... truncated, total failures=' . count($failures) . "\n");
            break;
        }
    }
    exit(1);
}

fwrite(STDOUT, "LANGUAGE_PARITY: PASS\n");
fwrite(STDOUT, 'SUPPORTED_LANGUAGE_COUNT=' . count($supportedLanguages) . "\n");
fwrite(STDOUT, 'SUPPORTED_LANGUAGES=' . implode(',', $supportedLanguages) . "\n");
fwrite(STDOUT, 'FRONTEND_EN_IDS=' . ($summary['frontend']['en'] ?? 0) . "\n");
fwrite(STDOUT, 'BACKEND_EN_IDS=' . ($summary['backend']['en'] ?? 0) . "\n");
fwrite(STDOUT, 'MISSING_IDS=' . $totalMissingIds . "\n");
fwrite(STDOUT, 'EXTRA_IDS=' . $totalExtraIds . "\n");
fwrite(STDOUT, 'EMPTY_TARGETS=' . $totalEmptyTargets . "\n");
fwrite(STDOUT, 'DUPLICATE_IDS=' . $totalDuplicateIds . "\n");
fwrite(STDOUT, 'SOURCE_TEXT_MISMATCHES=' . $totalSourceMismatches . "\n");
fwrite(STDOUT, 'PLACEHOLDER_MISMATCHES=' . $totalPlaceholderMismatches . "\n");
fwrite(STDOUT, 'TARGET_LANGUAGE_MISMATCHES=' . $totalTargetLanguageMismatches . "\n");
fwrite(STDOUT, 'IDENTICAL_SOURCE_TARGET=' . $totalIdenticalSourceTarget . "\n");
fwrite(STDOUT, 'ISSUE11_STRINGS=' . count($issue11Ids) . "\n");
fwrite(STDOUT, 'ISSUE11_STRINGS_PER_LOCALE=' . count($issue11Ids) . "\n");
fwrite(STDOUT, 'RTL_LANGUAGES=' . implode(',', $rtlLocales) . "\n");
fwrite(STDOUT, 'RTL_TRANSLATION_PARITY=' . ($rtlParityPass ? 'PASS' : 'FAIL') . "\n");
if ($suspiciousIdentical !== []) {
    fwrite(STDOUT, 'SUSPICIOUS_IDENTICAL_SOURCE_TARGET=' . count($suspiciousIdentical) . "\n");
    $shown = 0;
    foreach ($suspiciousIdentical as $item) {
        fwrite(STDOUT, 'SUSPICIOUS: ' . $item . "\n");
        $shown++;
        if ($shown >= 40) {
            fwrite(STDOUT, "SUSPICIOUS: ... truncated\n");
            break;
        }
    }
} else {
    fwrite(STDOUT, "SUSPICIOUS_IDENTICAL_SOURCE_TARGET=0\n");
}
exit(0);
