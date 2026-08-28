<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Form\FormDataProvider;

use Anatolkin\MosaicGallery\Service\FrontendTypoScriptDefaultsReader;
use Anatolkin\MosaicGallery\Service\MosaicGalleryCreationDefaultsDefinition;
use Anatolkin\MosaicGallery\Service\MosaicGalleryCreationDesignOverridesBuilder;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;

/**
 * Injects site TypoScript creation defaults into the per-request FlexForm DS for new records.
 */
final readonly class MosaicGalleryFlexFormDefaultsProvider implements FormDataProviderInterface
{
    private const FLEX_FIELD = 'pi_flexform';
    private const DESIGN_OVERRIDES_FIELD = 'settings.designOverrides';

    /** @var list<string> */
    private const LEGACY_LIST_TYPES = [
        'mosaicgallery_pi1',
        'anatolkinmosaicgallery_pi1',
    ];

    public function __construct(
        private FrontendTypoScriptDefaultsReader $typoScriptDefaultsReader,
        private MosaicGalleryCreationDefaultsDefinition $creationDefaultsDefinition,
        private MosaicGalleryCreationDesignOverridesBuilder $designOverridesBuilder,
    ) {
    }

    public function addData(array $result): array
    {
        if (($result['command'] ?? '') !== 'new') {
            return $result;
        }

        if (!$this->isMosaicGalleryRecord($result)) {
            return $result;
        }

        $flexConfig = $result['processedTca']['columns'][self::FLEX_FIELD]['config'] ?? null;
        if (!is_array($flexConfig) || !is_array($flexConfig['ds'] ?? null)) {
            return $result;
        }

        $site = $result['site'] ?? null;
        if (!$site instanceof SiteInterface) {
            $site = new NullSite();
        }

        $request = $result['request'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return $result;
        }

        $pageId = (int)($result['effectivePid'] ?? 0);
        $siteDefaults = $this->typoScriptDefaultsReader->resolveCreationDefaults($site, $pageId, $request);
        if ($siteDefaults === []) {
            return $result;
        }

        $dataStructure = $this->creationDefaultsDefinition->applyToDataStructure($flexConfig['ds'], $siteDefaults);
        $dataStructure = $this->applyDesignOverridesDefault($dataStructure, $siteDefaults);

        $result['processedTca']['columns'][self::FLEX_FIELD]['config']['ds'] = $dataStructure;

        return $result;
    }

    /**
     * @param array<string, mixed> $dataStructure
     * @param array<string, scalar> $siteDefaults
     * @return array<string, mixed>
     */
    private function applyDesignOverridesDefault(array $dataStructure, array $siteDefaults): array
    {
        if (!isset($dataStructure['sheets']['sDESIGN']['ROOT']['el'][self::DESIGN_OVERRIDES_FIELD]['config'])
            || !is_array($dataStructure['sheets']['sDESIGN']['ROOT']['el'][self::DESIGN_OVERRIDES_FIELD]['config'])
        ) {
            return $dataStructure;
        }

        $existingDefault = $dataStructure['sheets']['sDESIGN']['ROOT']['el'][self::DESIGN_OVERRIDES_FIELD]['config']['default'] ?? null;
        if (is_string($existingDefault) && trim($existingDefault) !== '' && trim($existingDefault) !== '{}') {
            return $dataStructure;
        }

        $overridesJson = $this->designOverridesBuilder->buildJson($siteDefaults);
        if ($overridesJson === null || $overridesJson === '' || $overridesJson === '{}') {
            return $dataStructure;
        }

        $dataStructure['sheets']['sDESIGN']['ROOT']['el'][self::DESIGN_OVERRIDES_FIELD]['config']['default']
            = $overridesJson;

        return $dataStructure;
    }

    /** @param array<string, mixed> $result */
    private function isMosaicGalleryRecord(array $result): bool
    {
        $row = $result['databaseRow'] ?? [];
        if (!is_array($row)) {
            return false;
        }

        $cType = (string)($row['CType'] ?? '');
        if ($cType === 'mosaicgallery_pi1') {
            return true;
        }

        if ($cType === 'list') {
            $listType = (string)($row['list_type'] ?? '');

            return in_array($listType, self::LEGACY_LIST_TYPES, true);
        }

        return false;
    }
}
