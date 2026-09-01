<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Form\Element;

use Anatolkin\MosaicGallery\Service\FolderImageProvider;
use Anatolkin\MosaicGallery\Service\GalleryFlexFormSourceReader;
use Anatolkin\MosaicGallery\Service\GalleryImageSorter;
use Anatolkin\MosaicGallery\Service\ManualImageProvider;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\StringUtility;

final class MetadataOverridesElement extends AbstractFormElement
{
    private const LANGUAGE_FILE = 'LLL:EXT:anatolkin_mosaic_gallery/Resources/Private/Language/locallang_be.xlf:';

    public function render(): array
    {
        $resultArray = $this->initializeResultArray();
        $parameterArray = $this->data['parameterArray'];
        $fieldId = StringUtility::getUniqueId('formengine-input-');
        $fieldName = (string)$parameterArray['itemFormElName'];
        $storedDocument = $this->decodeDocument((string)($parameterArray['itemFormElValue'] ?? ''));
        $gallerySettings = GeneralUtility::makeInstance(GalleryFlexFormSourceReader::class)
            ->readSettings($this->data['databaseRow']['pi_flexform'] ?? '');
        $source = $gallerySettings['source'];
        $folder = $gallerySettings['folder'];
        $recursive = $gallerySettings['recursive'];
        $sortBy = $gallerySettings['sortBy'];
        $sortDir = $gallerySettings['sortDir'];
        $legacyCaptions = $gallerySettings['captions'];
        $useFalCaptions = $gallerySettings['useFalCaptions'];
        $isManualSource = $source === GalleryFlexFormSourceReader::SOURCE_MANUAL;
        $legacyCaptionLines = $this->splitLegacyCaptionLines($legacyCaptions);
        $legacyCaptionsConverted = ($storedDocument['legacyCaptionsConverted'] ?? false) === true;
        $languageContext = $this->resolveLanguageContext();
        $contentUid = (int)$this->scalarValue($this->data['databaseRow']['uid'] ?? 0);

        $images = [];
        $folderError = false;
        if ($isManualSource) {
            if ($contentUid > 0) {
                $fileReferences = GeneralUtility::makeInstance(ManualImageProvider::class)
                    ->getFileReferences($contentUid);
                foreach ($fileReferences as $fileReference) {
                    try {
                        $images[] = $fileReference->getOriginalFile();
                    } catch (\Throwable) {
                        // Skip broken relations without blocking the metadata workspace.
                    }
                }
            }
        } elseif ($folder !== '') {
            try {
                $images = GeneralUtility::makeInstance(FolderImageProvider::class)->getImages($folder, $recursive);
                if ($sortBy !== 'random') {
                    $images = GeneralUtility::makeInstance(GalleryImageSorter::class)
                        ->sortDeterministically($images, $sortBy, $sortDir);
                }
            } catch (\Throwable) {
                $folderError = true;
            }
        }

        $hiddenValue = htmlspecialchars(
            (string)json_encode($storedDocument, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
        );
        $legacyCaptionLinesJson = htmlspecialchars(
            (string)json_encode($legacyCaptionLines, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
        );
        [$languageSummary, $languageHelp] = $this->renderLanguageContext($languageContext);
        $html = $this->renderLabel($fieldId)
            . '<div class="form-control-wrap" data-mosaic-metadata-editor'
            . ' data-mosaic-initial-source="' . htmlspecialchars($source, ENT_QUOTES) . '"'
            . ' data-mosaic-image-count-format="' . htmlspecialchars($this->rawLabel('metadata.imageCount'), ENT_QUOTES) . '"'
            . ' data-mosaic-legacy-captions="' . $legacyCaptionLinesJson . '"'
            . ' data-mosaic-edit-label="' . $this->label('metadata.view.edit') . '"'
            . ' data-mosaic-hide-label="' . $this->label('metadata.view.hide') . '"'
            . ' data-mosaic-inherit-label="' . $this->label('metadata.inherit') . '"'
            . ' data-mosaic-custom-label="' . $this->label('metadata.custom') . '"'
            . ' data-mosaic-decorative-label="' . $this->label('metadata.decorative') . '">'
            . '<input type="hidden" id="' . htmlspecialchars($fieldId, ENT_QUOTES) . '"'
            . ' name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '"'
            . ' value="' . $hiddenValue . '" data-formengine-input-name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '"'
            . ' data-mosaic-metadata-storage>'
            . $this->renderWorkspaceHeader(count($images), $languageSummary, $languageHelp)
            . $this->renderConversionUi(
                $legacyCaptions,
                $legacyCaptionsConverted,
                $sortBy,
                max(0, count($legacyCaptionLines) - count($images)),
                $folder !== '' && !$folderError && $images !== [],
                $useFalCaptions,
                $isManualSource,
            );

        if ($isManualSource) {
            $html .= $this->renderManualMetadataWorkspace($images, $storedDocument);
        } else {
            $html .= $this->renderManualMetadataWorkspace([], $storedDocument, true);
            if ($folder === '') {
                $html .= '<p class="form-text" data-mosaic-metadata-empty-folder>'
                    . $this->label('metadata.folderNotSelected') . '</p>';
            } elseif ($folderError) {
                $html .= '<div class="alert alert-warning">' . $this->label('metadata.folderReadError') . '</div>';
            } elseif ($images === []) {
                $html .= '<p class="form-text" data-mosaic-metadata-empty-noimages>'
                    . $this->label('metadata.noImages') . '</p>';
            }
        }

        if (!$isManualSource && $images !== []) {
            $html .= $this->renderMetadataItems($images, $storedDocument);
        }

        $html .= '</div>';
        $resultArray['html'] = $html;
        $resultArray['stylesheetFiles'][] =
            'EXT:anatolkin_mosaic_gallery/Resources/Public/Backend/Css/form-layout.css';
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@anatolkin/mosaic-gallery/metadata-editor.js',
        );

        return $resultArray;
    }

    /**
     * @return array{
     *     languageId: int,
     *     languageTitle: string,
     *     isDefault: bool,
     *     isAll: bool,
     *     isTranslation: bool,
     *     siteLanguages: list<SiteLanguage>,
     *     availableLanguageIds: array<int, true>|null
     * }
     */
    private function resolveLanguageContext(): array
    {
        $databaseRow = is_array($this->data['databaseRow'] ?? null) ? $this->data['databaseRow'] : [];
        $languageId = (int)$this->scalarValue($databaseRow['sys_language_uid'] ?? 0);
        $recordUid = (int)$this->scalarValue($databaseRow['uid'] ?? 0);
        $translationParentUid = (int)$this->scalarValue($databaseRow['l18n_parent'] ?? 0);
        $translationSourceUid = (int)$this->scalarValue($databaseRow['l10n_source'] ?? 0);
        $pageId = (int)$this->scalarValue($databaseRow['pid'] ?? $this->data['effectivePid'] ?? 0);
        $site = $this->resolveSite($pageId);
        $siteLanguages = $site instanceof Site ? $this->resolveSiteLanguages($site, $pageId) : [];
        $languageTitle = $this->resolveLanguageTitle($site, $languageId);

        return [
            'languageId' => $languageId,
            'languageTitle' => $languageTitle,
            'isDefault' => $languageId === 0,
            'isAll' => $languageId === -1,
            'isTranslation' => $languageId > 0
                && ($translationParentUid > 0 || $translationSourceUid > 0),
            'siteLanguages' => $siteLanguages,
            'availableLanguageIds' => $this->findAvailableLanguageIds(
                $recordUid,
                $languageId,
                $translationParentUid,
                $translationSourceUid,
            ),
        ];
    }

    /** @return list<SiteLanguage> */
    private function resolveSiteLanguages(Site $site, int $pageId): array
    {
        $languages = $site->getLanguages();
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if (!$backendUser instanceof BackendUserAuthentication) {
            return array_values($languages);
        }

        try {
            $userLanguages = $site->getAvailableLanguages($backendUser, false, $pageId);
            $languages = array_intersect_key($languages, $userLanguages);
        } catch (\Throwable) {
            // Enabled site languages remain useful if backend-user filtering is unavailable.
        }

        return array_values($languages);
    }

    private function resolveSite(int $pageId): ?Site
    {
        if ($pageId <= 0) {
            return null;
        }

        try {
            return GeneralUtility::makeInstance(SiteFinder::class)->getSiteByPageId($pageId);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveLanguageTitle(?Site $site, int $languageId): string
    {
        if ($languageId === -1) {
            return $this->rawLabel('metadata.language.all');
        }
        if ($site instanceof Site) {
            try {
                return $site->getLanguageById($languageId)->getTitle();
            } catch (\Throwable) {
                // The record remains editable if its language is not available in the current site configuration.
            }
        }

        return $this->formatRawLabel('metadata.language.unresolved', (string)$languageId);
    }

    /** @return array<int, true>|null */
    private function findAvailableLanguageIds(
        int $recordUid,
        int $languageId,
        int $translationParentUid,
        int $translationSourceUid,
    ): ?array
    {
        if ($languageId === -1) {
            return null;
        }

        if ($translationParentUid > 0) {
            $lineageUid = $translationParentUid;
            $isFreeMode = false;
        } elseif ($translationSourceUid > 0) {
            $lineageUid = $translationSourceUid;
            $isFreeMode = true;
        } elseif ($languageId === 0 && $recordUid > 0) {
            $lineageUid = $recordUid;
            $isFreeMode = false;
        } else {
            return null;
        }

        try {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('tt_content');
            $rows = $queryBuilder
                ->select('uid', 'sys_language_uid', 'l18n_parent', 'l10n_source')
                ->from('tt_content')
                ->where(
                    $queryBuilder->expr()->or(
                        $queryBuilder->expr()->eq(
                            'uid',
                            $queryBuilder->createNamedParameter($lineageUid, Connection::PARAM_INT),
                        ),
                        $queryBuilder->expr()->eq(
                            'l18n_parent',
                            $queryBuilder->createNamedParameter($lineageUid, Connection::PARAM_INT),
                        ),
                        $queryBuilder->expr()->eq(
                            'l10n_source',
                            $queryBuilder->createNamedParameter($lineageUid, Connection::PARAM_INT),
                        ),
                    ),
                )
                ->executeQuery()
                ->fetchAllAssociative();

            if ($isFreeMode && !$this->hasUnambiguousFreeModeSource($rows, $lineageUid)) {
                return null;
            }
            if ($this->hasNestedTranslationSources($rows, $lineageUid)) {
                return null;
            }

            $availableLanguageIds = [];
            foreach ($rows as $row) {
                $rowLanguageId = (int)($row['sys_language_uid'] ?? -1);
                if ($rowLanguageId >= 0) {
                    $availableLanguageIds[$rowLanguageId] = true;
                }
            }
            $availableLanguageIds[$languageId] = true;

            return $availableLanguageIds;
        } catch (\Throwable) {
            // Translation status is supplemental and must never block metadata editing.
            return null;
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function hasUnambiguousFreeModeSource(array $rows, int $sourceUid): bool
    {
        foreach ($rows as $row) {
            if ((int)($row['uid'] ?? 0) !== $sourceUid) {
                continue;
            }

            return (int)($row['l18n_parent'] ?? 0) === 0
                && (int)($row['l10n_source'] ?? 0) === 0;
        }

        return false;
    }

    /** @param list<array<string, mixed>> $rows */
    private function hasNestedTranslationSources(array $rows, int $lineageUid): bool
    {
        $directTranslationUids = [];
        foreach ($rows as $row) {
            $rowUid = (int)($row['uid'] ?? 0);
            if ($rowUid > 0 && $rowUid !== $lineageUid) {
                $directTranslationUids[] = $rowUid;
            }
        }
        if ($directTranslationUids === []) {
            return false;
        }

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        return $queryBuilder
            ->count('uid')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->in(
                    'l10n_source',
                    $queryBuilder->createNamedParameter($directTranslationUids, Connection::PARAM_INT_ARRAY),
                ),
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    /**
     * @param array{
     *     languageId: int,
     *     languageTitle: string,
     *     isDefault: bool,
     *     isAll: bool,
     *     isTranslation: bool,
     *     siteLanguages: list<SiteLanguage>,
     *     availableLanguageIds: array<int, true>|null
     * } $context
     * @return array{0: string, 1: string}
     */
    private function renderLanguageContext(array $context): array
    {
        $summary = '<span class="mosaic-metadata-workspace__language">'
            . $this->formatLabel('metadata.language.current', $context['languageTitle']) . '</span>';
        $help = '';

        if ($context['isAll']) {
            $help .= '<p>' . $this->label('metadata.language.allWarning') . '</p>';
        } elseif ($context['isDefault']) {
            $help .= '<p>' . $this->formatLabel('metadata.language.default', $context['languageTitle']) . '</p>';
        } elseif ($context['isTranslation']) {
            $help .= '<p>' . $this->formatLabel('metadata.language.translated', $context['languageTitle']) . '</p>';
        } else {
            $help .= '<p>' . $this->formatLabel('metadata.language.currentRecord', $context['languageTitle']) . '</p>';
        }

        if (!$context['isAll'] && $context['siteLanguages'] !== [] && $context['availableLanguageIds'] !== null) {
            $summary .= '<span class="mosaic-metadata-workspace__translations"><span>'
                . $this->label('metadata.language.translations') . '</span>';
            foreach ($context['siteLanguages'] as $siteLanguage) {
                $isAvailable = isset($context['availableLanguageIds'][$siteLanguage->getLanguageId()]);
                $statusLabel = $isAvailable
                    ? $this->label('metadata.language.available')
                    : $this->label('metadata.language.missing');
                $summary .= '<span class="badge ' . ($isAvailable ? 'text-bg-success' : 'text-bg-secondary') . '"'
                    . ' aria-label="' . $statusLabel . '">'
                    . htmlspecialchars($siteLanguage->getTitle(), ENT_QUOTES)
                    . ' ' . ($isAvailable ? '&#10003;' : '&mdash;') . '</span>';
            }
            $summary .= '</span>';
            $help .= '<p>' . $this->label('metadata.language.translationWorkflow') . '</p>';
        } elseif (!$context['isAll'] && $context['siteLanguages'] !== []) {
            $help .= '<p>' . $this->label('metadata.language.statusUnavailable') . '</p>';
        }

        return [$summary, $help];
    }

    private function renderWorkspaceHeader(int $imageCount, string $languageSummary, string $languageHelp): string
    {
        $helpId = StringUtility::getUniqueId('mosaic-metadata-help-');
        $help = '<p>' . $this->label('metadata.help.gallerySpecific') . '</p>'
            . '<p>' . $this->label('metadata.help.filelist') . '</p>'
            . '<p>' . $this->label('metadata.help.multilingual') . '</p>' . $languageHelp;

        return '<div class="mosaic-metadata-workspace" data-mosaic-images-view="table">'
            . '<div class="mosaic-metadata-workspace__top"><div><strong>' . $this->label('metadata.title') . '</strong> '
            . '<span class="badge text-bg-primary">' . $this->label('metadata.recommended') . '</span></div>'
            . '<div class="btn-group btn-group-sm" role="toolbar" aria-label="' . $this->label('metadata.view.label') . '">'
            . $this->renderViewButton('grid', 'metadata.view.grid')
            . $this->renderViewButton('list', 'metadata.view.list')
            . $this->renderViewButton('table', 'metadata.view.table') . '</div></div>'
            . '<div class="mosaic-metadata-workspace__summary"><span>'
            . $this->formatLabel('metadata.imageCount', (string)$imageCount) . '</span>'
            . $languageSummary
            . '<span class="mosaic-metadata-help"><button type="button" class="mosaic-metadata-help__button"'
            . ' aria-label="' . $this->label('metadata.help.open') . '" aria-describedby="'
            . htmlspecialchars($helpId, ENT_QUOTES) . '">&#9432;</button>'
            . '<span id="' . htmlspecialchars($helpId, ENT_QUOTES) . '" class="mosaic-metadata-help__popup" role="tooltip">'
            . $help . '</span></span></div></div>';
    }

    private function renderViewButton(string $view, string $labelKey): string
    {
        return '<button type="button" class="btn btn-default" data-mosaic-images-view-button="' . $view . '"'
            . ' aria-pressed="' . ($view === 'table' ? 'true' : 'false') . '">'
            . $this->label($labelKey) . '</button>';
    }

    /** @return list<string> */
    private function splitLegacyCaptionLines(string $captions): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $captions);
        return array_values(array_filter($lines, static fn($value) => $value !== null));
    }

    private function renderConversionUi(
        string $legacyCaptions,
        bool $converted,
        string $sortBy,
        int $unmatchedLineCount,
        bool $hasImages,
        bool $useFalCaptions,
        bool $isManualSource,
    ): string
    {
        if ($isManualSource) {
            return '';
        }

        if ($converted) {
            return '<div class="alert alert-success mosaic-metadata-status">'
                . $this->label('metadata.conversion.complete') . '</div>';
        }
        if (trim($legacyCaptions) === '') {
            return '';
        }
        if (!$hasImages) {
            return '';
        }
        if ($useFalCaptions) {
            return '<div class="alert alert-info">'
                . $this->label('metadata.conversion.fileMetadataEnabled') . '</div>';
        }
        if ($sortBy === 'random') {
            return '<div class="alert alert-warning">' . $this->label('metadata.conversion.randomBlocked') . '</div>';
        }

        return '<div class="card mb-3" data-mosaic-conversion-panel><div class="card-body py-2">'
            . '<strong>' . $this->label('metadata.conversion.title') . '</strong>'
            . '<p class="mb-2">' . $this->label('metadata.conversion.guidance') . '</p>'
            . '<p class="mb-2">' . $this->label('metadata.conversion.multilingual') . '</p>'
            . '<p class="mb-2">' . $this->label('metadata.conversion.savedSettings') . '</p>'
            . '<button type="button" class="btn btn-default btn-sm" data-mosaic-convert-legacy>'
            . $this->label('metadata.conversion.action') . '</button></div></div>'
            . '<div class="alert alert-success mosaic-metadata-status d-none" data-mosaic-conversion-status'
            . ' data-success-text="' . $this->label('metadata.conversion.complete') . '"'
            . ' data-extra-text="' . $this->label('metadata.conversion.extraLines') . '"'
            . ' data-unmatched-line-count="' . $unmatchedLineCount . '"></div>';
    }

    private function scalarValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        return $this->scalarValue(reset($value));
    }

    /** @return array{schemaVersion: int, files: array<string, mixed>} */
    private function decodeDocument(string $value): array
    {
        try {
            $document = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $document = [];
        }
        if (!is_array($document)) {
            $document = [];
        }
        $document['schemaVersion'] = 1;
        $document['files'] = is_array($document['files'] ?? null) ? $document['files'] : [];

        return $document;
    }

    /** @param list<string> $allowedModes @return array{mode: string, value: string} */
    private function normalizeProperty(mixed $property, array $allowedModes): array
    {
        if (!is_array($property) || !in_array($property['mode'] ?? '', $allowedModes, true)) {
            return ['mode' => 'inherit', 'value' => ''];
        }
        return ['mode' => $property['mode'], 'value' => (string)($property['value'] ?? '')];
    }

    /** @param list<File> $images @param array{schemaVersion: int, files: array<string, mixed>} $storedDocument */
    private function renderManualMetadataWorkspace(array $images, array $storedDocument, bool $hidden = false): string
    {
        $html = '<div data-mosaic-manual-live-scaffold' . ($hidden ? ' hidden' : '') . '>';
        $html .= $this->renderMetadataTableHead();
        $html .= '<div class="mosaic-metadata-items">';
        foreach ($images as $file) {
            $uid = $file->getUid();
            $entry = $storedDocument['files'][(string)$uid] ?? [];
            $caption = $this->normalizeProperty($entry['caption'] ?? null, ['inherit', 'custom']);
            $alt = $this->normalizeProperty($entry['alt'] ?? null, ['inherit', 'custom', 'empty']);
            $html .= $this->renderItem(
                $uid,
                (string)$file->getName(),
                (string)$file->getIdentifier(),
                $this->getPreviewUrl($file),
                $caption,
                $alt,
            );
        }
        $html .= '</div>';
        $html .= $this->renderItemTemplate();
        $html .= '<p class="form-text" data-mosaic-metadata-empty-manual'
            . ($images === [] ? '' : ' hidden')
            . '>' . $this->label('metadata.noManualImages') . '</p>';
        $html .= '</div>';

        return $html;
    }

    /** @param list<File> $images @param array{schemaVersion: int, files: array<string, mixed>} $storedDocument */
    private function renderMetadataItems(array $images, array $storedDocument): string
    {
        $html = $this->renderMetadataTableHead() . '<div class="mosaic-metadata-items">';
        foreach ($images as $file) {
            $uid = $file->getUid();
            $entry = $storedDocument['files'][(string)$uid] ?? [];
            $caption = $this->normalizeProperty($entry['caption'] ?? null, ['inherit', 'custom']);
            $alt = $this->normalizeProperty($entry['alt'] ?? null, ['inherit', 'custom', 'empty']);
            $html .= $this->renderItem(
                $uid,
                (string)$file->getName(),
                (string)$file->getIdentifier(),
                $this->getPreviewUrl($file),
                $caption,
                $alt,
            );
        }

        return $html . '</div>';
    }

    private function renderMetadataTableHead(): string
    {
        return '<div class="mosaic-metadata-table-head" aria-hidden="true"><span></span><span>'
            . $this->label('metadata.filename') . '</span><span>' . $this->label('metadata.caption')
            . '</span><span>' . $this->label('metadata.alternative') . '</span></div>';
    }

    private function renderItemTemplate(): string
    {
        return '<template data-mosaic-metadata-item-template>'
            . $this->renderItem(
                0,
                '',
                '',
                '',
                ['mode' => 'inherit', 'value' => ''],
                ['mode' => 'inherit', 'value' => ''],
            )
            . '</template>';
    }

    /** @param array{mode: string, value: string} $caption @param array{mode: string, value: string} $alt */
    private function renderItem(int $uid, string $name, string $identifier, string $publicUrl, array $caption, array $alt): string
    {
        $escapedName = htmlspecialchars($name, ENT_QUOTES);
        $escapedIdentifier = htmlspecialchars($identifier, ENT_QUOTES);
        $thumbnail = $publicUrl === '' ? '' : '<img src="' . htmlspecialchars($publicUrl, ENT_QUOTES) . '" alt="">';

        return '<article class="mosaic-metadata-item" data-mosaic-file-uid="' . $uid . '">'
            . '<div class="mosaic-metadata-item__media">' . $thumbnail . '</div>'
            . '<div class="mosaic-metadata-item__identity"><strong>' . $escapedName . '</strong>'
            . '<small>' . $escapedIdentifier . '</small></div>'
            . $this->renderPropertyCell('caption', $caption, false)
            . $this->renderPropertyCell('alt', $alt, true)
            . '<div class="mosaic-metadata-item__technical">'
            . $this->renderStatusIcon('caption', $caption)
            . $this->renderStatusIcon('alt', $alt)
            . '<button type="button" class="btn btn-default btn-sm mosaic-metadata-item__edit"'
            . ' data-mosaic-edit-metadata data-mosaic-action-tooltip aria-expanded="false" aria-label="'
            . $this->label('metadata.view.edit') . '">' . $this->iconSvg('edit')
            . '<span class="mosaic-metadata-item__edit-label">' . $this->label('metadata.view.edit') . '</span></button></div>'
            . '</article>';
    }

    /** @param array{mode: string, value: string} $property */
    private function renderStatusIcon(string $propertyName, array $property): string
    {
        $label = $propertyName === 'caption' ? $this->label('metadata.caption') : $this->label('metadata.alternative');
        $state = match ($property['mode']) {
            'custom' => $this->label('metadata.custom'),
            'empty' => $this->label('metadata.decorative'),
            default => $this->label('metadata.inherit'),
        };
        $value = $property['mode'] === 'custom' ? htmlspecialchars($property['value'], ENT_QUOTES) : '';
        $tooltipId = StringUtility::getUniqueId('mosaic-metadata-status-');

        return '<span class="mosaic-metadata-state"><button type="button" class="mosaic-metadata-state__button"'
            . ' data-mosaic-status-trigger="' . $propertyName . '" aria-label="' . $label . '" aria-describedby="'
            . htmlspecialchars($tooltipId, ENT_QUOTES) . '">' . $this->iconSvg($propertyName) . '</button>'
            . '<span id="' . htmlspecialchars($tooltipId, ENT_QUOTES) . '" class="mosaic-metadata-state__tooltip" role="tooltip">'
            . '<strong>' . $label . '</strong><span data-mosaic-status-state="' . $propertyName . '">' . $state . '</span>'
            . '<span data-mosaic-status-value="' . $propertyName . '">' . $value . '</span></span></span>';
    }

    private function iconSvg(string $icon): string
    {
        $path = match ($icon) {
            'caption' => '<path d="M3 4.5h18v12H9l-5 4v-4H3z"/><path d="M7 9h10M7 12h7"/>',
            'alt' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m5 17 4-4 3 3 2-2 5 3"/>',
            default => '<path d="m4 20 4.5-1 10-10-3.5-3.5-10 10z"/><path d="m13.5 7 3.5 3.5M4 20l1-4.5 3.5 3.5z"/>',
        };

        return '<svg class="mosaic-backend-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
            . $path . '</svg>';
    }

    /** @param array{mode: string, value: string} $property */
    private function renderPropertyCell(string $propertyName, array $property, bool $allowEmpty): string
    {
        $label = $propertyName === 'caption' ? $this->label('metadata.caption') : $this->label('metadata.alternative');
        $modeLabel = match ($property['mode']) {
            'custom' => $this->label('metadata.custom'),
            'empty' => $this->label('metadata.decorative'),
            default => $this->label('metadata.inherit'),
        };
        $summaryValue = $propertyName === 'caption' && $property['mode'] === 'custom'
            ? htmlspecialchars($property['value'], ENT_QUOTES)
            : '';

        return '<div class="mosaic-metadata-item__property mosaic-metadata-item__property--' . $propertyName . '">'
            . '<div class="mosaic-metadata-item__summary"><span class="mosaic-metadata-item__summary-label">'
            . $label . ':</span> <span class="badge text-bg-secondary" data-mosaic-summary-badge="'
            . $propertyName . '">' . $modeLabel . '</span><span class="mosaic-metadata-item__summary-value"'
            . ' data-mosaic-summary-value="' . $propertyName . '">' . $summaryValue . '</span></div>'
            . '<div class="mosaic-metadata-item__controls" aria-label="' . $label . '">'
            . $this->renderControl($propertyName, $property, $allowEmpty) . '</div></div>';
    }

    private function getPreviewUrl(File $file): string
    {
        try {
            $preview = $file->process(ProcessedFile::CONTEXT_IMAGEPREVIEW, [
                'width' => 240,
                'height' => 180,
            ]);

            return (string)($preview->getPublicUrl() ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /** @param array{mode: string, value: string} $property */
    private function renderControl(string $propertyName, array $property, bool $allowEmpty): string
    {
        $options = '<option value="inherit"' . ($property['mode'] === 'inherit' ? ' selected' : '') . '>'
            . $this->label('metadata.inherit') . '</option>'
            . '<option value="custom"' . ($property['mode'] === 'custom' ? ' selected' : '') . '>'
            . $this->label('metadata.custom') . '</option>';
        if ($allowEmpty) {
            $options .= '<option value="empty"' . ($property['mode'] === 'empty' ? ' selected' : '') . '>'
                . $this->label('metadata.decorative') . '</option>';
        }

        return '<select class="form-select form-select-sm mb-2" data-mosaic-property="' . $propertyName
            . '" data-mosaic-mode>' . $options . '</select>'
            . '<input type="text" class="form-control form-control-sm" data-mosaic-property="' . $propertyName
            . '" data-mosaic-value value="' . htmlspecialchars($property['value'], ENT_QUOTES) . '"'
            . ($property['mode'] !== 'custom' ? ' disabled' : '') . '>';
    }

    private function label(string $key): string
    {
        return htmlspecialchars($this->rawLabel($key), ENT_QUOTES);
    }

    private function rawLabel(string $key): string
    {
        return (string)$GLOBALS['LANG']->sL(self::LANGUAGE_FILE . $key);
    }

    private function formatLabel(string $key, string ...$values): string
    {
        return htmlspecialchars($this->formatRawLabel($key, ...$values), ENT_QUOTES);
    }

    private function formatRawLabel(string $key, string ...$values): string
    {
        return sprintf($this->rawLabel($key), ...$values);
    }
}
