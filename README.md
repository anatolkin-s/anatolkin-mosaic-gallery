# Anatolkin Mosaic Gallery

Anatolkin Mosaic Gallery is a masonry-like image gallery extension for TYPO3 CMS 13 that uses the TYPO3 File Abstraction Layer (FAL) and an optional GLightbox-based lightbox.

It is designed to be:

- **Simple to install** – shipped via Composer and a single static TypoScript include.
- **Safe for editors** – everything is configured through one content element.
- **Nice by default** – sensible defaults for spacing, frames, captions and lightbox theme.
- **Flexible** – supports folders, categories and fine‑tuning of the visual style.

_Current stable version: **0.1.14**_

---

## Requirements

- TYPO3: **13.4.0 – 13.99.99**
- PHP: **8.1 – 8.3**

(As declared in `composer.json` and `ext_emconf.php`.)

---

## Installation

### 1. Install via Composer

```bash
composer require anatolkin/anatolkin-mosaic-gallery:^0.1.14
```

Run TYPO3 extension setup and clear the caches:

```bash
php vendor/bin/typo3 extension:setup
php vendor/bin/typo3 cache:flush
```

### 2. Include the static TypoScript set

In the TYPO3 backend:

1. Go to **Web → TypoScript** (or **Site Configuration / Templates**, depending on your setup).
2. Open your main site template record.
3. In **Includes → Include TypoScript sets**, add:

   > **Anatolkin Mosaic Gallery (Assets & Masonry)**  
   > (`anatolkin_mosaic_gallery`)

4. Save the record and clear caches.

This will register:

- the main gallery TypoScript setup,
- CSS + JS assets (Masonry, imagesLoaded, GLightbox and the gallery theme),
- a small amount of configuration for the content element.

### 3. Place the content element

The extension adds a dedicated entry to the **New Content Element Wizard**:

- Group: **Gallery**
- Element: **Anatolkin Mosaic Gallery**
- Icon: mosaic tile with the letter “A”

Editors can:

1. Choose **Folder (fileadmin)** or **Categories** as the source.
2. Select a folder or categories.
3. Configure layout options on the **Plugin** and **Design** tabs.

---

## Features

### Masonry layout

- Uses **Masonry** + **imagesLoaded** to create a responsive grid.
- Supports variable image heights and different aspect ratios.
- Keeps gaps minimal and automatically reflows on resize.

### “Load more” pagination

- You can define:
  - **Items per page** – how many tiles are shown initially.
  - **Load step** – how many additional tiles are revealed on each click.
- Only the first N items are visible on first load; the rest are rendered with the `is-hidden` class.
- Clicking **Load more** reveals the next batch and triggers a proper Masonry relayout:
  - no large empty gaps between “old” and “new” tiles;
  - the button is removed automatically when there is nothing left to load.

This behaviour is controlled by:

- server-side logic (marking items as hidden or visible) and
- client-side JS (`Resources/Public/Js/mosaic-init.js`).

### Optional lightbox (GLightbox)

- Uses **GLightbox** for a modern, responsive lightbox.
- Can be enabled/disabled per content element.
- The lightbox theme automatically mirrors:
  - frame color and width,
  - border‑radius,
  - (optionally) tile background color.
- Additional color options for:
  - overlay,
  - navigation arrows,
  - close button,
  - caption text and background.

### Visual customization

Per content element, you can configure:

- **Gutter (gap)** between tiles.
- **Corner radius**.
- **Frame**:
  - color,
  - width,
  - style (solid, dashed, etc.).
- **Background application**:
  - apply background to the whole gallery,
  - only to tiles,
  - both, or none.
- **Shadow** (on/off).
- **Captions** (on/off).
- **Caption alignment** and source (FAL metadata or manual captions).

### Data sources

- **Folder mode** – use a FAL folder (optionally recursive).
- **Category mode** (if enabled) – render images by categories.

---

## Usage overview

