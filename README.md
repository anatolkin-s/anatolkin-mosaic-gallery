# Anatolkin Mosaic Gallery

**Anatolkin Mosaic Gallery** is a masonry-like image gallery extension for TYPO3 CMS 13.  
It uses TYPO3 FAL (File Abstraction Layer) as the image source and can optionally integrate with GLightbox for a modern lightbox experience.

The extension is designed to:

- provide a clean masonry-style gallery layout,
- be easy to use for editors (simple “folder as source” configuration),
- integrate nicely into TYPO3’s backend (icons, wizard, Page TSconfig),
- behave predictably on real-world TYPO3 13 installations (Composer & classic).

---

## Features

- Masonry-like image gallery using FAL
- Optional integration with GLightbox
- Uses a **folder** (and optionally subfolders) as a source of images
- “Load more” behaviour for large galleries (progressive loading)
- Separate plugin for galleries (list_type: `mosaicgallery_pi1`)
- TYPO3 13-compatible TCA, TypoScript and TSconfig wiring
- Backwards compatible with older content elements using the legacy list_type

---

## Requirements

- TYPO3: **13.4.0 – 13.99.99**
- PHP: **8.1 – 8.3**

As declared in:

- `ext_emconf.php`
- `composer.json`

---

## Installation

### 1. Composer-based installations (recommended)

In your TYPO3 project root, run:

```bash
composer require anatolkin/anatolkin-mosaic-gallery:^0.1.10
```

Then run TYPO3 extension setup and clear caches:

```bash
php vendor/bin/typo3 extension:setup
php vendor/bin/typo3 cache:flush
```

The extension key is:

```text
anatolkin_mosaic_gallery
```

### 2. Classic / non-Composer installation

If you are using a non-Composer TYPO3 installation:

1. Download the extension from the TYPO3 Extension Repository (TER) as a `.zip`.
2. Extract it into your `typo3conf/ext/` directory so that you have:

   ```text
   typo3conf/ext/anatolkin_mosaic_gallery/
   ```

3. Go to **Admin Tools → Extensions** and activate **Anatolkin Mosaic Gallery**.
4. In *Install Tool* or via the Extension Manager, run the database and cache update if required.

---

## Static TypoScript

To make the gallery work, you need to include the static TypoScript set.

1. Go to the **Template** module in the TYPO3 backend.
2. Select your main site root.
3. Click on **“Edit the whole template record”**.
4. In the **Includes** (static templates) section, add:

   > **Anatolkin Mosaic Gallery (Assets & Masonry)**

This static include is registered via:

```php
ExtensionManagementUtility::addStaticFile(
    'anatolkin_mosaic_gallery',
    'Configuration/TypoScript',
    'Anatolkin Mosaic Gallery (Assets & Masonry)'
);
```

---

## Page TSconfig and New Content Element Wizard

The extension automatically registers its Page TSconfig to appear in the “New Content Element” wizard.

- `ext_localconf.php` imports:

  ```php
  ExtensionManagementUtility::addPageTSConfig(
      "@import 'EXT:anatolkin_mosaic_gallery/Configuration/TsConfig/Page/NewContentElementWizard.typoscript'"
  );
  ```

- `Configuration/TsConfig/Page/NewContentElementWizard.typoscript` registers the plugin in a dedicated **“Gallery”** group:

  ```typoscript
  mod.wizards.newContentElement.wizardItems.gallery {
    header = Gallery
    position = after:plugins

    elements.anatolkin_mosaic_gallery {
      iconIdentifier = mosaic-gallery-plugin
      title = Anatolkin Mosaic Gallery
      description = Masonry-like image gallery using FAL with optional lightbox

      tt_content_defValues {
        CType = list
        list_type = mosaicgallery_pi1
      }
    }

    show := addToList(anatolkin_mosaic_gallery)
  }
  ```

You should see **“Anatolkin Mosaic Gallery”** as a selectable content element under the “Gallery” group in the New Content Element wizard.

---

## Plugin and TypoScript wiring

### Extension key and plugin signature

- Extension key: `anatolkin_mosaic_gallery`
- Plugin name: `Pi1`
- Final list_type: `mosaicgallery_pi1`

TCA (simplified):

```php
$extensionKey = 'anatolkin_mosaic_gallery';
$pluginName = 'Pi1';
$pluginSignature = str_replace('_', '', $extensionKey) . '_' . strtolower($pluginName); // mosaicgallery_pi1
```

This plugin signature is used in:

- `tt_content.list_type`
- TypoScript content object registration
- FlexForm registration
- New Content Element wizard configuration

### TypoScript setup

The extension imports its main TypoScript via:

```typoscript
@import "EXT:anatolkin_mosaic_gallery/Configuration/TypoScript/setup.typoscript"
```

In the setup, you will find the base configuration:

