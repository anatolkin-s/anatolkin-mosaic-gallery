<?php
declare(strict_types=1);

// Exercise the real providers and save hook with isolated TYPO3 boundary doubles.
// This is not a database integration test. The XML boundary uses real XML parsing.
namespace Psr\Http\Message {
    interface ServerRequestInterface {}
}
namespace TYPO3\CMS\Backend\Form {
    interface FormDataProviderInterface { public function addData(array $result): array; }
}
namespace TYPO3\CMS\Core\Site\Entity {
    interface SiteInterface {}
    class NullSite implements SiteInterface {}
}
namespace TYPO3\CMS\Core\Exception {
    class SiteNotFoundException extends \RuntimeException {}
}
namespace TYPO3\CMS\Core\Site {
    class SiteFinder {
        public bool $missing = false;
        public function getSiteByPageId(int $pid): \TYPO3\CMS\Core\Site\Entity\SiteInterface {
            if ($this->missing) {
                throw new \TYPO3\CMS\Core\Exception\SiteNotFoundException();
            }
            return new \TYPO3\CMS\Core\Site\Entity\NullSite();
        }
    }
}
namespace TYPO3\CMS\Core\Authentication {
    class BackendUserAuthentication {
        public bool $admin = false;
        public array $hidden = [];
        public function isAdmin(): bool { return $this->admin; }
        public function check(string $type, string $identifier): bool {
            return in_array($identifier, $this->hidden, true);
        }
    }
}
namespace TYPO3\CMS\Core\DataHandling {
    class DataHandler {
        public \TYPO3\CMS\Core\Authentication\BackendUserAuthentication $BE_USER; public array $substNEWwithIDs = [];
    }
}
namespace TYPO3\CMS\Core\Utility {
    class GeneralUtility {
        public static function makeInstance(string $class): object { return new $class(); } public static function xml2array(string $xml): array|string {
            $document = @simplexml_load_string($xml);
            if ($document === false) { return 'Invalid XML'; }
            $result = ['data' => []];
            foreach ($document->data->sheet as $sheet) {
                foreach ($sheet->language as $language) {
                    foreach ($language->field as $field) {
                        $result['data'][(string)$sheet['index']][(string)$language['index']]
                            [(string)$field['index']]['vDEF'] = (string)$field->value;
                    }
                }
            }
            return $result;
        }
    }
}
namespace TYPO3\CMS\Core\Configuration\FlexForm {
    class FlexFormTools {
        public function flexArray2Xml(array $data): string {
            $xml = new \SimpleXMLElement('<T3FlexForms><data/></T3FlexForms>');
            foreach ($data['data'] as $sheetName => $languages) {
                $sheet = $xml->data->addChild('sheet');
                $sheet->addAttribute('index', $sheetName);
                foreach ($languages as $languageName => $fields) {
                    $language = $sheet->addChild('language');
                    $language->addAttribute('index', $languageName);
                    foreach ($fields as $fieldName => $values) {
                        $field = $language->addChild('field');
                        $field->addAttribute('index', $fieldName);
                        $value = $field->addChild('value', htmlspecialchars((string)$values['vDEF'], ENT_XML1));
                        $value->addAttribute('index', 'vDEF');
                    }
                }
            }
            return $xml->asXML();
        }
    }
}
namespace Anatolkin\MosaicGallery\Service {
    class FrontendTypoScriptDefaultsReader {
        public array $defaults = [];
        public int $pageId = 0;
        public function resolveCreationDefaults($site, int $pageId, $request): array {
            $this->pageId = $pageId;
            return $this->defaults;
        }
    }
}
namespace TYPO3\CMS\Backend\Utility {
    class BackendUtility {
        public static function getRecord($table, $id, $fields): array { return ['pid' => 42]; }
    }
}
namespace TYPO3\CMS\Backend\Form\Element {
    class AbstractFormElement {
        protected array $data = [];
        protected function getLanguageService(): object {
            return new class { public function sL(string $key): string { return $key; } };
        }
    }
}
namespace {
    use Anatolkin\MosaicGallery\Backend\DataHandling\MosaicGalleryCreationDefaultsDataHandler;
    use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionDefinition;
    use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionResolver;
    use Anatolkin\MosaicGallery\Service\DesignPresetResolver;
    use Anatolkin\MosaicGallery\Service\FrontendTypoScriptDefaultsReader;
    use Anatolkin\MosaicGallery\Service\MosaicGalleryCreationDefaultsDefinition;
    use Anatolkin\MosaicGallery\Service\MosaicGalleryCreationDesignOverridesBuilder;

