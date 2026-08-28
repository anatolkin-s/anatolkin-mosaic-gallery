# Anatolkin Mosaic Gallery

## Overview

Anatolkin Mosaic Gallery is a TYPO3 extension for responsive FAL image galleries. Images come from one TYPO3 Fileadmin folder, with optional inclusion of its subfolders. Editors can choose from multiple gallery layouts, customize gallery and lightbox appearance, manage per-image Caption and Alternative text overrides, and open images in an optional GLightbox-based lightbox.

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

### Site defaults for new galleries

Site integrators can configure the **initial values shown when creating a new Mosaic Gallery content element** through:

```typoscript
plugin.tx_mosaicgallery_pi1.settings.defaults {
    gap = 20
    layoutMode = grid
    enableLightbox = 0
    frameWidth = 0
}
```

This namespace controls **creation defaults only**. It does not provide live inheritance for already saved galleries.

**Precedence for a new content element:**

1. Site TypoScript `settings.defaults.*`
2. Extension FlexForm XML default (when a TypoScript key is absent)

**After the first save:**

Stored FlexForm values become explicit content-element settings and remain authoritative permanently.

Changing TypoScript defaults later does **not** retroactively alter already saved galleries.

The existing 0.4.x top-level runtime settings remain supported and unchanged:

```typoscript
plugin.tx_mosaicgallery_pi1.settings {
    source = folder
    folder = fileadmin/gallery/
    recursive = 1
    gap = 12
}
```

Those keys affect **frontend runtime** behavior when a FlexForm value is absent. They are separate from the creation-default API above.

## Upgrade and legacy compatibility

Canonical Mosaic Gallery content uses:

```text
CType=mosaicgallery_pi1
```

Older installations stored galleries as plugins:

```text
CType=list
list_type=mosaicgallery_pi1
```

or:

```text
CType=list
list_type=anatolkinmosaicgallery_pi1
```

Whether migration is needed is determined from those database records, not from the installed extension version. Do not edit `tt_content` rows manually.

The TYPO3 upgrade wizard identifier is `mosaicGalleryCTypeMigration`. It may be checked again if matching legacy rows appear later. It does not run automatically during Composer install, extension setup, or normal frontend or backend requests.

On TYPO3 13.4, a compatibility bridge restores frontend rendering and backend editing of leftover plugin records **before** the wizard is run. The wizard remains the recommended permanent conversion. Complete that conversion before upgrading the TYPO3 Core to 14.

### Fresh installation

1. Install Anatolkin Mosaic Gallery.
2. Run TYPO3 extension setup and flush caches.
3. For Site Set sites, add the **Anatolkin Mosaic Gallery** Site Set.
4. Create galleries from the **Gallery** group. New records use `CType=mosaicgallery_pi1`.
5. The upgrade wizard is unnecessary when no legacy plugin records exist.

### Already canonical 0.3.x or 0.4.x records

If existing galleries already use `CType=mosaicgallery_pi1`, no database migration is required. Flush caches after updating. The wizard stays inactive unless leftover `CType=list` plugin records are still present.

### Direct update from 0.1.x or 0.2.x

1. Update to the current Anatolkin Mosaic Gallery release.
2. Run TYPO3 extension setup and flush caches.
3. For Site Set sites, add the **Anatolkin Mosaic Gallery** Site Set.
4. On TYPO3 13.4, existing plugin records should render and remain editable immediately.
5. Run `mosaicGalleryCTypeMigration` to convert them to `CType=mosaicgallery_pi1` with an empty `list_type`.
6. Verify backend labels, FlexForm settings, and frontend output.

### Recovery after 0.3.x or 0.4.0 with leftover plugin records

If the site was already updated to 0.3.x or 0.4.0 while some or all galleries remained `CType=list`, install the current release and flush caches. On TYPO3 13.4 the compatibility bridge restores frontend output and backend labels without changing those rows. Then run `mosaicGalleryCTypeMigration`. Mixed sites with both canonical and leftover plugin records are supported: only matching legacy rows are converted.

The wizard converts matching records to:

```text
CType=mosaicgallery_pi1
list_type=''
```

