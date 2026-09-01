<?php
declare(strict_types=1);

/**
 * Executable contract tests for title-based inherited gallery metadata resolution.
 * Run: php Build/test-gallery-inherited-metadata-resolver.php
 */

require_once dirname(__DIR__) . '/Classes/Service/GalleryInheritedMetadataResolver.php';

use Anatolkin\MosaicGallery\Service\GalleryInheritedMetadataResolver;

$failures = [];

function assertSameValue(mixed $expected, mixed $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

$noOverride = [];
$customOverride = [
    'caption' => ['mode' => 'custom', 'value' => 'MOSAIC CAPTION'],
    'alt' => ['mode' => 'custom', 'value' => 'MOSAIC ALT'],
];
$inheritOverride = [
    'caption' => ['mode' => 'inherit', 'value' => ''],
    'alt' => ['mode' => 'inherit', 'value' => ''],
];
$emptyAltOverride = [
    'alt' => ['mode' => 'empty', 'value' => ''],
];

// 1. Folder: title becomes caption.
assertSameValue(
    'FILE TITLE',
    GalleryInheritedMetadataResolver::resolveCaption('FILE TITLE', $noOverride),
    'Folder caption inherit uses File Title',
    $failures,
);

// 2. Folder: description-only must not become caption.
assertSameValue(
    '',
    GalleryInheritedMetadataResolver::resolveCaption('', $noOverride),
    'Folder caption inherit stays empty when File Title is empty',
    $failures,
);
assertSameValue(
    '',
    GalleryInheritedMetadataResolver::resolveCaption('', $noOverride),
    'Folder description must not become caption implicitly',
    $failures,
);

// 3. Manual: no reference override uses File Title (passed in after TYPO3 fallback).
assertSameValue(
    'FILE TITLE',
    GalleryInheritedMetadataResolver::resolveCaption('FILE TITLE', $noOverride),
    'Manual caption inherit uses effective File Title',
    $failures,
);

// 4. Manual: reference title wins.
assertSameValue(
    'REF TITLE',
    GalleryInheritedMetadataResolver::resolveCaption('REF TITLE', $noOverride),
    'Manual caption inherit uses FileReference Title',
    $failures,
);

// 5. Manual: description-only effective title stays empty.
assertSameValue(
    '',
    GalleryInheritedMetadataResolver::resolveCaption('', $noOverride),
    'Manual caption inherit stays empty when effective Title is empty',
    $failures,
);

// 6. Alt: file alternative inherited.
assertSameValue(
    'FILE ALT',
    GalleryInheritedMetadataResolver::resolveAlt('FILE ALT', $noOverride),
    'Folder alt inherit uses File Alternative',
    $failures,
);

// 7. Manual alt: reference alternative wins.
assertSameValue(
    'REF ALT',
    GalleryInheritedMetadataResolver::resolveAlt('REF ALT', $noOverride),
    'Manual alt inherit uses FileReference Alternative',
    $failures,
);

// 8. Mosaic custom caption wins.
assertSameValue(
    'MOSAIC CAPTION',
    GalleryInheritedMetadataResolver::resolveCaption('REF TITLE', $customOverride),
    'Mosaic custom caption wins over inherited title',
    $failures,
);

// 9. Mosaic custom alt wins.
assertSameValue(
    'MOSAIC ALT',
    GalleryInheritedMetadataResolver::resolveAlt('REF ALT', $customOverride),
    'Mosaic custom alt wins over inherited alternative',
    $failures,
);

// 10. Mosaic empty alt remains empty.
assertSameValue(
    '',
    GalleryInheritedMetadataResolver::resolveAlt('REF ALT', $emptyAltOverride),
    'Mosaic empty alt mode forces empty string',
    $failures,
);

// 11. Localization: caller must pass language-specific File Title.
assertSameValue(
    'English title',
    GalleryInheritedMetadataResolver::resolveCaption('English title', $noOverride),
    'Folder localized File Title is used when provided by TYPO3 FAL overlay',
    $failures,
);
assertSameValue(
    'Deutscher Titel',
    GalleryInheritedMetadataResolver::resolveCaption('Deutscher Titel', $noOverride),
    'Folder localized File Title selection does not leak another language',
    $failures,
);

// 12. Manual localization: localized FileReference Title overrides localized File Title.
assertSameValue(
    'Localized REF TITLE',
    GalleryInheritedMetadataResolver::resolveCaption('Localized REF TITLE', $noOverride),
    'Manual localized FileReference Title is used when provided by TYPO3 FAL overlay',
    $failures,
);

// 13. Description values must not become caption (assembler passes Title only).
assertSameValue(
    '',
    GalleryInheritedMetadataResolver::resolveCaption('', $noOverride),
    'Description must not become caption when effective Title is empty',
    $failures,
);

// Return to inherit restores reference/file title.
assertSameValue(
    'REF TITLE',
    GalleryInheritedMetadataResolver::resolveCaption('REF TITLE', $inheritOverride),
    'Inherit caption restores inherited title',
    $failures,
);
assertSameValue(
    'REF ALT',
    GalleryInheritedMetadataResolver::resolveAlt('REF ALT', $inheritOverride),
    'Inherit alt restores inherited alternative',
    $failures,
);

if ($failures === []) {
    fwrite(STDOUT, "Gallery inherited metadata resolver tests passed.\n");
    exit(0);
}

fwrite(STDERR, "Gallery inherited metadata resolver tests failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
