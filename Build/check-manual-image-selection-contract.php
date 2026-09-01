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
$metadataEditorJs = readFileOrFail($root . '/Resources/Public/JavaScript/metadata-editor.js', $failures);
$locallangBe = readFileOrFail($root . '/Resources/Private/Language/locallang_be.xlf', $failures);
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
    if (str_contains($tca, 'ManualImageSourceCondition') || preg_match(
        '/\$manualImagesField[\s\S]{0,400}displayCond/s',
        $tca,
    )) {
        $failures[] = 'B: Manual images field must not use server-side USER displayCond';
    }
    if (!str_contains($tca, "'elementBrowserEnabled' => true") || !str_contains($tca, "'fileUploadAllowed' => true")) {
        $failures[] = 'B: Manual images must enable native element browser and upload controls';
    }
    if (!preg_match("/'maxitems'\s*=>\s*200/", $tca)) {
        $failures[] = 'B: Manual images must allow multi-value relations (maxitems 200)';
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
    if (preg_match('/if\s*\(\$isManualSource\)\s*\{[^}]*metadata\.folderNotSelected/s', $metadataElement)) {
        $failures[] = 'E: Manual mode must not show folder-not-selected messaging';
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

if (is_file($root . '/Classes/Backend/Form/DisplayCondition/ManualImageSourceCondition.php')) {
    $failures[] = 'E: ManualImageSourceCondition must be removed after dropping TCA displayCond';
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
    if (!str_contains($designConfiguratorJs, 'IMAGE_SOURCE_FIELD_IDS')) {
        $failures[] = 'G: Images workspace must own source configuration fields explicitly';
    }
    $expectedSourceOrder = [
        'settings.source',
        'settings.folder',
        'settings.recursive',
        'settings.useFalCaptions',
        'settings.sortBy',
        'settings.sortDir',
    ];
    if (!preg_match('/const IMAGE_SOURCE_FIELD_IDS = \[(.*?)\];/s', $designConfiguratorJs, $sourceFieldMatch)) {
        $failures[] = 'G: Images workspace must own source configuration fields explicitly';
    } else {
        $sourceFieldBlock = $sourceFieldMatch[1];
        $sourceOrderPos = 0;
        foreach ($expectedSourceOrder as $fieldId) {
            $pos = strpos($sourceFieldBlock, "'" . $fieldId . "'");
            if ($pos === false) {
                $failures[] = 'G: Images workspace must reference field ' . $fieldId;
                continue;
            }
            if ($pos < $sourceOrderPos) {
                $failures[] = 'A: IMAGE_SOURCE_FIELD_IDS must be ordered source, folder, recursive, useFalCaptions, sortBy, sortDir';
                break;
            }
            $sourceOrderPos = $pos + 1;
        }
    }
    if (!str_contains($designConfiguratorJs, 'findManualImagesSection')) {
        $failures[] = 'C: design-configurator.js must expose a dedicated manual-section resolver';
    }
    if (!preg_match(
        '/findManualImagesSection[\s\S]{0,800}data-formengine-input-name[\s\S]{0,800}\.form-section/s',
        $designConfiguratorJs,
    )) {
        $failures[] = 'C: Manual-section resolver must locate native FormEngine wrapper via stable input-name markers';
    }
    if (!preg_match(
        '/findManualImagesSection[\s\S]{0,1200}insertBefore\(manualImagesSection,\s*metadataSection\)/s',
        $designConfiguratorJs,
    ) && !preg_match(
        '/manualImagesSection[\s\S]{0,400}insertBefore\(manualImagesSection,\s*metadataSection\)/s',
        $designConfiguratorJs,
    )) {
        $failures[] = 'E: Manual relation must be placed before metadata section inside Images workspace';
    }
    if (!preg_match(
        '/imagesHeader\.insertAdjacentElement\(\s*[\'"]afterend[\'"]\s*,\s*manualImagesSection\s*\)/s',
        $designConfiguratorJs,
    ) && !preg_match(
        '/insertBefore\(manualImagesSection,\s*metadataSection\)/s',
        $designConfiguratorJs,
    )) {
        $failures[] = 'D: Manual relation must be reparented into imagesSheet';
    }
    if (!str_contains($designConfiguratorJs, 'removeEmptyFormSectionShell')) {
        $failures[] = 'L: Manual relation reparent must remove outer empty FormEngine footprint';
    }
    if (!str_contains($designConfiguratorJs, 'LAYOUT_SETTINGS_FIELD_IDS')
        || !str_contains($designConfiguratorJs, 'settings.layoutMode')
    ) {
        $failures[] = 'G: Layout workspace must retain presentation settings separately from Images source';
    }
    if (!str_contains($designConfiguratorJs, 'mosaic-images-header')
        || !preg_match('/moveFromLayout[\s\S]*imagesSourceRow/s', $designConfiguratorJs)
    ) {
        $failures[] = 'G: Source row must be mounted in Images workspace, not Layout';
    }
    if (!str_contains($designConfiguratorJs, 'mountContinueToImages')
        || !str_contains($designConfiguratorJs, 'data-mosaic-continue-to-images')
    ) {
        $failures[] = 'G: Layout workspace must expose Continue to Images navigation';
    }
    if (!str_contains($designConfiguratorJs, 'applyManualFieldVisibility')
        || !preg_match('/manualSection\.hidden = source !== SOURCE_MANUAL/s', $designConfiguratorJs)
    ) {
        $failures[] = 'G: JavaScript must own manual relation visibility';
    }
    if (!preg_match('/applyManualFieldVisibility[\s\S]{0,200}findManualImagesSection/s', $designConfiguratorJs)) {
        $failures[] = 'F/G: Manual relation visibility must resolve via findManualImagesSection';
    }
    if (!str_contains($designConfiguratorJs, 'applyLegacyCaptionsVisibility')) {
        $failures[] = 'G: Manual mode must hide legacy Quick captions disclosure';
    }
    if (str_contains($designConfiguratorJs, 'scheduleImagesWorkspaceActivation')
        || str_contains($designConfiguratorJs, 'ensureManualSourceHint')
        || str_contains($designConfiguratorJs, 'manualSourceHint')
    ) {
        $failures[] = 'G: Obsolete manual redirect/hint machinery must be removed';
    }
    if (!str_contains($designConfiguratorJs, 'activateImagesWorkspaceTab')) {
        $failures[] = 'G: Images tab activation helper must remain for Continue to Images';
    }
    if (!str_contains($designConfiguratorJs, 'tx_anatolkinmosaicgallery_images')) {
        $failures[] = 'G: Native manual TCA relation must remain wired in the Images workspace';
    }
    if (preg_match('/customFileBrowser|custom-file-browser|buildManualFileSelector/i', $designConfiguratorJs)) {
        $failures[] = 'H: Manual source must not introduce a custom file browser';
    }
    if (!str_contains($designConfiguratorJs, 'mosaic:sourcechange')) {
        $failures[] = 'M: design-configurator must dispatch mosaic:sourcechange for live metadata source switching';
    }
    if (!str_contains($designConfiguratorJs, 'data-mosaic-source-mode') || !str_contains($designConfiguratorJs, 'imagesSourceRow')) {
        $failures[] = 'N: Images source row must expose explicit data-mosaic-source-mode state';
    }
    if (!str_contains($designConfiguratorJs, 'mosaic:workspaceconsolidated')) {
        $failures[] = 'N: design-configurator must dispatch mosaic:workspaceconsolidated after workspace consolidation';
    }
    if (!preg_match('/addCompactHelp\(metadataFallback\)/s', $designConfiguratorJs)) {
        $failures[] = 'I: Metadata fallback must use compact-help treatment';
    }
    if (!preg_match(
        '/mountSourcePair[\s\S]{0,400}settings\.recursive[\s\S]{0,200}settings\.useFalCaptions/s',
        $designConfiguratorJs,
    )) {
        $failures[] = 'K: Responsive contract must pair recursive and useFalCaptions source controls';
    }
    if (preg_match(
        '/\.form-section\[data-id="tx_anatolkinmosaicgallery_images"\]/',
        $designConfiguratorJs,
    ) && !str_contains($designConfiguratorJs, 'findManualImagesSection')) {
        $failures[] = 'L: Manual relation must not rely solely on FlexForm-only data-id lookup';
    }
}

if ($metadataElement !== '') {
    if (!str_contains($metadataElement, 'ManualImageProvider')) {
        $failures[] = 'M: MetadataOverridesElement must keep ManualImageProvider for saved records';
    }
    if (!preg_match('/if\s*\(\$contentUid\s*>\s*0\)/', $metadataElement)) {
        $failures[] = 'M: ManualImageProvider must remain gated to persisted content UID > 0';
    }
    if (!str_contains($metadataElement, 'data-mosaic-metadata-item-template')) {
        $failures[] = 'M: Metadata workspace must expose reusable metadata item template';
    }
    if (!str_contains($metadataElement, 'data-mosaic-manual-live-scaffold')) {
        $failures[] = 'M: Manual live scaffold must exist for unsaved FormEngine bridging';
    }
}

if ($metadataEditorJs !== '') {
    if (!str_contains($metadataEditorJs, 'readManualFileReferences')) {
        $failures[] = 'M: metadata-editor must read native manual FileReferences from FormEngine DOM';
    }
    if (!preg_match('/\[uid_local\]/', $metadataEditorJs)) {
        $failures[] = 'M: Live manual reader must key metadata by original sys_file uid via uid_local';
    }
    if (!str_contains($metadataEditorJs, 'MutationObserver')) {
        $failures[] = 'M: Live manual sync must observe native relation container mutations';
    }
    if (!str_contains($metadataEditorJs, 'syncLiveManualMetadata')) {
        $failures[] = 'M: Live add/remove/reorder sync must rebuild metadata rows from native relations';
    }
    if (!str_contains($metadataEditorJs, 'mosaic:sourcechange')) {
        $failures[] = 'M: metadata-editor must react to live source changes';
    }
    if (str_contains($metadataEditorJs, 'GalleryImageSorter') || str_contains($metadataEditorJs, '.sort(')) {
        $failures[] = 'M: Manual live metadata must preserve native relation order without sorting';
    }
    if (preg_match('/auto\s*save|autosave|DataHandler|process_datamap/i', $metadataEditorJs)) {
        $failures[] = 'M: Live manual bridge must not auto-save content elements';
    }
    if (preg_match('/customFileBrowser|custom-file-browser|buildManualFileSelector/i', $metadataEditorJs)) {
        $failures[] = 'M: Live manual bridge must not introduce a custom file browser';
    }
    if (!str_contains($metadataEditorJs, 'buildMetadataRow')) {
        $failures[] = 'M: Live metadata rows must clone the shared metadata item template';
    }
    if (!str_contains($metadataEditorJs, 'persistVisibleRows')) {
        $failures[] = 'M: Live rebuilds must preserve stored caption/alt overrides keyed by file UID';
    }
    if (!str_contains($metadataEditorJs, 'mosaicMetadataInitialized')) {
        $failures[] = 'N: metadata-editor must mark initialized editors with data-mosaic-metadata-initialized';
    }
    if (!str_contains($metadataEditorJs, 'refreshEditor') || !str_contains($metadataEditorJs, 'bootstrapForm')) {
        $failures[] = 'N: metadata-editor must bootstrap editors via lifecycle-aware refresh/bootstrap helpers';
    }
    if (!str_contains($metadataEditorJs, 'setupFormBootstrapObserver') || !str_contains($metadataEditorJs, 'findManualFilesContainer')) {
        $failures[] = 'N: metadata-editor must observe FormEngine form roots and native files containers';
    }
    if (!str_contains($metadataEditorJs, 'mosaic:workspaceconsolidated')) {
        $failures[] = 'N: metadata-editor must refresh after workspace consolidation events';
    }
    if (!str_contains($metadataEditorJs, 'AbortController') || !str_contains($metadataEditorJs, 'editorState')) {
        $failures[] = 'N: metadata-editor initialization must be idempotent via bounded lifecycle state';
    }
    if (!str_contains($metadataEditorJs, 'bindRecordsObserver')) {
        $failures[] = 'N: metadata-editor must rebind records observers when _records appears or is replaced';
    }
    if (!preg_match('/const getViewHost = \(editor\) => editor;/', $metadataEditorJs)) {
        $failures[] = 'P: getViewHost must resolve canonical metadata editor root as view-state owner';
    }
    if (!preg_match('/getViewHost\(editor\)[\s\S]{0,120}dataset\.mosaicImagesView/s', $metadataEditorJs)) {
        $failures[] = 'P: applyView must write data-mosaic-images-view to the canonical editor root';
    }
    if (!preg_match('/data-mosaic-metadata-editor[^>]*data-mosaic-images-view="table"/s', $metadataElement)) {
        $failures[] = 'P: Metadata editor root must own initial data-mosaic-images-view before JS init';
    }
    if (preg_match('/class="mosaic-metadata-workspace"[^>]*data-mosaic-images-view/s', $metadataElement)) {
        $failures[] = 'P: mosaic-metadata-workspace must not compete as view-state owner';
    }
    $formLayoutCss = readFileOrFail($root . '/Resources/Public/Backend/Css/form-layout.css', $failures);
    if ($formLayoutCss !== '' && !preg_match(
        '/\[data-mosaic-images-view="grid"\][\s\S]{0,120}\.mosaic-metadata-items/s',
        $formLayoutCss,
    )) {
        $failures[] = 'P: CSS view selectors must target metadata descendants of canonical editor root';
    }
    if (!str_contains($metadataEditorJs, 'data-mosaic-metadata-empty-manual') && !str_contains($metadataEditorJs, 'updateEmptyStates')) {
        $failures[] = 'N: Live manual empty-state handling must differ from folder guidance';
    }
    if (!str_contains($metadataEditorJs, 'parseFileUidLocalValue')) {
        $failures[] = 'O: metadata-editor must normalize TYPO3 uid_local entity tokens via parseFileUidLocalValue';
    }
    if (!preg_match('/readUidLocal[\s\S]{0,400}querySelectorAll/s', $metadataEditorJs)) {
        $failures[] = 'O: readUidLocal must inspect all uid_local candidates in DOM order';
    }
    if (!str_contains($metadataEditorJs, 'findSourceControl')) {
        $failures[] = 'O: resolveLiveSource must prefer live FormEngine source control via findSourceControl';
    }
    if (!preg_match('/resolveLiveSource[\s\S]{0,500}findSourceControl/s', $metadataEditorJs)) {
        $failures[] = 'O: resolveLiveSource must consult live source control before initial server snapshot';
    }
    if (!preg_match('/refreshEditor[\s\S]{0,1200}applySourceMode\(editor\)[\s\S]{0,200}observeManualRelations\(editor\)/s', $metadataEditorJs)) {
        $failures[] = 'O: refreshEditor must apply source mode before manual relation observers';
    }
    if (!str_contains($metadataEditorJs, 'dataset.mosaicLiveSource')) {
        $failures[] = 'O: applySourceMode must persist resolved live source on editor dataset';
    }
}

$uidParserTest = $root . '/Build/test-manual-file-uid-parser.js';
if (!is_file($uidParserTest)) {
    $failures[] = 'O: Missing executable uid_local parser test: Build/test-manual-file-uid-parser.js';
} else {
    $parserOutput = [];
    $parserExit = 0;
    exec('node ' . escapeshellarg($uidParserTest) . ' 2>&1', $parserOutput, $parserExit);
    if ($parserExit !== 0) {
        $failures[] = 'O: uid_local parser executable test failed: ' . implode("\n", $parserOutput);
    }
}

if ($locallangBe !== '') {
    if (str_contains($locallangBe, 'flexform.source.manual.hint')) {
        $failures[] = 'G: Obsolete manual-source hint label must be removed';
    }
    if (!str_contains($locallangBe, 'Add images to edit image metadata.')) {
        $failures[] = 'G: Manual metadata empty state must instruct add-images without save requirement';
    }
    if (!str_contains($locallangBe, 'workspace.continueToImages')) {
        $failures[] = 'G: Continue to Images label must exist';
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