FlexForm data, record UIDs, localization relationships, and ordinary `tt_content` fields are preserved. No duplicate content elements are created.

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
- Five responsive gallery layouts: Masonry, Mosaic, Patterned Mosaic, Justified Rows, and Uniform Grid
- Configurable Patterned Mosaic density on wide screens
- Configurable image width, gap, frames, frame accents, corner radius, background, shadow, captions, and layout behavior
- Design presets with gallery-specific overrides and a live Gallery/Lightbox design preview
- Optional GLightbox lightbox with per-gallery scoped appearance controls
- Configurable gallery and Lightbox caption presentation
- Progressive image loading with configurable initial batch, Load More step, and Lightbox refresh
- Per-gallery UID-linked Caption and Alternative text metadata overrides
- Images workspace with Grid, List, and Table metadata views
- Multilingual metadata workflow integrated with TYPO3 content localization
- Compact responsive backend configuration
- English, German, French, Spanish, and Russian extension interface translations

## What's new in 0.4.2

Version 0.4.2 improves custom-template integration, inherited FAL metadata, and GLightbox accessibility without requiring a database migration.

Highlights:

- Exposes the current `tt_content` row to custom Fluid templates as `{data}`.
- Exposes a stable localized `sys_file_metadata` subset for each gallery item: title, caption, description, alternative text, and copyright.
- Uses inherited caption fallback order: `caption → title → description` while preserving gallery-specific overrides.
- Fixes the GLightbox `aria-hidden` focus warning by clearing focus from the originating gallery link before GLightbox hides background content, moving focus into the lightbox when it opens, and restoring focus to the originating link when it closes.
- No database migration is required for already-canonical gallery records.

## What's new in 0.4.1

Version 0.4.1 is a compatibility hotfix for upgrades from earlier Mosaic Gallery releases.

It restores live frontend and backend use of leftover `CType=list` gallery records on TYPO3 13.4, keeps the `mosaicGalleryCTypeMigration` wizard data-driven for both legacy `list_type` signatures, and normalizes TYPO3 FormEngine FlexForm wrapper values. Empty legacy `vDEF` arrays that previously caused `Array to string conversion` in the Design configurator are treated as empty strings. Already-canonical `CType=mosaicgallery_pi1` records need no database migration.

## What's new in 0.4.0

Version 0.4.0 substantially expands gallery presentation and backend editing while preserving existing gallery compatibility.

Highlights include:

- Masonry, Mosaic, Patterned Mosaic, Justified Rows, and Uniform Grid layouts
- Adaptive Patterned Mosaic density with preserved image proportions
- Design presets with gallery-specific overrides
- Live Gallery and Lightbox design preview
- Expanded frame, caption, background, and Lightbox styling controls
- Compact responsive Layout workspace
- Images workspace with Grid, List, and Table metadata views
- UID-linked Caption and Alternative text overrides
- Improved multilingual metadata workflow
- Per-gallery scoped Lightbox theming

Existing galleries without a layout setting continue to use Masonry. Updating from 0.3.1 to 0.4.0 requires no database migration when records are already canonical. Leftover plugin records from earlier releases still need `mosaicGalleryCTypeMigration`.

## Custom Fluid templates

The gallery Fluid template receives the current `tt_content` content-object row as `{data}`. Custom templates can therefore access ordinary content element fields, for example:

```html
<f:comment>Gallery content element uid: {data.uid}, page id: {data.pid}</f:comment>
```

Typical fields include `{data.uid}`, `{data.pid}`, `{data.CType}`, and `{data.sys_language_uid}`. The value comes from the active frontend `ContentObjectRenderer` data for the rendering context.

Each gallery item also exposes localized `sys_file_metadata` as a stable subset:

- `{it.metadata.title}`
- `{it.metadata.caption}`
- `{it.metadata.description}`
- `{it.metadata.alternative}`
- `{it.metadata.copyright}`

`{it.caption}` and `{it.alt}` remain the final resolved values after gallery-specific overrides. When **Use file metadata as fallback** is enabled, the visible caption fallback order is:

```text
caption → title → description
```

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

Anatolkin Mosaic Gallery 0.4.2 targets TYPO3 13.4 and TYPO3 14.3.

Existing folder galleries, legacy Quick captions, and the `list_type` to `CType` migration path introduced in 0.3.0 remain supported. On TYPO3 13.4, unmigrated plugin records remain usable before that wizard is run. TYPO3 14 installations should complete the wizard first and then use dedicated `CType=mosaicgallery_pi1` records only.

Existing galleries without the newer layout and design settings continue to use backward-compatible defaults. Metadata conversion remains an explicit editor action and is not automatically required.

## License

Anatolkin Mosaic Gallery is released under the [GNU General Public License v2.0 or later](LICENSE).
