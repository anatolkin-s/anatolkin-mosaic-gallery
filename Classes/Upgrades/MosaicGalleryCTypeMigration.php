<?php
declare(strict_types=1);

namespace Anatolkin\MosaicGallery\Upgrades;

/**
 * Retired 0.3.0/0.4.0 upgrade-wizard class identity.
 *
 * The public wizard identifier remains mosaicGalleryCTypeMigration.
 * The active implementation is MosaicGalleryLegacyCTypeMigration so a previous
 * "wizard done" registry entry for this class cannot hide leftover legacy rows.
 */
final class MosaicGalleryCTypeMigration
{
}
