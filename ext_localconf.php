<?php
declare(strict_types=1);
defined('TYPO3') || die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Anatolkin\MosaicGallery\Controller\GalleryController;

ExtensionUtility::configurePlugin(
  'MosaicGallery',
  'Pi1',
  [GalleryController::class => 'list'],
  []
);