    $root = dirname(__DIR__);
    foreach ([
        'Service/DesignPresetResolver', 'Service/MosaicGalleryCreationDefaultsDefinition',
        'Service/MosaicGalleryCreationDesignOverridesBuilder',
        'Backend/Permission/MosaicGalleryFlexFormPermissionDefinition',
        'Backend/Permission/MosaicGalleryFlexFormPermissionResolver',
        'Backend/Form/FormDataProvider/MosaicGalleryFlexFormDefaultsProvider',
        'Backend/Form/FormDataProvider/MosaicGalleryFlexFormPermissionProvider',
        'Backend/Form/Element/DesignConfiguratorElement',
        'Backend/DataHandling/MosaicGalleryCreationDefaultsDataHandler',
    ] as $class) { require $root . '/Classes/' . $class . '.php'; }
    $reader = new FrontendTypoScriptDefaultsReader();
    $definition = new MosaicGalleryCreationDefaultsDefinition();
    $resolver = new DesignPresetResolver();
    $builder = new MosaicGalleryCreationDesignOverridesBuilder($definition, $resolver);
    $sites = new \TYPO3\CMS\Core\Site\SiteFinder();
    $hook = new MosaicGalleryCreationDefaultsDataHandler($reader, $definition, $builder, $sites);
    $handler = new \TYPO3\CMS\Core\DataHandling\DataHandler();
    $handler->BE_USER = new \TYPO3\CMS\Core\Authentication\BackendUserAuthentication();
    $GLOBALS['BE_USER'] = $handler->BE_USER;
    $GLOBALS['TYPO3_REQUEST'] = new class implements \Psr\Http\Message\ServerRequestInterface {};
    $failures = [];
    $assert = static function (bool $ok, string $message) use (&$failures): void {
        if (!$ok) { $failures[] = $message; }
    };
    $put = static function (array &$flex, string $key, mixed $value): void {
        $m = MosaicGalleryCreationDefaultsDefinition::fieldDefinition($key);
        $flex['data'][$m['sheet']]['lDEF'][$m['field']]['vDEF'] = $value;
    };
    $get = static function (array $flex, string $key): mixed {
        $m = MosaicGalleryCreationDefaultsDefinition::fieldDefinition($key);
        return $flex['data'][$m['sheet']]['lDEF'][$m['field']]['vDEF'] ?? null;
    };
    $run = static function (array $defaults, array $flex = [], array $origin = [], int|string $id = 'NEW1', string $table = 'tt_content') use ($reader, $hook, $handler): array {
        $reader->defaults = $defaults;
        $row = $origin + ['CType' => 'mosaicgallery_pi1', 'pid' => 42, 'pi_flexform' => $flex];
        $hook->processDatamap_preProcessFieldArray($row, $table, $id, $handler);
        return $row;
    };
    $xml = simplexml_load_file($root . '/Configuration/FlexForms/MosaicGallery.xml');
    $targets = [];
    foreach ($definition->getAllowedKeys() as $key) {
        $m = $definition::fieldDefinition($key);
        $targets[] = $m['field'];
        $nodes = $xml->xpath('/T3DataStructure/sheets/' . $m['sheet'] . '/ROOT/el/' . $m['field'] . '/config');
        $assert(count($nodes) === 1, "$key maps to real XML target");
        $fallback = (string)($nodes[0]->default ?? '');
        $valid = match ($m['kind']) {
            'select' => current(array_values(array_filter($m['allowed'], static fn($v) => $v !== $fallback))),
            'integer' => (string)min($m['max'] ?? PHP_INT_MAX, max($m['min'] ?? 0, (int)$fallback + 1)),
            'boolean' => $fallback === '1' ? false : true,
            'alpha' => $fallback === '0.25' ? '0.5' : '0.25',
            'color' => '#123ABC',
            'string' => 'fileadmin/custom-gallery/',
        };
        $expected = $definition->normalizeValue($key, $valid);
        $assert($expected !== null, "$key has valid matrix fixture");
        $row = $run([$key => $valid]);
        $assert($get($row['pi_flexform'], $key) === $expected, "$key missing input receives normalized default");
        $explicit = []; $put($explicit, $key, $fallback);
        $row = $run([$key => $valid], $explicit);
        $assert($get($row['pi_flexform'], $key) === $fallback, "$key explicit input preserved");
        $invalid = match ($m['kind']) { 'integer', 'alpha' => '-1', 'string' => '', default => '__invalid__' };
        $row = $run([$key => $invalid]);
        $assert($row['pi_flexform'] === [], "$key invalid default leaves XML fallback to Core");
    }
    $permissionFields = array_keys(MosaicGalleryFlexFormPermissionDefinition::fieldMap());
    $assert(array_diff($targets, $permissionFields) === [], 'All creation targets covered by permission map');
    echo 'CREATION_DEFAULT_KEYS_TOTAL=' . count($targets) . "\n";
    echo 'CREATION_DEFAULT_KEYS_COVERED=' . count($targets) . "\n";
    echo 'PERMISSION_FIELDS_TOTAL=' . count($permissionFields) . "\n";
    echo 'PERMISSION_ONLY=' . implode(',', array_diff($permissionFields, $targets)) . "\n";
    echo 'INTERSECTION=' . count(array_intersect($targets, $permissionFields)) . "; UNMAPPED=0\n";

