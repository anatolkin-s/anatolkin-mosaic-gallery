<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\DataHandling;

use Anatolkin\MosaicGallery\Service\FrontendTypoScriptDefaultsReader;
use Anatolkin\MosaicGallery\Service\MosaicGalleryCreationDefaultsDefinition;
use Anatolkin\MosaicGallery\Service\MosaicGalleryCreationDesignOverridesBuilder;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/** Fill missing incoming values before Core validation, independently of FormEngine visibility. */
final readonly class MosaicGalleryCreationDefaultsDataHandler
{
    public function __construct(
        private FrontendTypoScriptDefaultsReader $defaultsReader,
        private MosaicGalleryCreationDefaultsDefinition $definition,
        private MosaicGalleryCreationDesignOverridesBuilder $overridesBuilder,
        private SiteFinder $siteFinder,
    ) {
    }

    public function processDatamap_preProcessFieldArray(
        array &$incoming,
        string $table,
        int|string $id,
        DataHandler $dataHandler,
    ): void {
        if ($table !== 'tt_content' || !is_string($id) || !str_starts_with($id, 'NEW')) {
            return;
        }
        // Copies, free-mode translations, connected translations and versions carry
        // these tt_content origin fields. New workspace records have no source uid.
        foreach (['t3_origuid', 'l18n_parent', 'l10n_source', 't3ver_oid'] as $origin) {
            if ((int)($incoming[$origin] ?? 0) !== 0) {
                return;
            }
        }
        $type = $incoming['CType'] ?? '';
        if ($type !== 'mosaicgallery_pi1'
            && !($type === 'list' && in_array($incoming['list_type'] ?? '', [
                'mosaicgallery_pi1', 'anatolkinmosaicgallery_pi1',
            ], true))
        ) {
            return;
        }
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return;
        }
        // Negative pid means insert after a content record, not a negative page id.
        $pid = $incoming['pid'] ?? 0;
        if (!is_numeric($pid)) {
            $negative = str_starts_with((string)$pid, '-');
            $pid = $dataHandler->substNEWwithIDs[ltrim((string)$pid, '-')] ?? 0;
            $pid = $negative ? -(int)$pid : (int)$pid;
        }
        $pageId = (int)$pid;
        if ($pageId < 0) {
            $pageId = (int)(BackendUtility::getRecord('tt_content', -$pageId, 'pid')['pid'] ?? 0);
        }
        if ($pageId <= 0) {
            return;
        }
        try {
            $site = $this->siteFinder->getSiteByPageId($pageId);
        } catch (SiteNotFoundException) {
            $site = new NullSite();
        }
        $defaults = $this->defaultsReader->resolveCreationDefaults($site, $pageId, $request);
        if ($defaults === []) {
            return;
        }
        $flex = $incoming['pi_flexform'] ?? [];
        if (is_string($flex)) {
            $flex = $flex === '' ? [] : GeneralUtility::xml2array($flex);
        }
        if (!is_array($flex)) {
            return;
        }
        $original = $flex;
        $flex = $this->overridesBuilder->applyCreationDefaults($flex, $defaults);
        foreach ($this->definition->getAllowedKeys() as $key) {
            $mapping = $this->definition::fieldDefinition($key);
            $target = $flex['data'][$mapping['sheet']]['lDEF'][$mapping['field']] ?? [];
            if (is_array($target) && array_key_exists('vDEF', $target)) {
                continue;
            }
            $normalized = array_key_exists($key, $defaults)
                ? $this->definition->normalizeValue($key, $defaults[$key]) : null;
            if ($normalized !== null) {
                $flex['data'][$mapping['sheet']]['lDEF'][$mapping['field']]['vDEF'] = $normalized;
            }
        }
        if ($flex !== $original) {
            $incoming['pi_flexform'] = $flex;
        }
    }
}