1. Create or choose a page for your gallery.
2. Add content element **Anatolkin Mosaic Gallery**.
3. On the **Plugin** tab, configure:

   - **Source**: folder or categories.
   - **Items per page**: e.g. `18`.
   - **Load step**: e.g. `12` (or `0` to load all remaining items at once).
   - **Max image width**: to control file size and layout.
   - **Lightbox**: enable if you want click‑to‑zoom behaviour.
   - **Captions**: enable if you want image titles shown under the tiles.

4. Tune the **Design** tab (per element):

   - frame color, width, style and border radius,
   - background color and where it is applied (container/tiles/both),
   - shadow,
   - lightbox overlay, arrows, close button and caption colors.

5. Save and clear the TYPO3 caches (if needed).

---

## How “Load more” works

At render time the controller prepares a list of items. For each item it marks an internal `hidden` flag based on the **items per page** and **load step** settings.

In the Fluid template (`Resources/Private/Templates/Gallery/List.html`):

- visible items are rendered as:

  ```html
  <figure class="mosaic-item">
  ```

- hidden items are rendered as:

  ```html
  <figure class="mosaic-item is-hidden">
  ```

In `mosaic-init.js`:

- Masonry is initialised on the grid after all currently visible images are loaded.
- Clicking **Load more**:
  - finds a batch of `.mosaic-item.is-hidden` elements,
  - removes `is-hidden` from that batch,
  - calls `msnry.appended(reveal)` and `msnry.layout()`,
  - reloads GLightbox if it is enabled,
  - removes the button when no hidden items remain.

This combination keeps the grid tight and avoids large empty bands in the layout.

---

## Backwards compatibility

- Old `list_type` values are still supported:

  ```typoscript
  tt_content.list.20.anatolkinmosaicgallery_pi1 < tt_content.list.20.mosaicgallery_pi1
  ```

- Existing content elements created with earlier versions (0.1.6–0.1.10) continue to work.
- Extension key is consistently **`anatolkin_mosaic_gallery`** everywhere.

---

## Changelog (short)

### 0.1.14 (stable)

- New default EDITABLE via Backend visual theme:
  - frame color `#b40000`, width `2px`, solid;
  - border radius `6px`, optional shadow;
  - background color `#e5e5e5` applied to container and tiles;
  - lightbox overlay `#2c5222` with opacity `0.92`;
  - white arrows, close button and caption text;
  - caption background `#b40000`.
- Cleaned up FlexForm labels and defaults for **Plugin** and **Design** tabs.
- Internal documentation and README updated for the stable release.

### 0.1.13

- Fix: prevent PHP warning *“Undefined array key 'title'”* when FAL metadata is missing or not translated.

### 0.1.12

- Fixes the “Load more” pagination and removes Masonry layout gaps when loading additional items.
- Improves Masonry re‑layout on resize.
- Adds an option to style the “Load more” button with the same frame as gallery tiles.

### 0.1.11

- Refined **“Load more”** behaviour:
  - now shows only the configured number of items on first load;
  - reveals additional items in proper Masonry batches;
  - removes the button when no items remain.
- Fixed layout gaps that could appear between initial and newly loaded tiles.
- Small internal clean‑ups in JS to better handle lightbox re‑initialisation.

### 0.1.10

- Unified extension key: `anatolkin_mosaic_gallery` (Composer + TYPO3).
- Unified plugin signature: `mosaicgallery_pi1`.
- Added TypoScript alias for older `list_type` values.
- Cleaned up TypoScript and TSconfig wiring.
- Improved backend integration (wizard group, icons, labels).
- Hardened FlexForm and Extbase plugin wiring for TYPO3 13.

(Older versions are not listed here in detail.)

---

## Support / issues

Please report bugs or feature requests on GitHub:

- Issues: `https://github.com/anatolkin-s/anatolkin-mosaic-gallery/issues`
- Source: `https://github.com/anatolkin-s/anatolkin-mosaic-gallery`

When reporting a problem, please include:

- TYPO3 version,
- PHP version,
- screenshot of the content element configuration,
- any relevant log messages or exception codes,
- and (if possible) a short description of the folder/category structure.
