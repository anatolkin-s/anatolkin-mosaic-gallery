# Mosaic Gallery for TYPO3

Responsive masonry-style image gallery for **TYPO3 v13 LTS**.

The extension reads images from a FAL folder and displays them in a masonry grid
(Masonry.js + imagesLoaded) with an optional **GLightbox** overlay.

- ✅ Masonry layout (Masonry.js + imagesLoaded)  
- ✅ Uses a FAL folder as image source  
- ✅ Optional lightbox (GLightbox)  
- ✅ Simple design options (gap, columns, captions, frame / background)  
- ✅ Works with any TYPO3 frontend (Bootstrap Package or your own sitepackage)  

Current version: **0.1.8**

---

## Requirements

- TYPO3 **v13.4 LTS**  
- PHP **8.1 – 8.3**  
- Composer-based TYPO3 installation  

---

## Installation

### 1. Install via Composer

If the package is available on Packagist, installation will be as simple as:

    composer require anatolkin/anatolkin-mosaic-gallery:^0.1

If you want to use the extension directly from GitHub as a VCS repository,
add this to your project’s `composer.json` first (in the root TYPO3 project):

    {
      "repositories": [
        {
          "type": "vcs",
          "url": "https://github.com/anatolkin-s/anatolkin-mosaic_gallery.git"
        }
      ]
    }

Then run:

    composer require anatolkin/anatolkin-mosaic-gallery:^0.1.8

After that, if needed:

    composer install
    composer dump-autoload

---

### 2. Activate the extension in TYPO3

1. Log in to the TYPO3 backend.  
2. Open **Admin Tools → Extensions**.  
3. Search for **“Anatolkin Mosaic Gallery”** (`anatolkin_mosaic_gallery`).  
4. Click the **Activate** icon if it is not already active.  

---

### 3. Include the TypoScript setup

The extension ships its own TypoScript that:

- registers CSS for the gallery,  
- registers JS for Masonry / imagesLoaded / GLightbox,  
- configures the Fluid template.

Include it once on your **site root**:

1. Go to the **Template** module.  
2. Select your **site root** page in the page tree.  
3. Click **“Edit the whole template record”**.  
4. Open the **“Includes”** tab.  
5. In **“Include static (from extensions)”** add:

       Anatolkin Mosaic Gallery (EXT:anatolkin_mosaic_gallery)

6. Save the template.  

If you use a custom **sitepackage**, you can instead import the TypoScript there:

    @import 'EXT:anatolkin_mosaic_gallery/Configuration/TypoScript/setup.typoscript'

---

### 4. (Optional) GLightbox from CDN

By default, Anatolkin Mosaic Gallery loads GLightbox, Masonry and imagesLoaded from the
extension’s own `Resources/Public` assets.

If you prefer to load GLightbox from a CDN instead, you can override this
in your TypoScript Setup, for example:

    page.includeCSS.mg_glightbox = https://cdn.jsdelivr.net/npm/glightbox/dist/css/glightbox.min.css
    page.includeJSFooterlibs.mg_glightbox = https://cdn.jsdelivr.net/npm/glightbox/dist/js/glightbox.min.js

Where to put it:

- **Template module → site root → Edit whole template record → Setup tab → paste → Save**,  
  or  
- in your **sitepackage** TypoScript Setup file.

After changing TypoScript, clear caches.

Example (CLI, adjust path for your project):

    sudo -u webuser -H /usr/bin/php /var/www/typo3/typo3-site/vendor/bin/typo3 cache:flush

---

## Creating your first gallery

1. Go to the **Page** module.  
2. Choose the page where you want to show the gallery.  
3. Click **“Create new content element”**.  
4. On the **“Plugins”** tab choose **“Anatolkin Mosaic Gallery”**.  
5. In the plugin settings (**Plugin** tab):

   - **Image folder** – select a folder under `fileadmin/` that contains your images.  
     Example: `fileadmin/Anatolkin/photo/people/portraits/children/`.  

   - **Columns** – number of columns for large screens  
     (default: 3).  

   - **Gutter (px)** – space between tiles  
     (default: 16 px).  

   - **Show captions** – if enabled, the caption/description will be shown under each image  
     (using FAL metadata or provided captions).  

   - **Open full image in new tab** – if enabled, clicks on images open the file in a new tab  
     (if lightbox is enabled, GLightbox will be used instead).  

   - **Enable lightbox** (if present in the plugin options) – enable/disable GLightbox overlay.  

6. Save the content element.  
7. View the page in the frontend — the gallery should appear with a responsive masonry layout.  

---

## Styling and advanced options

The gallery uses a few CSS variables on the root `.anatolkin-mosaic-gallery` element:

    .mosaic-gallery {
      --gap: 12px;              /* space between tiles */
      --radius: 6px;            /* border-radius for images */
      --frame-width: 0px;       /* border width */
      --frame-style: solid;     /* border style */
      --frame-color: #000;      /* border color */
      --bg: transparent;        /* background color (container/tiles) */
    }

You can override them in your sitepackage CSS, for example:

    .mosaic-gallery.mg-portfolio {
      --gap: 24px;
      --radius: 12px;
      --frame-width: 1px;
      --frame-style: solid;
      --frame-color: #dddddd;
      --bg: #f7f7f7;
    }

Then assign the extra class to the content element (e.g. `mg-portfolio`).

---

## Troubleshooting

**No images appear**

- Verify the content element points to a valid FAL folder with readable images.  
- Make sure the storage that contains your `fileadmin/` is online.  
- Check that the extension is active and the TypoScript is included on the site root.  

**Lightbox does not open**

- Ensure that the TypoScript from the extension (or your CDN override) is included.  
- Clear the TYPO3 caches after changing TypoScript.  
- Make sure no CSP (Content Security Policy) blocks JS/CSS from GLightbox/CDN.  

**CLI permissions**

Run TYPO3 CLI commands as the web user. Example:

    sudo -u webuser -H bash -lc 'cd /var/www/typo3/typo3-site && composer show anatolkin/anatolkin-mosaic-gallery | head -8'

To flush caches:

    sudo -u webuser -H /usr/bin/php /var/www/typo3/typo3-site/vendor/bin/typo3 cache:flush

(Replace `/var/www/typo3/typo3-site` with your project path if it differs.)

---

## Changelog (short)

### v0.1.8

- First public GitHub release of **Anatolkin Mosaic Gallery for TYPO3 v13**.  
- Bundled JS/CSS for Masonry, imagesLoaded and GLightbox.  
- Fluid template for masonry gallery with optional lightbox.  
- Basic README with installation and usage instructions.  

---

## License

Released under the **MIT License**.  
See the `LICENSE` file for details.




