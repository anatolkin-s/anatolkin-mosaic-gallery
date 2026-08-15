<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Form\Element;

use Anatolkin\MosaicGallery\Service\FolderImageProvider;
use Anatolkin\MosaicGallery\Service\GalleryImageSorter;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Service\FlexFormService;
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
        [$folder, $recursive, $sortBy, $sortDir, $legacyCaptions, $useFalCaptions] = $this->readSettings(
            $this->data['databaseRow']['pi_flexform'] ?? '',
        );
        $legacyCaptionLines = $this->splitLegacyCaptionLines($legacyCaptions);
        $legacyCaptionsConverted = ($storedDocument['legacyCaptionsConverted'] ?? false) === true;
        $languageContext = $this->resolveLanguageContext();

        $images = [];
        $folderError = false;
        if ($folder !== '') {
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
        $html = $this->renderLabel($fieldId)
            . '<div class="form-control-wrap" data-mosaic-metadata-editor'
            . ' data-mosaic-legacy-captions="' . $legacyCaptionLinesJson . '">'
            . '<input type="hidden" id="' . htmlspecialchars($fieldId, ENT_QUOTES) . '"'
            . ' name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '"'
            . ' value="' . $hiddenValue . '" data-formengine-input-name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '"'
            . ' data-mosaic-metadata-storage>'
            . '<div class="alert alert-info"><strong>' . $this->label('metadata.help.title') . '</strong>'
            . ' <span class="badge text-bg-primary">' . $this->label('metadata.recommended') . '</span>'
            . $this->renderLanguageContext($languageContext)
            . '<p class="mb-1">' . $this->label('metadata.help.gallerySpecific') . '</p>'
            . '<p class="mb-1">' . $this->label('metadata.help.filelist') . '</p>'
            . '<p class="mb-0">' . $this->label('metadata.help.multilingual') . '</p></div>'
            . $this->renderConversionUi(
                $legacyCaptions,
                $legacyCaptionsConverted,
                $sortBy,
                max(0, count($legacyCaptionLines) - count($images)),
                $folder !== '' && !$folderError && $images !== [],
                $useFalCaptions,
            );

        if ($folder === '') {
            $html .= '<p class="form-text">' . $this->label('metadata.folderNotSelected') . '</p>';
        } elseif ($folderError) {
            $html .= '<div class="alert alert-warning">' . $this->label('metadata.folderReadError') . '</div>';
        } elseif ($images === []) {
            $html .= '<p class="form-text">' . $this->label('metadata.noImages') . '</p>';
        } else {
            $html .= '<div class="table-fit"><table class="table table-striped table-hover">'
                . '<thead><tr><th></th><th>' . $this->label('metadata.filename') . '</th>'
                . '<th>' . $this->label('metadata.caption') . '</th>'
                . '<th>' . $this->label('metadata.alternative') . '</th></tr></thead><tbody>';

            foreach ($images as $file) {
                $uid = $file->getUid();
                $entry = $storedDocument['files'][(string)$uid] ?? [];
                $caption = $this->normalizeProperty($entry['caption'] ?? null, ['inherit', 'custom']);
                $alt = $this->normalizeProperty($entry['alt'] ?? null, ['inherit', 'custom', 'empty']);
                $html .= $this->renderRow(
                    $uid,
                    (string)$file->getName(),
                    (string)$file->getIdentifier(),
                    $this->getPreviewUrl($file),
                    $caption,
                    $alt,
                );
            }
            $html .= '</tbody></table></div>';
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
     */
    private function renderLanguageContext(array $context): string
    {
        $html = '<div class="mt-2 mb-2"><strong>'
            . $this->formatLabel('metadata.language.current', $context['languageTitle'])
            . '</strong></div>';

        if ($context['isAll']) {
            $html .= '<div class="alert alert-warning mb-2">'
                . $this->label('metadata.language.allWarning') . '</div>';
        } elseif ($context['isDefault']) {
            $html .= '<p class="mb-2">'
                . $this->formatLabel('metadata.language.default', $context['languageTitle']) . '</p>';
        } elseif ($context['isTranslation']) {
            $html .= '<p class="mb-2">'
                . $this->formatLabel('metadata.language.translated', $context['languageTitle']) . '</p>';
        } else {
            $html .= '<p class="mb-2">'
                . $this->formatLabel('metadata.language.currentRecord', $context['languageTitle']) . '</p>';
        }

        if (!$context['isAll'] && $context['siteLanguages'] !== [] && $context['availableLanguageIds'] !== null) {
            $html .= '<div class="d-flex flex-wrap gap-1 align-items-center mb-1"><span>'
                . $this->label('metadata.language.translations') . '</span>';
            foreach ($context['siteLanguages'] as $siteLanguage) {
                $isAvailable = isset($context['availableLanguageIds'][$siteLanguage->getLanguageId()]);
                $statusLabel = $isAvailable
                    ? $this->label('metadata.language.available')
                    : $this->label('metadata.language.missing');
                $html .= '<span class="badge ' . ($isAvailable ? 'text-bg-success' : 'text-bg-secondary') . '"'
                    . ' title="' . $statusLabel . '">'
                    . htmlspecialchars($siteLanguage->getTitle(), ENT_QUOTES)
                    . ' ' . ($isAvailable ? '&#10003;' : '&mdash;') . '</span>';
            }
            $html .= '</div><p class="mb-0 small">'
                . $this->label('metadata.language.translationWorkflow') . '</p>';
        } elseif (!$context['isAll'] && $context['siteLanguages'] !== []) {
            $html .= '<p class="mb-0 small">'
                . $this->label('metadata.language.statusUnavailable') . '</p>';
        }

        return $html;
    }

    /** @return array{0: string, 1: bool, 2: string, 3: string, 4: string, 5: bool} */
    private function readSettings(mixed $flexForm): array
    {
        if (is_string($flexForm) && trim($flexForm) !== '') {
            try {
                $flexForm = GeneralUtility::makeInstance(FlexFormService::class)
                    ->convertFlexFormContentToArray($flexForm);
            } catch (\Throwable) {
                return ['', false, 'name', 'asc', '', true];
            }
        }

        if (!is_array($flexForm)) {
            return ['', false, 'name', 'asc', '', true];
        }

        $folder = $flexForm['settings']['folder']
            ?? $flexForm['data']['sDEF']['lDEF']['settings.folder']['vDEF']
            ?? '';
        $recursive = $flexForm['settings']['recursive']
            ?? $flexForm['data']['sDEF']['lDEF']['settings.recursive']['vDEF']
            ?? false;
        $sortBy = $flexForm['settings']['sortBy']
            ?? $flexForm['data']['sDEF']['lDEF']['settings.sortBy']['vDEF']
            ?? 'name';
        $sortDir = $flexForm['settings']['sortDir']
            ?? $flexForm['data']['sDEF']['lDEF']['settings.sortDir']['vDEF']
            ?? 'asc';
        $captions = $flexForm['settings']['captions']
            ?? $flexForm['data']['sDEF']['lDEF']['settings.captions']['vDEF']
            ?? '';
        $useFalCaptions = $flexForm['settings']['useFalCaptions']
            ?? $flexForm['data']['sDEF']['lDEF']['settings.useFalCaptions']['vDEF']
            ?? true;

        return [
            (string)$this->scalarValue($folder),
            (bool)$this->scalarValue($recursive),
            (string)$this->scalarValue($sortBy),
            (string)$this->scalarValue($sortDir),
            (string)$this->scalarValue($captions),
            (bool)$this->scalarValue($useFalCaptions),
        ];
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
    ): string
    {
        if ($converted) {
            return '<div class="alert alert-success">' . $this->label('metadata.conversion.complete') . '</div>';
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
            . '<div class="alert alert-success d-none" data-mosaic-conversion-status'
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

    /** @param array{mode: string, value: string} $caption @param array{mode: string, value: string} $alt */
    private function renderRow(int $uid, string $name, string $identifier, string $publicUrl, array $caption, array $alt): string
    {
        $escapedName = htmlspecialchars($name, ENT_QUOTES);
        $escapedIdentifier = htmlspecialchars($identifier, ENT_QUOTES);
        $thumbnail = $publicUrl === '' ? '' : '<img src="' . htmlspecialchars($publicUrl, ENT_QUOTES)
            . '" alt="" style="width:80px;height:60px;object-fit:cover">';

        return '<tr data-mosaic-file-uid="' . $uid . '"><td>' . $thumbnail . '</td>'
            . '<td><strong>' . $escapedName . '</strong><br><small>' . $escapedIdentifier . '</small></td>'
            . '<td>' . $this->renderControl('caption', $caption, false) . '</td>'
            . '<td>' . $this->renderControl('alt', $alt, true) . '</td></tr>';
    }

    private function getPreviewUrl(File $file): string
    {
        try {
            $preview = $file->process(ProcessedFile::CONTEXT_IMAGEPREVIEW, [
                'width' => 80,
                'height' => 60,
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