```typoscript
plugin.tx_mosaicgallery_pi1 {
  view {
    templateRootPaths.10 = EXT:anatolkin_mosaic_gallery/Resources/Private/Templates/
    partialRootPaths.10  = EXT:anatolkin_mosaic_gallery/Resources/Private/Partials/
    layoutRootPaths.10   = EXT:anatolkin_mosaic_gallery/Resources/Private/Layouts/
  }

  settings {
    source    = folder
    folder    = fileadmin/gallery/
    recursive = 1
    gap       = 12
  }
}
```

And the content object registration:

```typoscript
tt_content.list.20.mosaicgallery_pi1 = USER
tt_content.list.20.mosaicgallery_pi1 {
  userFunc      = TYPO3\CMS\Extbase\Core\Bootstrap->run
  vendorName    = Anatolkin
  extensionName = MosaicGallery
  pluginName    = Pi1
}
```

### Backwards compatibility

If you have older content elements using the old list_type `anatolkinmosaicgallery_pi1`, they are still supported:

```typoscript
tt_content.list.20.anatolkinmosaicgallery_pi1 < tt_content.list.20.mosaicgallery_pi1
```

This avoids runtime errors like:

```text
No Content Object definition found at TypoScript object path "tt_content.list.20.anatolkinmosaicgallery_pi1"
```

---

## How to use

1. **Prepare an image folder**

   - In the **Filelist** module, create a folder for your gallery, for example:

     ```text
     fileadmin/gallery/
     ```

   - Upload images to that folder (and optionally to subfolders).

2. **Create a gallery content element**

   - Go to the **Page** module.
   - On the desired page, click **“New Content Element”**.
   - Open the **“Gallery”** group.
   - Choose **“Anatolkin Mosaic Gallery”**.

3. **Configure the plugin**

   - Switch to the **Plugin** tab of the content element.
   - Select **source = folder** (default).
   - Choose the folder containing your images (e.g. `fileadmin/gallery/`).
   - Optionally enable recursive loading to include subfolders.
   - Adjust other settings as needed (gap, number of images per “page”, etc. if available in the FlexForm).

4. **Save and view the page**

   - Clear frontend cache if needed.
   - Open the page in the frontend and verify that the masonry gallery appears.

---

## Layout, masonry and “Load more”

- The layout is based on a masonry-like CSS grid.
- The `gap` setting (in TypoScript / FlexForm) controls the spacing between tiles.
- “Load more” behavior allows progressively loading additional images instead of rendering everything at once.
- Depending on your theme and CSS, you might want to adjust:
  - breakpoints,
  - number of columns,
  - gap size,
  - animation or hover effects.

Future releases will focus on exposing more of these options via Constants and FlexForm.

---

## Troubleshooting

### The plugin does not appear in the New Content Element wizard

- Make sure the extension is **installed and active**.
- Check that Page TSconfig import is in place (it is done automatically via `ext_localconf.php`).
- Clear all caches:

  ```bash
  php vendor/bin/typo3 cache:flush
  ```

- Log out and log in again to the backend if necessary.

### Error: “No Content Object definition found at TypoScript object path …”

- This usually means TypoScript for the plugin has not been included.
- Ensure that the static TypoScript set  
  **“Anatolkin Mosaic Gallery (Assets & Masonry)”**  
  is included in your site template.
- If you have very old content elements, the alias

  ```typoscript
  tt_content.list.20.anatolkinmosaicgallery_pi1 < tt_content.list.20.mosaicgallery_pi1
  ```

  should handle them. If not, please open an issue with details.

### FlexForm file not found / 500 errors in backend

- Make sure the extension path is correct and the FlexForm is referenced as:

  ```php
  'FILE:EXT:anatolkin_mosaic_gallery/Configuration/FlexForms/MosaicGallery.xml'
  ```

- If you manually copied files between instances, verify that the `Configuration/FlexForms/` directory exists and contains `MosaicGallery.xml`.

---

## Development

The extension follows a standard TYPO3 Extbase + Fluid structure:

- PHP namespace: `Anatolkin\MosaicGallery`
- Controller: `Classes/Controller/GalleryController.php`
- Templates: `Resources/Private/Templates/`
- Partials: `Resources/Private/Partials/`
- Layouts: `Resources/Private/Layouts/`

Repository:

- GitHub: `https://github.com/anatolkin-s/anatolkin-mosaic-gallery`

If you want to develop locally:

```bash
git clone https://github.com/anatolkin-s/anatolkin-mosaic-gallery.git
```

Place the extension under your TYPO3 project’s `packages/` or `typo3conf/ext/` (depending on your setup) and register it accordingly.

---

## Changelog

The full changelog is available in the **Releases** section on GitHub:

- `https://github.com/anatolkin-s/anatolkin-mosaic-gallery/releases`

Key releases:

- **v0.1.10** – Stable TYPO3 13 support, unified extension key, consistent list_type, improved wiring.

---

## License

This extension is licensed under the **MIT License**.

See the `LICENSE` file for details.
