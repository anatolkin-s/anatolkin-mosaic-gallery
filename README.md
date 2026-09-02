# Anatolkin Mosaic Gallery

## Overview

Anatolkin Mosaic Gallery is a TYPO3 extension for responsive FAL image galleries. Images can come from a Fileadmin folder (with optional subfolders) or from individually selected Manual Images using native TYPO3 FAL FileReferences. Editors can choose from multiple gallery layouts, customize gallery and lightbox appearance, manage per-image Caption and Alternative text overrides, and open images in an optional GLightbox-based lightbox.

## Requirements

- TYPO3 CMS 13.4 or 14.3
- PHP 8.2 through 8.5

## Installation

Choose the path that matches your TYPO3 installation.

### Composer — fresh install

Recommended for Composer-based TYPO3 projects:

```bash
composer require anatolkin/anatolkin-mosaic-gallery:^0.6
```

Then apply extension setup:

```bash
php vendor/bin/typo3 extension:setup
```

Optionally, when configuration or assets appear stale:

```bash
php vendor/bin/typo3 cache:flush
```

In the TYPO3 backend, add the Site Set:

1. Open **Site Management → Sites**.
2. Edit the site configuration.
3. Under **Sets for this Site**, add **Anatolkin Mosaic Gallery**.
4. Save the site configuration.

Site Set integration is the recommended frontend TypoScript integration for TYPO3 13.4 and 14.3.

**Legacy TypoScript alternative:** add the static TypoScript include **Anatolkin Mosaic Gallery (Assets & Masonry)** to the site template, or add this explicit import to the site's TypoScript **Setup** (not Constants):

```typoscript
@import 'EXT:anatolkin_mosaic_gallery/Configuration/TypoScript/setup.typoscript'
```

Use one integration method only. Do not include the same Mosaic Gallery TypoScript through a Site Set, static include, and manual import at the same time.

### Updating with Composer

Normal update:

```bash
composer update anatolkin/anatolkin-mosaic-gallery -W
```

Then run extension setup:

```bash
php vendor/bin/typo3 extension:setup
```

Optionally:

```bash
php vendor/bin/typo3 cache:flush
```

Run `extension:setup` after every install or update of this package. Existing Site Set or TypoScript integration should normally remain enabled; verify gallery backend editing and frontend output after updating.

If `composer.json` pins an older exact release and blocks the update, widen the project constraint first, for example:

```bash
composer require anatolkin/anatolkin-mosaic-gallery:^0.6 -W
```

Do not edit `composer.lock` manually.

### TER / classic installation

For non-Composer / classic TYPO3 installations, install **Anatolkin Mosaic Gallery** from the TYPO3 Extension Repository through the Extension Manager.

After installation:

1. Ensure the extension is active and setup has been applied in TYPO3.
2. For Site Set sites, add the **Anatolkin Mosaic Gallery** Site Set under **Site Management → Sites**.
3. Otherwise use the legacy TypoScript integration documented above.
4. Flush TYPO3 caches if configuration or assets appear stale.

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

### Legacy plugin records still present?

If legacy records still exist as:

```text
CType=list
list_type=mosaicgallery_pi1
```

or:

```text
CType=list
list_type=anatolkinmosaicgallery_pi1
```

then on TYPO3 13.4 users should check and run the migration wizard **before** moving the installation to TYPO3 14.

Whether migration is needed is determined from those database records, not from the installed extension version. Do not edit `tt_content` rows manually.

The TYPO3 upgrade wizard identifier is `mosaicGalleryCTypeMigration`. It may be checked again if matching legacy rows appear later. It does not run automatically during Composer install, extension setup, or normal frontend or backend requests.

**Backend:** **Admin Tools → System → Upgrade → Upgrade Wizard**

**CLI (Composer installations):**

```bash
vendor/bin/typo3 upgrade:list
vendor/bin/typo3 upgrade:run mosaicGalleryCTypeMigration
```

Updating from 0.5.x or 0.6.0 to 0.6.1 does **not** require the legacy CType migration wizard. That wizard remains only for genuinely old `CType=list` plugin records (see below). Run extension setup after updating:

```bash
composer update anatolkin/anatolkin-mosaic-gallery -W
php vendor/bin/typo3 extension:setup
```

Version 0.6.1 requires no database migration and no permission setup.

Updating from 0.5.0 to 0.5.1 does **not** require this wizard. Version 0.5.1 itself requires no database migration.

On TYPO3 13.4, a compatibility bridge restores frontend rendering and backend editing of leftover plugin records **before** the wizard is run. The wizard remains the recommended permanent conversion. Complete that conversion before upgrading the TYPO3 Core to 14.

