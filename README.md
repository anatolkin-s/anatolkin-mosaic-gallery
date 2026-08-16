# Anatolkin Mosaic Gallery

## Overview

Anatolkin Mosaic Gallery is a TYPO3 extension for responsive masonry-style image galleries. Images come from one TYPO3 Fileadmin folder, with optional inclusion of its subfolders. Editors configure each gallery through a content element and can open images in an optional GLightbox-based lightbox.

## Requirements

- TYPO3 CMS 13.4 or 14.3
- PHP 8.2 through 8.5

## Installation

For Composer-based TYPO3 installations:

```bash
composer require anatolkin/anatolkin-mosaic-gallery
```

Apply the TYPO3 extension setup and clear caches as appropriate for the installation:

```bash
php vendor/bin/typo3 extension:setup
php vendor/bin/typo3 cache:flush
```

### Site Set integration (recommended)

For sites using TYPO3 Site Sets:

1. Open **Site Management → Sites**.
2. Edit the site configuration.
3. Under **Sets for this Site**, add **Anatolkin Mosaic Gallery**.
4. Save the site configuration and flush caches.

This is the recommended frontend TypoScript integration for TYPO3 13.4 and 14.3 sites using Site Sets.

### Legacy TypoScript integration

Installations intentionally using TypoScript records instead of Site Sets remain supported. Add the static TypoScript include **Anatolkin Mosaic Gallery (Assets & Masonry)** to the site template.

As an alternative, add this explicit import to the site's TypoScript **Setup**, not Constants:

```typoscript
@import 'EXT:anatolkin_mosaic_gallery/Configuration/TypoScript/setup.typoscript'
```

A site should normally use one integration method. Do not include the same Mosaic Gallery TypoScript through a Site Set, static include, and manual import at the same time.

## Upgrade from 0.2.x to 0.3.0

1. Update or install Anatolkin Mosaic Gallery 0.3.0.
2. Run TYPO3 extension setup:

   ```bash
   php vendor/bin/typo3 extension:setup
   ```

3. For sites using Site Sets, add the **Anatolkin Mosaic Gallery** Site Set.
4. Run the registered TYPO3 upgrade wizard `mosaicGalleryCTypeMigration`.
5. Flush caches.
6. Verify existing Mosaic Gallery content elements in the backend and frontend.

The upgrade wizard converts legacy records from:

```text
CType=list
list_type=mosaicgallery_pi1
```

or:

```text
CType=list
list_type=anatolkinmosaicgallery_pi1
```

to:

```text
CType=mosaicgallery_pi1
list_type=''
```

Do not edit these database records manually.

## Basic usage

1. Create an **Anatolkin Mosaic Gallery** content element from the **Gallery** group.
2. Select a Fileadmin folder.
3. Enable **Include subfolders** if images from nested folders should be included.
4. Choose filename, modification-time, or random ordering. Filename and modification-time sorting support ascending and descending directions.
5. Configure captions, lightbox, Load More, image width, spacing, and visual options.
6. Save the content element. Use **Image metadata** for stable per-image Caption and Alternative text values.

## Current features

- Fileadmin folder image source with optional recursive subfolder inclusion
- Filename and modification-time sorting, including ascending and descending directions
- Random display ordering
- Responsive mosaic layout using Masonry and imagesLoaded
- Configurable image width, gap, frame, corner radius, background, and shadow
- Optional GLightbox lightbox with configurable appearance
- Optional visible captions
- Configurable Load More button that reveals batches of already rendered images; it does not make AJAX requests
- Per-gallery Image metadata editor with backend previews
- Responsive backend editing layout
- English, German, French, Spanish, and Russian extension interface translations

## Image metadata behavior

The Image metadata editor stores gallery-specific values on the current `tt_content` record. Entries are linked to `sys_file.uid`, so Caption and Alternative text overrides remain attached to the correct image when ordering changes or files are added or removed.

For each image:

- **Caption** can inherit the existing fallback or use a gallery-specific custom value.
- **Alternative text** can inherit, use a custom value, or be explicitly empty for a decorative image.
- **Use file metadata as fallback** allows metadata maintained through TYPO3 Filelist to supply inherited values.

The editor reads images from the saved folder settings. Save folder and sorting changes before editing or converting metadata.

## Legacy Quick captions

Quick captions remain available as a simple legacy method. They contain one positional line per image and are not linked to file UIDs. Adding or removing files, changing the order, or using random ordering can associate those lines with different images.

The Image metadata editor is the recommended method for stable metadata. Its explicit conversion action copies eligible Quick caption lines into UID-linked image entries while retaining the original Quick captions as a legacy reference. Conversion uses the last saved gallery settings and is intentionally blocked when a safe positional mapping cannot be established.

## Multilingual behavior

Gallery-specific metadata follows TYPO3's native content localization model:

- The default-language gallery stores its own metadata document.
- Each translated `tt_content` gallery stores an independent metadata document.
- The same file UID can therefore have different Caption and Alternative text overrides in different gallery translations.
- Frontend metadata resolution uses TYPO3's current `LanguageAspect` and `PageRepository` overlay behavior, including configured fallbacks.
- A gallery assigned to **All languages** shares its gallery-specific metadata across site languages.

Configured site languages are discovered from TYPO3 Site Configuration. The five bundled XLIFF languages translate the extension interface and do not limit the site's available content languages.

## Compatibility and release status

Anatolkin Mosaic Gallery 0.3.0 targets TYPO3 13.4 and TYPO3 14.3. Runtime validation was completed successfully on TYPO3 13.4.34 and TYPO3 14.3.6. The `list_type` to `CType` migration was also runtime tested successfully.

Existing folder galleries, the metadata editor, saving, Load More, and the lightbox were runtime-smoke-tested.

Existing folder galleries and legacy Quick captions remain supported. Metadata conversion remains an explicit editor action and is not automatically required.

## License

Anatolkin Mosaic Gallery is released under the [GNU General Public License v2.0 or later](LICENSE).
