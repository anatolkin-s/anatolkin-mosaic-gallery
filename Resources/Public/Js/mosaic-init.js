(function () {
  var activeThemeClassPrefix = "mg-glx-active-";

  function clearActiveMosaicLightboxTheme() {
    Array.prototype.slice.call(document.documentElement.classList).forEach(function (className) {
      if (className.indexOf(activeThemeClassPrefix) === 0) {
        document.documentElement.classList.remove(className);
      }
    });
  }

  function activateMosaicLightboxTheme(themeClass) {
    clearActiveMosaicLightboxTheme();
    document.documentElement.classList.add(themeClass);
  }

  function deactivateMosaicLightboxTheme(themeClass) {
    document.documentElement.classList.remove(themeClass);
  }

  function hexToRgb(hex) {
    if (!hex) return { r: 0, g: 0, b: 0 };
    if (/^rgba?\(/i.test(hex)) {
      var m = hex.match(/(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
      return { r: +(m && m[1] || 0), g: +(m && m[2] || 0), b: +(m && m[3] || 0) };
    }
    var s = String(hex).replace("#", "").trim();
    if (s.length === 3) {
      s = s.split("").map(function (c) { return c + c; }).join("");
    }
    var n = parseInt(s, 16);
    if (isNaN(n)) return { r: 0, g: 0, b: 0 };
    return {
      r: (n >> 16) & 255,
      g: (n >> 8) & 255,
      b: n & 255
    };
  }

  function toRgba(hex, a) {
    if (!hex) return "rgba(0,0,0," + (a || 0.92) + ")";
    if (/^rgba?\(/i.test(hex)) return hex;
    var c = hexToRgb(hex);
    var alpha = (typeof a === "number" ? a : parseFloat(a));
    if (isNaN(alpha)) alpha = 0.92;
    return "rgba(" + c.r + "," + c.g + "," + c.b + "," + alpha + ")";
  }

  function normalizeAlpha(value, fallback) {
    if (value === null || typeof value === "undefined" || String(value).trim() === "") {
      return fallback;
    }
    var alpha = Number(value);
    if (isNaN(alpha)) return fallback;
    return Math.min(1, Math.max(0, alpha));
  }

  function normalizeCaptionAlign(value) {
    return ["left", "center", "right"].indexOf(value) !== -1 ? value : "left";
  }

  function resolveCaptionSize(value) {
    return {
      small: "0.875rem",
      normal: "1rem",
      large: "1.125rem"
    }[value] || "1rem";
  }

  function resolveCaptionStyle(value) {
    return {
      regular: { fontStyle: "normal", fontWeight: "400" },
      italic: { fontStyle: "italic", fontWeight: "400" },
      strong: { fontStyle: "normal", fontWeight: "600" }
    }[value] || { fontStyle: "normal", fontWeight: "400" };
  }

  function normalizeLayoutMode(value) {
    return ["masonry", "mosaic", "grid"].indexOf(value) !== -1 ? value : "masonry";
  }

  function injectCss(id, css) {
    if (document.getElementById(id)) return;
    var st = document.createElement("style");
    st.id = id;
    st.type = "text/css";
    st.textContent = css;
    document.head.appendChild(st);
  }

  function ensureLightboxFrameWrappers(root) {
    var scope = root && typeof root.querySelectorAll === "function" ? root : document;
    scope.querySelectorAll(".gslide-image img").forEach(function (image) {
      if (image.parentElement && image.parentElement.classList.contains("mg-glx-frame")) return;
      var frame = document.createElement("span");
      frame.className = "mg-glx-frame";
      image.parentNode.insertBefore(frame, image);
      frame.appendChild(image);
    });
  }

  function prepareLightboxTheme(root, index) {
    var ds = root.dataset;
    var themeClass = activeThemeClassPrefix + index;
    var scope = "html." + themeClass + " ";

    var cs = getComputedStyle(root);
    function gv(n, d) {
      return (cs.getPropertyValue(n) || d).toString().trim();
    }

    var frameColor = gv("--frame-color", "transparent");
    var frameAccent = gv("--frame-accent", frameColor);
    var frameWidth = gv("--frame-width", "0px");
    var frameWidthValue = Math.max(0, parseFloat(frameWidth) || 0);
    var frameKeyline = (frameWidthValue === 0 ? 0 : Math.min(frameWidthValue, Math.max(1, frameWidthValue * 0.1))) + "px";
    var frameQuarter = (frameWidthValue * 0.25) + "px";
    var frameThird = (frameWidthValue / 3) + "px";
    var frameForty = (frameWidthValue * 0.4) + "px";
    var frameFortyFive = (frameWidthValue * 0.45) + "px";
    var frameSixty = (frameWidthValue * 0.6) + "px";
    var frameTwoThirds = (frameWidthValue * 2 / 3) + "px";
    var frameThreeQuarters = (frameWidthValue * 0.75) + "px";
    var frameBudget = frameWidthValue + "px";
    var frameStyle = root.getAttribute("data-frame-style") || "none";
    var nativeFrameStyles = ["solid", "dashed", "dotted"];
    var semanticFrameStyles = ["double", "groove", "ridge", "triple", "doubleOuterStrong", "doubleInnerStrong", "gallery"];
    var safeFrameStyle = nativeFrameStyles.indexOf(frameStyle) !== -1 ? frameStyle : "none";
    var semanticFrame = semanticFrameStyles.indexOf(frameStyle) !== -1;
    var semanticShadow = {
      double: "inset 0 0 0 " + frameFortyFive + " " + frameAccent + ",inset 0 0 0 " + frameBudget + " " + frameColor,
      groove: "inset 0 0 0 " + frameKeyline + " color-mix(in srgb," + frameColor + " 75%,#000),inset 0 0 0 " + frameSixty + " " + frameAccent + ",inset 0 0 0 " + frameBudget + " " + frameColor,
      ridge: "inset 0 0 0 " + frameKeyline + " color-mix(in srgb," + frameAccent + " 65%,#fff),inset 0 0 0 " + frameSixty + " " + frameColor + ",inset 0 0 0 " + frameBudget + " " + frameAccent,
      triple: "inset 0 0 0 " + frameThird + " " + frameColor + ",inset 0 0 0 " + frameTwoThirds + " " + frameAccent + ",inset 0 0 0 " + frameBudget + " " + frameColor,
      doubleOuterStrong: "inset 0 0 0 " + frameThreeQuarters + " " + frameColor + ",inset 0 0 0 " + frameBudget + " " + frameAccent,
      doubleInnerStrong: "inset 0 0 0 " + frameQuarter + " " + frameAccent + ",inset 0 0 0 " + frameBudget + " " + frameColor,
      gallery: "inset 0 0 0 " + frameKeyline + " color-mix(in srgb," + frameColor + " 35%," + frameAccent + "),inset 0 0 0 " + frameForty + " " + frameAccent + ",inset 0 0 0 " + frameBudget + " " + frameColor
    }[frameStyle] || "none";
    if (frameWidthValue <= 0) {
      safeFrameStyle = "none";
      semanticShadow = "none";
    }

    var radius = (function (v) {
      v = (v || "0").toString().trim();
      return v.endsWith("px") ? v : (parseInt(v, 10) || 0) + "px";
    })(gv("--radius", "0"));

    var bgApply = (root.getAttribute("data-apply-bg") || "").toLowerCase();
    var bgColor = gv("--bg", "transparent");
    var tileBg = (bgApply === "tiles" || bgApply === "both") ? bgColor : "transparent";
    var captionBackground = toRgba(
      ds.lbCaptionBg || "rgba(0,0,0,0.75)",
      normalizeAlpha(ds.lbCaptionBgAlpha, 1)
    );
    var captionAlign = normalizeCaptionAlign(ds.lbCaptionAlign);
    var captionSize = resolveCaptionSize(ds.lbCaptionSize);
    var captionStyle = resolveCaptionStyle(ds.lbCaptionStyle);
    var captionLabelMargins = {
      left: "margin-left:0!important;margin-right:auto!important;",
      center: "margin-left:auto!important;margin-right:auto!important;",
      right: "margin-left:auto!important;margin-right:0!important;"
    }[captionAlign];

    var css =
      scope + ".goverlay{background:" +
      toRgba(ds.lbOverlay || "#000000", ds.lbOverlayAlpha || "0.92") +
      "!important;}" +
      scope + ".glightbox-clean .gclose path{fill:" +
      (ds.lbClose || "#FFFFFF") +
      "!important;}" +
      scope + ".glightbox-clean .gnext path," + scope + ".glightbox-clean .gprev path{fill:" +
      (ds.lbNav || "#FFFFFF") +
      "!important;}" +
      scope + ".glightbox-container .gslide-title," + scope + ".glightbox-container .gslide-desc{color:" +
      (ds.lbCaption || "#FFFFFF") +
      "!important;" +
      "text-align:" + captionAlign + "!important;" +
      "font-size:" + captionSize + "!important;" +
      "font-style:" + captionStyle.fontStyle + "!important;" +
      "font-weight:" + captionStyle.fontWeight + "!important;" +
      "line-height:1.35!important;" +
      "margin:0!important;}" +
      scope + ".glightbox-clean .gslide-description{background:transparent!important;}" +
      scope + ".glightbox-clean .gdesc-inner{background:" +
      captionBackground +
      "!important;" +
      "padding:0.65rem 0.8rem!important;" +
      "box-sizing:border-box;" +
      "width:fit-content!important;" +
      "max-width:100%!important;" +
      "border-radius:4px;" +
      "overflow-wrap:anywhere;" +
      captionLabelMargins +
      "}" +
      scope + ".glightbox-container .gslide-title + .gslide-desc{margin-top:0.3rem!important;}" +
      scope + ".glightbox-container .gslide-image .mg-glx-frame{" +
      "--mg-lightbox-tile-bg:" + tileBg + ";" +
      "display:inline-flex;" +
      "line-height:0;" +
      "margin:auto;" +
      "max-width:100%;" +
      "position:relative;" +
      "border:" + (semanticFrame ? "0 solid" : frameWidth + " " + safeFrameStyle) + " " + frameColor + " !important;" +
      "border-radius:" + radius + " !important;" +
      "background:" + tileBg + " !important;" +
      "box-sizing:border-box;" +
      "}" +
      scope + ".glightbox-container .gslide-image .mg-glx-frame img{" +
      "border:0!important;" +
      "border-radius:inherit!important;" +
      "background:transparent!important;" +
      "box-sizing:border-box;" +
      "display:block;" +
      "margin:0!important;" +
      "max-width:100%;" +
      "}" +
      (semanticFrame ? scope + ".glightbox-container .gslide-image .mg-glx-frame::after{" +
      "content:\"\";" +
      "position:absolute;" +
      "inset:0;" +
      "pointer-events:none;" +
      "border-radius:inherit;" +
      "box-shadow:" + semanticShadow + " !important;" +
      "}"
      : "");

    injectCss("mg-glx-theme-" + index, css);
    return themeClass;
  }

  document.addEventListener("DOMContentLoaded", function () {
    var containers = document.querySelectorAll(".mosaic-gallery");
    if (!containers.length) return;

    function waitForImages(images, callback, timeoutMs) {
      var list = Array.prototype.slice.call(images || []);
      var finished = false;
      var timeoutId = null;
      var finish = function () {
        if (finished) return;
        finished = true;
        if (timeoutId) clearTimeout(timeoutId);
        callback();
      };

      if (!list.length) {
        finish();
        return;
      }

      timeoutId = setTimeout(finish, timeoutMs || 10000);

      if (typeof window.imagesLoaded === "function") {
        try {
          window.imagesLoaded(list, finish);
          return;
        } catch (e) {
          // Fall through to native image events.
        }
      }

      var remaining = list.length;
      list.forEach(function (img) {
        var done = function () {
          img.removeEventListener("load", done);
          img.removeEventListener("error", done);
          remaining -= 1;
          if (remaining === 0) finish();
        };
        if (img.complete) {
          done();
        } else {
          img.addEventListener("load", done);
          img.addEventListener("error", done);
        }
      });
    }

    containers.forEach(function (container, index) {
      var gap     = parseInt(container.style.getPropertyValue("--gap") || "12", 10);
      var step    = parseInt(container.getAttribute("data-step") || "0", 10);
      var enable  = container.getAttribute("data-lightbox") === "1";
      var group   = container.getAttribute("data-group") || "gallery";
      var layoutMode = normalizeLayoutMode(container.getAttribute("data-layout-mode"));
      var grid    = container.querySelector(".mosaic-grid") || container;
      var lightbox = null;
      var msnry = null;
      var themeClass = enable ? prepareLightboxTheme(container, index) : null;

      function markLayoutReady() {
        container.classList.remove("is-layout-pending");
        container.classList.add("is-layout-ready");
      }

      function tryInitLightbox() {
        if (!enable) return;
        if (window.GLightbox) {
          try {
            lightbox = GLightbox({
              selector: "a[data-gallery=\"" + group + "\"]",
              onOpen: function () {
                activateMosaicLightboxTheme(themeClass);
                ensureLightboxFrameWrappers(document);
              },
              onClose: function () {
                deactivateMosaicLightboxTheme(themeClass);
              }
            });
            lightbox.on("slide_after_load", function (data) {
              ensureLightboxFrameWrappers(data && data.slideNode ? data.slideNode : document);
            });
            return true;
          } catch (e) {
            deactivateMosaicLightboxTheme(themeClass);
          }
        }
        return false;
      }

      // First attempt immediately, then retry lazily until GLightbox is loaded (max ~6s)
      if (enable && !tryInitLightbox()) {
        var t0 = Date.now();
        var iv = setInterval(function () {
          if (tryInitLightbox() || Date.now() - t0 > 6000) {
            clearInterval(iv);
          }
        }, 150);
      }

      var visibleImages = grid.querySelectorAll(".mosaic-item:not(.is-hidden) img");
      waitForImages(visibleImages, function () {
        if (layoutMode !== "grid" && typeof window.Masonry === "function") {
          try {
            var sizer = grid.querySelector(".mosaic-sizer") || grid.querySelector(".mosaic-item");
            msnry = new window.Masonry(grid, {
              itemSelector: ".mosaic-item",
              columnWidth:  sizer,
              percentPosition: true,
              gutter: gap,
              fitWidth: false  // Use the full container width.
            });
          } catch (e) {
            msnry = null;
          }
        }

        if (layoutMode === "grid") {
          grid.style.height = "";
          Array.prototype.forEach.call(grid.querySelectorAll(".mosaic-item"), function (item) {
            item.style.left = "";
            item.style.position = "";
            item.style.top = "";
            item.style.transform = "";
          });
        }

        function relayout() {
          if (msnry) msnry.layout();
        }

        window.addEventListener("resize", relayout);

        var btn = container.querySelector(".mosaic-load-more");
        if (btn) {
          var loading = false;
          btn.addEventListener("click", function () {
            if (loading) return;

            var hidden = grid.querySelectorAll(".mosaic-item.is-hidden");
            if (!hidden.length) {
              btn.remove();
              return;
            }

            var reveal = Array.prototype.slice.call(hidden, 0, step || hidden.length);
            var batchImages = [];
            loading = true;
            btn.disabled = true;
            btn.setAttribute("aria-busy", "true");

            reveal.forEach(function (el) {
              Array.prototype.forEach.call(el.querySelectorAll("img[data-src]"), function (img) {
                var deferredSrc = img.getAttribute("data-src");
                if (deferredSrc) {
                  img.loading = "eager";
                  img.setAttribute("src", deferredSrc);
                }
                img.removeAttribute("data-src");
              });
              Array.prototype.push.apply(batchImages, el.querySelectorAll("img"));
            });

            waitForImages(batchImages, function () {
              reveal.forEach(function (el) {
                el.classList.remove("is-hidden");
              });
              try {
                relayout();
                if (enable && lightbox && typeof lightbox.reload === "function") {
                  lightbox.reload();
                }
              } catch (e) {
                // Keep the revealed batch usable if an optional layout integration fails.
              }

              loading = false;
              if (!grid.querySelector(".mosaic-item.is-hidden")) {
                btn.remove();
              } else {
                btn.disabled = false;
                btn.removeAttribute("aria-busy");
              }
            }, 10000);
          });
        }

        relayout();
        markLayoutReady();
      }, 10000);
    });
  });
})();
