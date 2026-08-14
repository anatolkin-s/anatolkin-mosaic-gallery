<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Form\Element;

use Anatolkin\MosaicGallery\Service\FolderImageProvider;
use TYPO3\CMS\Backend\Form\Element\AbstractFormElement;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Service\FlexFormService;
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
        [$folder, $recursive] = $this->readFolderSettings($this->data['databaseRow']['pi_flexform'] ?? '');

        $images = [];
        $folderError = false;
        if ($folder !== '') {
            try {
                $images = GeneralUtility::makeInstance(FolderImageProvider::class)->getImages($folder, $recursive);
            } catch (\Throwable) {
                $folderError = true;
            }
        }

        $hiddenValue = htmlspecialchars(
            (string)json_encode($storedDocument, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
        );
        $html = $this->renderLabel($fieldId)
            . '<div class="form-control-wrap" data-mosaic-metadata-editor>'
            . '<input type="hidden" id="' . htmlspecialchars($fieldId, ENT_QUOTES) . '"'
            . ' name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '"'
            . ' value="' . $hiddenValue . '" data-formengine-input-name="' . htmlspecialchars($fieldName, ENT_QUOTES) . '"'
            . ' data-mosaic-metadata-storage>';

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
        $resultArray['javaScriptModules'][] = JavaScriptModuleInstruction::create(
            '@anatolkin/mosaic-gallery/metadata-editor.js',
        )->invoke('initialize');

        return $resultArray;
    }

    /** @return array{0: string, 1: bool} */
    private function readFolderSettings(mixed $flexForm): array
    {
        if (is_string($flexForm) && trim($flexForm) !== '') {
            try {
                $flexForm = GeneralUtility::makeInstance(FlexFormService::class)
                    ->convertFlexFormContentToArray($flexForm);
            } catch (\Throwable) {
                return ['', false];
            }
        }

        if (!is_array($flexForm)) {
            return ['', false];
        }

        $folder = $flexForm['settings']['folder']
            ?? $flexForm['data']['sDEF']['lDEF']['settings.folder']['vDEF']
            ?? '';
        $recursive = $flexForm['settings']['recursive']
            ?? $flexForm['data']['sDEF']['lDEF']['settings.recursive']['vDEF']
            ?? false;

        return [(string)$this->scalarValue($folder), (bool)$this->scalarValue($recursive)];
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
        return htmlspecialchars((string)$GLOBALS['LANG']->sL(self::LANGUAGE_FILE . $key), ENT_QUOTES);
    }
}