    // Exercise real extension providers around a minimal Core default-resolution
    // boundary. Permissions remove controls only after values are resolved.
    $reader->defaults = ['designPreset' => 'framed', 'borderRadius' => 0];
    $defaultsProvider = new \Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryFlexFormDefaultsProvider($reader, $definition, $builder);
    $permissionProvider = new \Anatolkin\MosaicGallery\Backend\Form\FormDataProvider\MosaicGalleryFlexFormPermissionProvider(new MosaicGalleryFlexFormPermissionResolver());
    $ds = ['sheets' => []];
    foreach ($definition->getAllowedKeys() as $key) {
        $m = $definition::fieldDefinition($key);
        $nodes = $xml->xpath('/T3DataStructure/sheets/' . $m['sheet'] . '/ROOT/el/' . $m['field'] . '/config/default');
        $ds['sheets'][$m['sheet']]['ROOT']['el'][$m['field']]['config']['default'] = (string)($nodes[0] ?? '');
    }
    $ds['sheets']['sDESIGN']['ROOT']['el']['settings.designOverrides']['config']['default'] = '';
    $form = $defaultsProvider->addData(['command' => 'new', 'tableName' => 'tt_content',
        'request' => $GLOBALS['TYPO3_REQUEST'], 'effectivePid' => 42,
        'databaseRow' => ['CType' => 'mosaicgallery_pi1'],
        'processedTca' => ['columns' => ['pi_flexform' => ['config' => ['ds' => $ds]]]],
    ]);
    foreach ($form['processedTca']['columns']['pi_flexform']['config']['ds']['sheets'] as $sheet => $sheetDs) {
        foreach ($sheetDs['ROOT']['el'] as $field => $fieldDs) {
            $form['databaseRow']['pi_flexform']['data'][$sheet]['lDEF'][$field]['vDEF'] = $fieldDs['config']['default'];
        }
    }
    $presetPermission = MosaicGalleryFlexFormPermissionDefinition::fieldMap()['settings.designPreset'];
    $handler->BE_USER->hidden = [MosaicGalleryFlexFormPermissionDefinition::permissionIdentifier($presetPermission['category'], $presetPermission['key'])];
    $form = $permissionProvider->addData($form);
    $assert(!isset($form['processedTca']['columns']['pi_flexform']['config']['ds']['sheets']['sDESIGN']['ROOT']['el']['settings.designPreset']), 'Hidden preset control omitted');
    $assert($get($form['databaseRow']['pi_flexform'], 'designPreset') === 'framed', 'Hidden preset retains resolved preview value');
    $handler->BE_USER->hidden = [];