The two legacy `list_type` signatures are **not** offered when creating new Plugin / `CType=list` content. New galleries must be created from the **Gallery** group as `CType=mosaicgallery_pi1`. Existing unmigrated plugin records stay editable in FormEngine with an explicit legacy-compatibility label; only the signature already stored on that record is exposed. Frontend TypoScript aliases for both legacy signatures remain active on TYPO3 13 only.

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
2. Choose **Folder** or **Manual images** as the image source.
3. For Folder: select a Fileadmin folder and enable **Include subfolders** if needed.
4. For Manual images: add images through the native TYPO3 FileReference selector; order them manually.
5. Choose filename, modification-time, or random ordering (Folder source). Filename and modification-time sorting support ascending and descending directions.
6. Configure captions, lightbox, Load More, image width, spacing, and visual options.
7. Save the content element. Use **Image metadata** for stable per-image Caption and Alternative text values.

## Current features

- **Folder** image source with optional recursive subfolder inclusion
- **Manual Images** source with native TYPO3 FAL FileReferences, manual ordering, and per-reference crop support
- Filename and modification-time sorting, including ascending and descending directions
- Random display ordering (Folder source)
- Five responsive gallery layouts: Masonry, Mosaic, Patterned Mosaic, Justified Rows, and Uniform Grid
- Configurable Patterned Mosaic density on wide screens
- Configurable image width, gap, frames, frame accents, corner radius, background, shadow, captions, and layout behavior
- Design presets with gallery-specific overrides and a live Gallery/Lightbox design preview
- Optional GLightbox lightbox with per-gallery scoped appearance controls
- Configurable gallery and Lightbox caption presentation
- Progressive image loading with configurable initial batch, Load More step, and Lightbox refresh
- Per-gallery UID-linked Caption and Alternative text metadata overrides
- Images workspace with Grid, List, and Table metadata views
- Live metadata synchronization for Manual Images during add/remove/reorder before Save
- Multilingual metadata workflow integrated with TYPO3 content localization
- Compact responsive backend configuration
- Interface translations for **35 languages**, including major European and Asian languages plus right-to-left Arabic, Hebrew, and Persian

Supported interface locales:

`en`, `de`, `fr`, `es`, `ru`, `ar`, `he`, `fa`, `it`, `nl`, `pl`, `pt`, `pt_BR`, `uk`, `cs`, `sk`, `da`, `sv`, `no`, `fi`, `tr`, `ro`, `hu`, `el`, `bg`, `hr`, `sr`, `ja`, `ko`, `zh`, `zh_CN`, `hi`, `vi`, `th`, `ka`

This release intentionally does **not** claim every TYPO3 regional/legacy locale.

## What's new in 0.6.1

### Optional editor restrictions

TYPO3 administrators can optionally hide individual Mosaic Gallery settings for backend user groups.

Open the backend user group and go to:

**Module Permissions → Custom module options → Anatolkin Mosaic Gallery**

Restrictions are grouped into:

- General restrictions
- Design restrictions

All restrictions are opt-in.

If no Mosaic Gallery restriction is selected, editors retain the same access as before.

Administrators always retain full access.

### Upgrade

No database migration or Upgrade Wizard is required.

No permission setup is required after upgrading.

Existing gallery records and stored FlexForm values remain unchanged.

Hidden controls are not intentionally cleared or rewritten.

## What's new in 0.6.0

Version 0.6.0 adds **Manual Images** — a native TYPO3 FAL FileReference workflow alongside the existing Folder gallery source.

### Manual Images

- Select individual FAL images instead of scanning a folder
- Uses native TYPO3 `sys_file_reference` records with manual ordering
- Multi-select before Save
- Per-image crop editor through TYPO3 FileReferences
- Native FileReference Link, Title, and Alternative Text fields
- Mosaic Caption and Alternative text overrides per image
- Grid, List, and Table metadata editor views
- Live metadata rows during add, remove, and reorder before Save
- Collapse-safe backend behavior on TYPO3 13.4 and 14.3 (collapsed or lazy-loaded FileReference cards do not drop metadata rows)

### Folder mode

- Remains dynamic folder-based gallery generation
- No FileReferences are created merely to render a folder gallery

### Caption semantics (0.6.0)

**Caption** is the short image title/label shown with the gallery. It is **not** the FAL Description field.

| Source | Inherited Caption uses |
|--------|------------------------|
| **Folder** | FAL **File Title** for the current language |
| **Manual Images** | TYPO3 **FileReference Title**, with TYPO3's normal fallback to the underlying **File Title** |

- If Title is empty, inherited Caption is empty.
- **Description is not automatically used as Caption.**
- **Mosaic Custom Caption** overrides inherited Title.
- **Alternative text** remains independent (File or FileReference Alternative Text, with Mosaic Custom / Empty modes).

Description and lightbox-description support are intentionally deferred to a future release.

### Upgrade note from 0.5.x

This is **not** a database migration. Existing content records and metadata overrides are preserved.

Starting with 0.6.0, **Folder inherited Caption uses File Title only**. In 0.5.x, inherited Caption could fall back from Title to Description when Title was empty. After updating, a Folder gallery that relied on Description because its File Title was empty may show **no Caption**.

Safe remedies:

- Set a **File Title** in Filelist metadata, or
- Use a **Mosaic Custom Caption** for that image.

