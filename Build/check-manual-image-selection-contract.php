<?php
declare(strict_types=1);

/**
 * Structural checks for Issue #3 manual image selection contract.
 * Run: php Build/check-manual-image-selection-contract.php
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

$flexForm = readFileOrFail($root . '/Configuration/FlexForms/MosaicGallery.xml', $failures);
$tca = readFileOrFail($root . '/Configuration/TCA/Overrides/tt_content.php', $failures);
$controller = readFileOrFail($root . '/Classes/Controller/GalleryController.php', $failures);
$manualProvider = readFileOrFail($root . '/Classes/Service/ManualImageProvider.php', $failures);
$itemAssembler = readFileOrFail($root . '/Classes/Service/GalleryItemAssembler.php', $failures);
$dimensions = readFileOrFail($root . '/Classes/Service/GalleryImageDimensionsResolver.php', $failures);
$metadataElement = readFileOrFail($root . '/Classes/Backend/Form/Element/MetadataOverridesElement.php', $failures);
$displayCond = readFileOrFail(
    $root . '/Classes/Backend/Form/DisplayCondition/ManualImageSourceCondition.php',
    $failures,
);
$template = readFileOrFail($root . '/Resources/Private/Templates/Gallery/List.html', $failures);
$creationDefaults = readFileOrFail(
    $root . '/Classes/Service/MosaicGalleryCreationDefaultsDefinition.php',
    $failures,
);
$folderProvider = readFileOrFail($root . '/Classes/Service/FolderImageProvider.php', $failures);

// A. FlexForm
if ($flexForm !== '') {
    if (!preg_match('/<value>manual<\/value>/', $flexForm)) {
        $failures[] = 'A: FlexForm must define source value manual';
    }
    if (!preg_match('/<default>folder<\/default>/', $flexForm)) {
        $failures[] = 'A: FlexForm source default must remain folder';
    }
    if (!preg_match('/<onChange>reload<\/onChange>/', $flexForm)) {
        $failures[] = 'A: FlexForm source must reload on change';
    }
    foreach (['settings.recursive', 'settings.sortBy', 'settings.sortDir'] as $field) {
        if (!str_contains($flexForm, '<' . $field . '>') || !str_contains($flexForm, 'FIELD:settings.source:=:folder')) {
            $failures[] = 'A: FlexForm folder-only control must be conditional: ' . $field;
        }
    }
    if (!str_contains($flexForm, 'settings.captions') || !preg_match(
        '/settings\.captions[\s\S]*displayCond[\s\S]*FIELD:settings\.source:=:folder/',
        $flexForm,
    )) {
        $failures[] = 'A: Legacy positional captions must be hidden in manual mode via displayCond';
    }
}

// B. TCA
if ($tca !== '') {
    if (!str_contains($tca, "'tx_anatolkinmosaicgallery_images'") && !str_contains($tca, '$manualImagesField')) {
        $failures[] = 'B: TCA must define tx_anatolkinmosaicgallery_images';
    }
    if (!str_contains($tca, "'type' => 'file'") && !str_contains($tca, "'type'=>'file'")) {
        $failures[] = 'B: Manual images field must use type=file';
    }
    if (!str_contains($tca, "'allowed' => 'common-image-types'")) {
        $failures[] = 'B: Manual images field must allow common-image-types';
    }
    if (!str_contains($tca, "'useSortable' => true")) {
        $failures[] = 'B: Manual images relation must be sortable';
    }
    if (!str_contains($tca, "'allowLanguageSynchronization' => true")) {
        $failures[] = 'B: Manual images must allow language synchronization';
    }
    if (!str_contains($tca, 'ManualImageSourceCondition') || !str_contains($tca, 'displayCond')) {
        $failures[] = 'B: Manual images field must use source-aware displayCond';
    }
}

// C. Runtime
if ($controller !== '') {
    if (!str_contains($controller, 'SOURCE_MANUAL') && !str_contains($controller, "'manual'")) {
        $failures[] = 'C: GalleryController must support manual source branch';
    }
    if (!str_contains($controller, 'assembleFromFileReferences')) {
        $failures[] = 'C: GalleryController manual path must assemble from file references';
    }
    if (preg_match('/SOURCE_MANUAL[\s\S]*GalleryImageSorter/s', $controller)) {
        $failures[] = 'C: Manual source must not apply GalleryImageSorter';
    }
    if (preg_match('/SOURCE_MANUAL[\s\S]*shuffle\s*\(/s', $controller)) {
        $failures[] = 'C: Manual source must not shuffle references';
    }
}

if ($manualProvider !== '') {
    if (!str_contains($manualProvider, 'findByRelation')) {
        $failures[] = 'C: ManualImageProvider must use FileRepository::findByRelation';
    }
    if (!str_contains($manualProvider, 'tx_anatolkinmosaicgallery_images')) {
        $failures[] = 'C: ManualImageProvider must use canonical field name';
    }
}

if ($itemAssembler !== '') {
    if (!str_contains($itemAssembler, 'metadataOverrides[(string)$file->getUid()]')) {
        $failures[] = 'C: Metadata overrides must remain keyed by original sys_file.uid';
    }
    if (!str_contains($itemAssembler, "'renderFile'")) {
        $failures[] = 'C: Gallery items must expose renderFile';
    }
}

// D. Rendering
if ($template !== '') {
    if (!str_contains($template, 'it.renderFile')) {
        $failures[] = 'D: Built-in template must use it.renderFile';
    }
    if (str_contains($template, 'it.file.publicUrl')) {
        $failures[] = 'D: Built-in template must not use raw it.file.publicUrl for lightbox';
    }
    if (!preg_match('/f:uri\.image\(image:\s*it\.renderFile/', $template)) {
        $failures[] = 'D: Preview and lightbox must generate URIs from it.renderFile';
    }
}

if ($dimensions !== '') {
    if (!str_contains($dimensions, 'CropVariantCollection')) {
        $failures[] = 'D: Crop-aware dimensions must use Core CropVariantCollection';
    }
}

// E. Metadata workspace
if ($metadataElement !== '') {
    if (!str_contains($metadataElement, 'GalleryFlexFormSourceReader')) {
        $failures[] = 'E: Metadata workspace must read gallery source from flexform';
    }
    if (!str_contains($metadataElement, 'ManualImageProvider')) {
        $failures[] = 'E: Metadata workspace must use ManualImageProvider in manual mode';
    }
    if (!str_contains($metadataElement, 'metadata.noManualImages')) {
        $failures[] = 'E: Metadata workspace must define manual-mode empty state label';
    }
    $manualBlockStart = strpos($metadataElement, 'if ($isManualSource) {');
    $folderBranchStart = strpos($metadataElement, "} elseif (\$folder !== '') {");
    $sorterPos = strpos($metadataElement, 'GalleryImageSorter::class');
    if ($manualBlockStart !== false && $folderBranchStart !== false && $sorterPos !== false
        && $sorterPos > $manualBlockStart && $sorterPos < $folderBranchStart
    ) {
        $failures[] = 'E: Manual metadata list must not apply GalleryImageSorter';
    }
    if (!str_contains($metadataElement, 'isManualSource') || !str_contains($metadataElement, 'renderConversionUi')) {
        $failures[] = 'E: Metadata workspace must disable legacy conversion UI in manual mode';
    }
}

if ($displayCond !== '') {
    if (!str_contains($displayCond, 'SOURCE_MANUAL') || !str_contains($displayCond, 'pi_flexform')) {
        $failures[] = 'E: ManualImageSourceCondition must read pi_flexform for manual source';
    }
}

// F. Regression
if ($flexForm !== '' && !str_contains($flexForm, '<value>folder</value>')) {
    $failures[] = 'F: Folder source option must remain present';
}
if ($creationDefaults !== '') {
    if (!str_contains($creationDefaults, "'folder'") || !str_contains($creationDefaults, "'manual'")) {
        $failures[] = 'F: Creation defaults source allowlist must include folder and manual';
    }
}
if ($folderProvider === '') {
    $failures[] = 'F: FolderImageProvider must remain present';
}

$sourceReaderTest = $root . '/Build/test-flexform-source-reader.php';
if (!is_file($sourceReaderTest)) {
    $failures[] = 'F: Executable FormEngine-shape regression fixture must exist at Build/test-flexform-source-reader.php';
} else {
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($sourceReaderTest);
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        $failures[] = 'F: FlexForm source reader regression fixture failed (exit ' . $exitCode . ')';
    }
}

$sourceReader = readFileOrFail($root . '/Classes/Service/GalleryFlexFormSourceReader.php', $failures);
if ($sourceReader !== '') {
    if (preg_match('/\(string\)\(\$settings\[/', $sourceReader)) {
        $failures[] = 'F: GalleryFlexFormSourceReader must not cast settings arrays directly to string';
    }
    if (!str_contains($sourceReader, 'private function stringValue(')
        || !str_contains($sourceReader, 'private function boolValue(')
    ) {
        $failures[] = 'F: GalleryFlexFormSourceReader must normalize settings via bounded stringValue/boolValue helpers';
    }
}

$designConfiguratorJs = readFileOrFail($root . '/Resources/Public/JavaScript/design-configurator.js', $failures);
if ($designConfiguratorJs !== '') {
    if (!str_contains($designConfiguratorJs, 'SOURCE_MANUAL') || !str_contains($designConfiguratorJs, 'SOURCE_FOLDER')) {
        $failures[] = 'G: design-configurator must recognize manual and folder source modes';
    }
    foreach (['settings.folder', 'settings.recursive', 'settings.sortBy', 'settings.sortDir'] as $fieldId) {
        if (!str_contains($designConfiguratorJs, $fieldId)) {
            $failures[] = 'G: design-configurator must declare folder-only field ' . $fieldId;
        }
    }
    if (!str_contains($designConfiguratorJs, 'FOLDER_ONLY_FIELD_IDS')) {
        $failures[] = 'G: design-configurator must use an explicit folder-only field inventory';
    }
    if (!str_contains($designConfiguratorJs, 'applyFolderOnlyVisibility')
        || !str_contains($designConfiguratorJs, 'section.hidden = isManual')
    ) {
        $failures[] = 'G: Manual source must hide folder-only controls without destroying them';
    }
    if (!str_contains($designConfiguratorJs, 'activateImagesWorkspaceTab')
        || !str_contains($designConfiguratorJs, 'scheduleImagesWorkspaceActivation')
    ) {
        $failures[] = 'G: Manual source must activate the Mosaic Images workspace tab';
    }
    if (!str_contains($designConfiguratorJs, 'tx_anatolkinmosaicgallery_images')) {
        $failures[] = 'G: Native manual TCA relation must remain wired in the Images workspace';
    }
    if (preg_match('/customFileBrowser|custom-file-browser|buildManualFileSelector/i', $designConfiguratorJs)) {
        $failures[] = 'G: Manual source must not introduce a custom file browser';
    }
}

if ($failures === []) {
    fwrite(STDOUT, "Manual image selection contract checks passed.\n");
    exit(0);
}

fwrite(STDERR, "Manual image selection contract checks failed:\n");
foreach ($failures as $failure) {
    fwrite(STDERR, '- ' . $failure . "\n");
}
exit(1);