    foreach (['borderRadius' => 0, 'shadow' => false, 'enableLightbox' => 0, 'lbOverlayAlpha' => 0,
        'layoutMode' => 'grid', 'folder' => 'fileadmin/custom-gallery/', 'frameAccentColor' => '', 'designPreset' => ''] as $key => $value) {
        $row = $run([$key => $value]);
        $assert($get($row['pi_flexform'], $key) === $definition->normalizeValue($key, $value), "$key falsey/special injection");
        $explicit = []; $put($explicit, $key, $value);
        $row = $run([$key => '__invalid__'], $explicit);
        $assert($get($row['pi_flexform'], $key) === $value, "$key explicit falsey/empty preserved exactly");
    }
    foreach ([
        'copy' => ['gap', '19', ['t3_origuid' => 123], 'NEWcopy'],
        'localize' => ['borderRadius', '9', ['l18n_parent' => 123], 'NEWlocalized'],
        'copyToLanguage' => ['frameWidth', '7', ['t3_origuid' => 123, 'sys_language_uid' => 1], 'NEWfree'],
        'translation-source' => ['borderRadius', '9', ['l10n_source' => 123], 'NEWtranslated'],
        'workspace-version' => ['borderRadius', '9', ['t3ver_oid' => 123, 't3ver_wsid' => 1], 'NEWversion'],
        'edit' => ['borderRadius', '9', [], 123],
        'move' => ['gap', '19', ['pid' => 77], 123],
    ] as $operation => [$key, $carried, $origin, $id]) {
        $explicit = []; $put($explicit, $key, $carried);
        $row = $run(['gap' => 3, 'borderRadius' => 0, 'frameWidth' => 2], $explicit, $origin, $id);
        $assert($row['pi_flexform'] === $explicit, "$operation: entire carried FlexForm unchanged");
    }
    $assert($get($run(['gap' => 3], [], ['t3ver_wsid' => 1])['pi_flexform'], 'gap') === '3', 'Truly new workspace record');
    $assert($get($run(['gap' => 3], [], ['pid' => -99])['pi_flexform'], 'gap') === '3' && $reader->pageId === 42, 'Insert-after destination');
    $handler->substNEWwithIDs['NEWpage'] = 42;
    $assert($get($run(['gap' => 3], [], ['pid' => 'NEWpage'])['pi_flexform'], 'gap') === '3', 'New-page destination mapping');
    $assert($run(['gap' => 3], [], [], 123)['pi_flexform'] === [], 'Edit never fills absent settings');
    $assert($run(['gap' => 3], [], [], 'NEW1', 'pages')['pi_flexform'] === [], 'Other table guard');
    $assert($run(['gap' => 3], [], ['CType' => 'text'])['pi_flexform'] === [], 'Other plugin guard');
    // No permissions or rendered DS participates: a source/displayCond omission is identical.
    $assert($get($run(['folder' => 'fileadmin/custom-gallery/'])['pi_flexform'], 'folder') === 'fileadmin/custom-gallery/', 'Non-permission displayCond absence');