No compatibility shim, record mutation, or Upgrade Wizard is provided for this semantic correction.

### Data preservation

- No database migration is required for 0.6.0.
- Existing Mosaic metadata overrides (`custom` / `inherit` / `empty`) remain valid.
- Existing Folder galleries remain Folder galleries.
- Existing 0.5.x content records do not require conversion.
- Manual Images uses TYPO3 native `sys_file_reference` records when that source is chosen.

Verified on TYPO3 13.4.34 and TYPO3 14.3.6.

## What's new in 0.5.1

Version 0.5.1 is a corrective compatibility and backend UX release for TYPO3 13.4 and 14.3.

Highlights:

- Restores Core-native TYPO3 color controls for Custom design values.
- Fixes native color-picker live-preview lifecycle.
- Fixes persisted color hydration after reload and save.
- Fixes display-proxy checkbox persistence for captions, lightbox, Load More, and Load More frame styling.
- Fixes post-save FormEngine proxy lifecycle, especially on TYPO3 13.
- Improves Design Configurator responsiveness.
- Improves named-preset controls with color and reset actions.
- Uses compact responsive numeric controls.
- Moves Load More controls into the Gallery section.
- Improves upper Layout workspace responsiveness.
- Fixes narrow Folder field overflow while keeping Browse and delete controls visible.
- Verified on TYPO3 13.4.34 and TYPO3 14.3.6.

No database migration is required for 0.5.1. Existing stored gallery values remain compatible.

## What's new in 0.5.0

Version 0.5.0 adds configurable TypoScript creation defaults for new Mosaic Gallery content elements and improves TYPO3 13/14 backend compatibility.

Highlights:

- Adds configurable TypoScript creation defaults for new Mosaic Gallery content elements.
- Preserves explicit first-save values while leaving existing and legacy records unchanged.
- Carries the native TYPO3 FAL metadata fix introduced in 0.4.3.
- Improves Design Configurator creation-default synchronization and design-override handling.
- Hides obsolete TYPO3 13 legacy plugin signatures from new-content creation while retaining edit and runtime compatibility for existing legacy records.
- Keeps Custom-mode color fields Core-native for backend color-control compatibility.
- Improves responsive Design Configurator layout at narrow backend widths.
- Supports TYPO3 13.4 and TYPO3 14.3.

No database migration is required for 0.5.0. Existing Mosaic Gallery records retain their stored settings. TypoScript creation defaults affect new records only.

## What's new in 0.4.3

Version 0.4.3 is a compatibility hotfix for inherited FAL metadata on TYPO3 13.4 and 14.3.

It replaces a custom `sys_file_metadata` QueryBuilder path that could throw `TypeError` on `PDO::PARAM_INT` under TYPO3/DBAL 4 with the native `File::getMetaData()->get()` API. The stable five-key Fluid metadata subset, caption fallback order, alternative-text propagation, and gallery-specific overrides are unchanged. No database migration is required.

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

`{it.caption}` and `{it.alt}` remain the final resolved values after gallery-specific overrides.

**Folder source:** when **Use file metadata as fallback** is enabled and Caption is set to Inherit, the visible caption uses the localized **File Title**. Description is not used automatically.

**Manual Images source:** when Caption is set to Inherit, the visible caption uses the **FileReference Title** (with TYPO3's normal fallback to File Title). Description is not used automatically.

## Image metadata behavior

The Image metadata editor stores gallery-specific values on the current `tt_content` record. Entries are linked to `sys_file.uid`, so Caption and Alternative text overrides remain attached to the correct image when ordering changes or files are added or removed.

For each image:

- **Caption** can inherit the File or FileReference **Title** (depending on source) or use a gallery-specific custom value.
- **Alternative text** can inherit File or FileReference Alternative Text, use a custom value, or be explicitly empty for a decorative image.
- **Use file metadata as fallback** (Folder source) allows metadata maintained through TYPO3 Filelist to supply inherited Title and Alternative Text values.

For Folder galleries, the editor reads images from the saved folder settings. For Manual Images, images come from native FileReferences on the content element. Save source and ordering changes before editing metadata.

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

Configured site languages are discovered from TYPO3 Site Configuration. The bundled XLIFF interface languages translate the extension UI and do not limit the site's available content languages.

## Compatibility and release status

Anatolkin Mosaic Gallery 0.6.1 targets TYPO3 13.4 and TYPO3 14.3.

Existing folder galleries, legacy Quick captions, and the `list_type` to `CType` migration path introduced in 0.3.0 remain supported. On TYPO3 13.4, unmigrated plugin records remain usable before that wizard is run. TYPO3 14 installations should complete the wizard first and then use dedicated `CType=mosaicgallery_pi1` records only.

Existing galleries without the newer layout and design settings continue to use backward-compatible defaults. Metadata conversion remains an explicit editor action and is not automatically required.

## License

Anatolkin Mosaic Gallery is released under the [GNU General Public License v2.0 or later](LICENSE).
