<?php
declare(strict_types=1);

/**
 * Executable contract tests for Manual-source FileReference metadata resolution.
 * Run: php Build/test-manual-filereference-metadata-resolver.php
 */

require_once dirname(__DIR__) . '/Classes/Service/ManualGalleryMetadataResolver.php';

use Anatolkin\MosaicGallery\Service\ManualGalleryMetadataResolver;

$failures = [];

function assertSameValue(mixed $expected, mixed $actual, string $message, array &$failures): void
{
    if ($expected !== $actual) {
        $failures[] = $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')';
    }
}

// FileReference with no element-specific overrides (reference inherits file values).
$noOverride = [];
assertSameValue(
    'FILE DESCRIPTION',
    ManualGalleryMetadataResolver::resolveCaption('FILE DESCRIPTION', $noOverride),
    'File-only description becomes caption',
    $failures,
);
assertSameValue(
    'FILE ALT',
    ManualGalleryMetadataResolver::resolveAlt('FILE ALT', $noOverride),
    'File-only alternative becomes alt',
    $failures,
);

// FileReference overrides must win over file defaults.
assertSameValue(
    'REF DESCRIPTION',
    ManualGalleryMetadataResolver::resolveCaption('REF DESCRIPTION', $noOverride),
    'Reference description becomes caption',
    $failures,
);
assertSameValue(
    'REF ALT',
    ManualGalleryMetadataResolver::resolveAlt('REF ALT', $noOverride),
    'Reference alternative becomes alt',
    $failures,
);

// Reference title must never be consulted by the resolver itself.
assertSameValue(
    'REF DESCRIPTION',
    ManualGalleryMetadataResolver::resolveCaption('REF DESCRIPTION', $noOverride),
    'Reference title must not replace description for caption resolution',
    $failures,
);

// Mosaic custom overrides.
$customOverride = [
    'caption' => ['mode' => 'custom', 'value' => 'MOSAIC CAPTION'],
    'alt' => ['mode' => 'custom', 'value' => 'MOSAIC ALT'],
];
assertSameValue(
    'MOSAIC CAPTION',
    ManualGalleryMetadataResolver::resolveCaption('REF DESCRIPTION', $customOverride),
    'Mosaic custom caption wins',
    $failures,
);
assertSameValue(
    'MOSAIC ALT',
    ManualGalleryMetadataResolver::resolveAlt('REF ALT', $customOverride),
    'Mosaic custom alt wins',
    $failures,
);

// Return to inherit.
$inheritOverride = [
    'caption' => ['mode' => 'inherit', 'value' => ''],
    'alt' => ['mode' => 'inherit', 'value' => ''],
];
assertSameValue(
    'REF DESCRIPTION',
    ManualGalleryMetadataResolver::resolveCaption('REF DESCRIPTION', $inheritOverride),
    'Inherit caption restores reference description',
    $failures,
);
assertSameValue(
    'REF ALT',
    ManualGalleryMetadataResolver::resolveAlt('REF ALT', $inheritOverride),
    'Inherit alt restores reference alternative',
    $failures,
);

// Explicit decorative alt.
$emptyAltOverride = [
    'alt' => ['mode' => 'empty', 'value' => ''],
];
assertSameValue(
    '',
    ManualGalleryMetadataResolver::resolveAlt('REF ALT', $emptyAltOverride),
    'Mosaic empty alt mode must force empty string',
    $failures,
);

if ($failures === []) {
    fwrite(STDOUT, "Manual FileReference metadata resolver tests passed.\n");
    exit(0);
}

fwrite(STDERR, "Manual FileReference metadata resolver tests failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
