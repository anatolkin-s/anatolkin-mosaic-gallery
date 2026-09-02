<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Backend\Permission;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Resolves opt-in deny restrictions for Mosaic Gallery FlexForm fields.
 *
 * Effective semantics:
 * - no checked custom options => all mapped fields visible
 * - checked custom option => corresponding field hidden
 * - admin => never hidden
 * - multiple effective groups => union of checked restrictions
 */
final class MosaicGalleryFlexFormPermissionResolver
{
    /** @var list<string> */
    private const LEGACY_LIST_TYPES = [
        'mosaicgallery_pi1',
        'anatolkinmosaicgallery_pi1',
    ];

    /**
     * @param callable(string): bool|null $permissionChecker Receives category:key identifiers.
     * @return list<string> FlexForm field names to hide from FormEngine.
     */
    public function resolveHiddenFields(
        ?BackendUserAuthentication $backendUser = null,
        ?callable $permissionChecker = null,
    ): array {
        $backendUser ??= $this->resolveBackendUser();
        // Admins must bypass before any custom_options check(): Core returns true for
        // every identifier when isAdmin() is true, which would otherwise hide all fields.
        if ($backendUser !== null && $backendUser->isAdmin()) {
            return [];
        }

        $checker = $permissionChecker ?? $this->createDefaultChecker($backendUser);
        if ($checker === null) {
            return [];
        }

        $hidden = [];
        foreach (MosaicGalleryFlexFormPermissionDefinition::fieldMap() as $fieldName => $mapping) {
            $identifier = MosaicGalleryFlexFormPermissionDefinition::permissionIdentifier(
                $mapping['category'],
                $mapping['key'],
            );
            if ($checker($identifier)) {
                $hidden[] = $fieldName;
            }
        }

        return $hidden;
    }

    /**
     * @param array<string, mixed> $result
     */
    public function isMosaicGalleryRecord(array $result): bool
    {
        $row = $result['databaseRow'] ?? [];
        if (!is_array($row)) {
            return false;
        }

        $cType = $this->scalarField($row['CType'] ?? '');
        if ($cType === 'mosaicgallery_pi1') {
            return true;
        }

        if ($cType === 'list') {
            $listType = $this->scalarField($row['list_type'] ?? '');

            return in_array($listType, self::LEGACY_LIST_TYPES, true);
        }

        return false;
    }

    private function createDefaultChecker(?BackendUserAuthentication $backendUser): ?callable
    {
        $backendUser ??= $this->resolveBackendUser();
        if ($backendUser === null) {
            return null;
        }

        return static fn(string $identifier): bool => (bool)$backendUser->check('custom_options', $identifier);
    }

    private function resolveBackendUser(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        return $backendUser instanceof BackendUserAuthentication ? $backendUser : null;
    }

    private function scalarField(mixed $value): string
    {
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }

        return trim((string)$value);
    }
}