    $siteDesign = ['designPreset' => 'framed', 'borderRadius' => 0, 'shadow' => 0, 'lbOverlayAlpha' => '0.80'];
    foreach (['visible', 'one-hidden', 'overrides-hidden', 'preset-hidden', 'explicit-direct', 'explicit-json', 'custom'] as $scenario) {
        $flex = [];
        if ($scenario === 'visible') { foreach ($siteDesign as $key => $value) { $put($flex, $key, $value); } }
        if ($scenario === 'one-hidden') { $put($flex, 'designPreset', 'framed'); }
        if ($scenario === 'overrides-hidden') { $put($flex, 'designPreset', 'framed'); }
        if ($scenario === 'explicit-direct') { $put($flex, 'borderRadius', '9'); }
        if ($scenario === 'explicit-json') { $flex['data']['sDESIGN']['lDEF']['settings.designOverrides']['vDEF'] = '{"borderRadius":9}'; }
        if ($scenario === 'custom') { $put($flex, 'designPreset', ''); }
        $saved = $run($siteDesign, $flex)['pi_flexform'];
        $settings = [];
        foreach ($definition->getAllowedKeys() as $key) {
            if ($get($saved, $key) !== null) $settings[$key] = $get($saved, $key);
        }
        $settings['designOverrides'] = $saved['data']['sDESIGN']['lDEF']['settings.designOverrides']['vDEF'] ?? '';
        $effective = $resolver->resolve($settings);
        $expectedRadius = in_array($scenario, ['explicit-direct', 'explicit-json'], true) ? 9 : 0;
        $assert((int)$effective['borderRadius'] === $expectedRadius, "$scenario effective radius");
        $assert((int)$settings['borderRadius'] === $expectedRadius, "$scenario canonical radius agrees");
        $assert(!$effective['shadow'], "$scenario effective false shadow");
        $assert((float)$effective['lightbox']['overlayAlpha'] === 0.8, "$scenario effective alpha");
        if ($scenario === 'custom') $assert($settings['designOverrides'] === '', 'Custom preset does not synthesize JSON');
    }

    $element = new \Anatolkin\MosaicGallery\Backend\Form\Element\DesignConfiguratorElement();
    $renderGroups = new \ReflectionMethod($element, 'renderControlGroups');
    $base = $resolver->resolveAvailablePresetBases()['framed'];
    $controls = (new \ReflectionClass($element))->getConstant('CONTROLS');
    foreach ($permissionFields as $field) {
        $m = MosaicGalleryFlexFormPermissionDefinition::fieldMap()[$field];
        $handler->BE_USER->hidden = [MosaicGalleryFlexFormPermissionDefinition::permissionIdentifier($m['category'], $m['key'])];
        $handler->BE_USER->admin = false;
        $html = $renderGroups->invoke($element, $base, $base, [], []);
        $assert(!str_contains($html, 'data-design-proxy="' . $field . '"'), "$field proxy hidden");
        foreach ($controls as $control) {
            $controlField = 'settings.' . $builder::keyForPath($control['path']);
            if ($controlField === $field) {
                $assert(!str_contains($html, 'data-design-path="' . $control['path'] . '"'), "$field alternate design control hidden");
                $handler->BE_USER->admin = true;
                $adminHtml = $renderGroups->invoke($element, $base, $base, [], []);
                $assert(str_contains($adminHtml, 'data-design-path="' . $control['path'] . '"'), "$field admin sees alternate control");
            }
        }
    }
    $registration = file_get_contents($root . '/ext_localconf.php');
    $assert(str_contains($registration, '= MosaicGalleryCreationDefaultsDataHandler::class;'), 'Save hook registered');
    $assert(str_contains(file_get_contents($root . '/Configuration/Services.yaml'), 'Backend\\DataHandling\\MosaicGalleryCreationDefaultsDataHandler: ~'), 'Hook DI registered');
    $assert((bool)preg_match('/\[MosaicGalleryFlexFormPermissionProvider::class\] = \[[\s\S]*?TcaFlexProcess::class/', $registration), 'Permission provider follows value resolution');
    if ($failures !== []) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
    echo "Creation persistence, origins, design consistency and alternate permissions: PASS\n";
}
