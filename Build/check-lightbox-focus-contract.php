<?php
declare(strict_types=1);

/**
 * Structural checks for Mosaic GLightbox focus lifecycle around aria-hidden.
 * Run: php Build/check-lightbox-focus-contract.php
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

$mosaicInit = readFileOrFail($root . '/Resources/Public/Js/mosaic-init.js', $failures);
$glightbox = readFileOrFail($root . '/Resources/Public/Js/glightbox.min.js', $failures);

if ($mosaicInit !== '') {
    $normalized = preg_replace('/\s+/', ' ', $mosaicInit);
    if (!is_string($normalized)) {
        $failures[] = 'Unable to normalize mosaic-init.js for structural checks';
        $normalized = '';
    }

    if (!str_contains($normalized, 'var originatingTrigger = null')) {
        $failures[] = 'Each gallery must keep per-instance originatingTrigger state';
    }

    if (!str_contains($normalized, "container.addEventListener(\"click\", rememberGalleryTrigger, true)")) {
        $failures[] = 'Mosaic must bind a capture-phase click listener before GLightbox open';
    }

    if (!str_contains($normalized, 'trigger.blur()')) {
        $failures[] = 'Pre-open focus preparation must blur the originating trigger when it owns focus';
    }

    if (!str_contains($normalized, 'focusOpenedLightbox()')) {
        $failures[] = 'onOpen must move focus into the opened lightbox';
    }

    if (!str_contains($normalized, 'getElementById("glightbox-body")')) {
        $failures[] = 'Lightbox focus helper must target #glightbox-body';
    }

    if (!str_contains($normalized, 'querySelector(".gclose")')) {
        $failures[] = 'Lightbox focus helper must prefer .gclose when available';
    }

    if (!str_contains($normalized, 'focusElement(originatingTrigger)')) {
        $failures[] = 'onClose must restore focus to the originating trigger';
    }

    if (!str_contains($normalized, 'originatingTrigger = null')) {
        $failures[] = 'onClose must clear the stored originating trigger';
    }

    if (!str_contains($normalized, 'activateMosaicLightboxTheme(themeClass)')) {
        $failures[] = 'onOpen must preserve activateMosaicLightboxTheme()';
    }

    if (!str_contains($normalized, 'deactivateMosaicLightboxTheme(themeClass)')) {
        $failures[] = 'onClose must preserve deactivateMosaicLightboxTheme()';
    }

    if (!str_contains($normalized, 'slide_after_load')) {
        $failures[] = 'slide_after_load wrapper logic must remain';
    }

    if (!str_contains($normalized, 'ensureLightboxFrameWrappers(data && data.slideNode ? data.slideNode : document)')) {
        $failures[] = 'slide_after_load must keep ensureLightboxFrameWrappers()';
    }
}

if ($glightbox !== '' && str_contains($glightbox, 'originatingTrigger')) {
    $failures[] = 'Vendored glightbox.min.js must not contain Mosaic focus integration code';
}

if ($glightbox !== '' && str_contains($glightbox, 'focusOpenedLightbox')) {
    $failures[] = 'Vendored glightbox.min.js must not contain Mosaic lightbox focus helpers';
}

if ($failures === []) {
    fwrite(STDOUT, "Lightbox focus lifecycle contract checks passed.\n");
    exit(0);
}

fwrite(STDERR, "Lightbox focus lifecycle contract checks failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
