<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Form\FormDataProvider;

use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionDefinition;
use Anatolkin\MosaicGallery\Backend\Permission\MosaicGalleryFlexFormPermissionResolver;
use TYPO3\CMS\Backend\Form\FormDataProviderInterface;

/**
 * Hides Mosaic Gallery FlexForm fields for non-admin editors when a matching
 * backend user-group custom permission is checked.
 *
 * Only the per-request processed data structure is modified. Stored pi_flexform
 * values for hidden fields remain intact and are preserved on save.
 */
final readonly class MosaicGalleryFlexFormPermissionProvider implements FormDataProviderInterface
{
    private const FLEX_FIELD = 'pi_flexform';

    public function __construct(
        private MosaicGalleryFlexFormPermissionResolver $permissionResolver,
    ) {
    }

    public function addData(array $result): array
    {
        if (($result['tableName'] ?? '') !== 'tt_content') {
            return $result;
        }

        if (!$this->permissionResolver->isMosaicGalleryRecord($result)) {
            return $result;
        }

        $flexConfig = $result['processedTca']['columns'][self::FLEX_FIELD]['config'] ?? null;
        if (!is_array($flexConfig) || !is_array($flexConfig['ds'] ?? null)) {
            return $result;
        }

        $hiddenFields = $this->permissionResolver->resolveHiddenFields();
        if ($hiddenFields === []) {
            return $result;
        }

        $dataStructure = $flexConfig['ds'];
        foreach ($hiddenFields as $fieldName) {
            $mapping = MosaicGalleryFlexFormPermissionDefinition::fieldMap()[$fieldName] ?? null;
            if (!is_array($mapping)) {
                continue;
            }

            $sheet = $mapping['sheet'];
            if (!isset($dataStructure['sheets'][$sheet]['ROOT']['el'][$fieldName])) {
                continue;
            }

            unset($dataStructure['sheets'][$sheet]['ROOT']['el'][$fieldName]);
        }

        $result['processedTca']['columns'][self::FLEX_FIELD]['config']['ds'] = $dataStructure;

        return $result;
    }
}
