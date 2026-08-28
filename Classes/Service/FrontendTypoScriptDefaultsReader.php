<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Service;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Cache\Frontend\PhpFrontend;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Core\Site\Set\SetRegistry;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScriptFactory;
use TYPO3\CMS\Core\TypoScript\IncludeTree\SysTemplateRepository;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;

/**
 * Resolves plugin.tx_mosaicgallery_pi1.settings.defaults from frontend TypoScript.
 *
 * TYPO3 Core TypoScript compilation APIs used here (FrontendTypoScriptFactory,
 * SysTemplateRepository) are @internal. They are isolated in this class so the
 * rest of the extension does not depend on them directly.
 */
final class FrontendTypoScriptDefaultsReader
{
    /** @var list<string> */
    private const DEFAULTS_TYPOSCRIPT_PATH = [
        'plugin.',
        'tx_mosaicgallery_pi1.',
        'settings.',
        'defaults.',
    ];

    public function __construct(
        private readonly TypoScriptService $typoScriptService,
        #[Autowire(service: 'cache.typoscript')]
        private readonly PhpFrontend $typoScriptCache,
        #[Autowire(service: 'cache.runtime')]
        private readonly FrontendInterface $runtimeCache,
        private readonly SysTemplateRepository $sysTemplateRepository,
        private readonly FrontendTypoScriptFactory $frontendTypoScriptFactory,
        private readonly SetRegistry $setRegistry,
    ) {
    }

    /**
     * @return array<string, scalar>
     */
    public function resolveCreationDefaults(
        SiteInterface $site,
        int $pageId,
        ServerRequestInterface $request,
    ): array {
        if ($pageId <= 0 && !($site instanceof Site)) {
            return [];
        }

        try {
            $setupArray = $this->compileSetupArray($site, $pageId, $request);
            $defaultsTypoScript = $this->extractNestedTypoScriptArray($setupArray, self::DEFAULTS_TYPOSCRIPT_PATH);
            if ($defaultsTypoScript === null) {
                return [];
            }

            $defaults = $this->typoScriptService->convertTypoScriptArrayToPlainArray($defaultsTypoScript);
            if (!is_array($defaults)) {
                return [];
            }

            /** @var array<string, scalar> $filtered */
            $filtered = [];
            foreach ($defaults as $key => $value) {
                if (is_string($key) && (is_string($value) || is_int($value) || is_float($value) || is_bool($value))) {
                    $filtered[$key] = $value;
                }
            }

            return $filtered;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param array<string, mixed> $root
     * @param list<string> $path
     * @return array<string, mixed>|null
     */
    private function extractNestedTypoScriptArray(array $root, array $path): ?array
    {
        $current = $root;
        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return is_array($current) ? $current : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function compileSetupArray(
        SiteInterface $site,
        int $pageId,
        ServerRequestInterface $request,
    ): array {
        $cacheIdentifier = 'mosaic-gallery-creation-defaults-'
            . sha1($site->getIdentifier() . ':' . $pageId);
        $cached = $this->runtimeCache->get($cacheIdentifier);
        if (is_array($cached)) {
            return $cached;
        }

        if ($site instanceof NullSite && $pageId <= 0) {
            return [];
        }

        $rootLine = [];
        $sysTemplateRows = [];
        if ($pageId > 0) {
            $rootLine = GeneralUtility::makeInstance(RootlineUtility::class, $pageId)->get();
            $rootLineForSysTemplates = $rootLine;
            if ($site instanceof Site && $site->isTypoScriptRoot()) {
                $rootLineForSysTemplates = [];
                foreach ($rootLine as $index => $rootlinePage) {
                    $rootLineForSysTemplates[$index] = $rootlinePage;
                    if ((int)($rootlinePage['uid'] ?? 0) === $site->getRootPageId()) {
                        break;
                    }
                }
            }
            $sysTemplateRows = $this->sysTemplateRepository->getSysTemplateRowsByRootline(
                $rootLineForSysTemplates,
                $request,
            );
            ksort($rootLine);
        }

        $sets = $site instanceof Site ? $this->setRegistry->getSets(...$site->getSets()) : [];
        if ($sysTemplateRows === [] && $sets === []) {
            $sysTemplateRows[] = $this->fakeSysTemplateRow();
        }

        $expressionMatcherVariables = [
            'request' => $request,
            'pageId' => $pageId,
            'page' => $rootLine !== [] ? $rootLine[array_key_first($rootLine)] : [],
            'fullRootLine' => $rootLine,
            'site' => $site,
        ];

        $typoScript = $this->frontendTypoScriptFactory->createSettingsAndSetupConditions(
            $site,
            $sysTemplateRows,
            $expressionMatcherVariables,
            $this->typoScriptCache,
        );
        $typoScript = $this->frontendTypoScriptFactory->createSetupConfigOrFullSetup(
            true,
            $typoScript,
            $site,
            $sysTemplateRows,
            $expressionMatcherVariables,
            '0',
            $this->typoScriptCache,
            null,
        );

        $setupArray = $typoScript->getSetupArray();
        $this->runtimeCache->set($cacheIdentifier, $setupArray);

        return $setupArray;
    }

    /** @return array<string, mixed> */
    private function fakeSysTemplateRow(): array
    {
        return [
            'uid' => 0,
            'pid' => 0,
            'title' => 'Fake sys_template row to force global TypoScript loading',
            'root' => 1,
            'clear' => 3,
            'include_static_file' => '',
            'basedOn' => '',
            'includeStaticAfterBasedOn' => 0,
            'static_file_mode' => false,
            'constants' => '',
            'config' => '',
            'deleted' => 0,
            'hidden' => 0,
            'starttime' => 0,
            'endtime' => 0,
            'sorting' => 0,
        ];
    }
}
