(function () {
  document.addEventListener("DOMContentLoaded", function () {
    var containers = document.querySelectorAll(".mosaic-gallery");
    if (!containers.length) return;

    // Tiny imagesLoaded fallback if the library is not present
    if (typeof window.imagesLoaded === "undefined") {
      window.imagesLoaded = function (el, cb) {
        try {
          var imgs = el.querySelectorAll("img");
          var left = imgs.length;
          if (!left) { cb(); return; }
          imgs.forEach(function (img) {
            var done = function () { if (--left === 0) cb(); };
            if (img.complete) {
              done();
            } else {
              img.addEventListener("load", done);
              img.addEventListener("error", done);
            }
          });
        } catch (e) {
          cb();
        }
      };
    }

    containers.forEach(function (container) {
      var gap      = parseInt(container.style.getPropertyValue("--gap") || "12", 10);
      var step     = parseInt(container.getAttribute("data-step") || "0", 10);
      var enable   = container.getAttribute("data-lightbox") === "1";
      var group    = container.getAttribute("data-group") || "gallery";
      var grid     = container.querySelector(".mosaic-grid") || container;
      var lightbox = null;

      function tryInitLightbox() {
        if (!enable) return;
        if (window.GLightbox) {
          lightbox = GLightbox({ selector: "a[data-gallery=\"" + group + "\"]" });
          return true;
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

      imagesLoaded(grid, function () {
        var sizer = grid.querySelector(".mosaic-sizer") || grid.querySelector(".mosaic-item");
        var msnry = new Masonry(grid, {
          itemSelector: ".mosaic-item",
          columnWidth:  sizer,
          percentPosition: true,
          gutter: gap,
          fitWidth: true
        });

        window.addEventListener("resize", function () {
          msnry.layout();
        });

        var btn = container.querySelector(".mosaic-load-more");
        if (btn) {
          btn.addEventListener("click", function () {
            var hidden = grid.querySelectorAll(".mosaic-item.is-hidden");
            if (!hidden.length) {
              btn.remove();
              return;
            }

            var reveal = Array.prototype.slice.call(hidden, 0, step || hidden.length);
            reveal.forEach(function (el) {
              el.classList.remove("is-hidden");
            });

            imagesLoaded(reveal, function () {
              msnry.appended(reveal);
              msnry.layout();
              if (enable && lightbox && typeof lightbox.reload === "function") {
                lightbox.reload();
              }
            });

            if (!grid.querySelector(".mosaic-item.is-hidden")) {
              btn.remove();
            }
          });
        }
      });
    });
  });
})();

/* === mosaic_gallery v1.3.1 – Lightbox theme + frame copied from gallery tiles === */
(function(){
  function hexToRgb(hex){
    if (!hex) return {r:0,g:0,b:0};
    if (/^rgba?\(/i.test(hex)) {
      var m = hex.match(/(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
      return {r:+(m && m[1] || 0), g:+(m && m[2] || 0), b:+(m && m[3] || 0)};
    }
    var s = String(hex).replace("#","").trim();
    if (s.length === 3) {
      s = s.split("").map(function(c){ return c + c; }).join("");
    }
    var n = parseInt(s,16);
    if (isNaN(n)) return {r:0,g:0,b:0};
    return {
      r:(n>>16)&255,
      g:(n>>8)&255,
      b:n&255
    };
  }

  function toRgba(hex, a){
    if (!hex) return "rgba(0,0,0," + (a || 0.92) + ")";
    if (/^rgba?\(/i.test(hex)) return hex;
    var c = hexToRgb(hex);
    var alpha = (typeof a === "number" ? a : parseFloat(a));
    if (isNaN(alpha)) alpha = 0.92;
    return "rgba(" + c.r + "," + c.g + "," + c.b + "," + alpha + ")";
  }

  function injectCss(id, css){
    if (document.getElementById(id)) return;
    var st = document.createElement("style");
    st.id = id;
    st.type = "text/css";
    st.textContent = css;
    document.head.appendChild(st);
  }

  document.addEventListener("DOMContentLoaded", function(){
    document
      .querySelectorAll(".mosaic-gallery[data-lightbox=\"1\"]")
      .forEach(function(root, idx){
        var ds    = root.dataset;
        var group = ds.group || ("mg" + idx);

        // Read CSS variables from the gallery to mirror frame/background in the lightbox
        var cs = getComputedStyle(root);
        function gv(n, d){
          return (cs.getPropertyValue(n) || d).toString().trim();
        }

        var frameColor = gv("--frame-color", "transparent");
        var frameWidth = gv("--frame-width", "0px");
        var frameStyle = gv("--frame-style", "none");

        var radius = (function(v){
          v = (v || "0").toString().trim();
          return v.endsWith("px") ? v : (parseInt(v, 10) || 0) + "px";
        })(gv("--radius","0"));

        var bgApply = (root.getAttribute("data-apply-bg") || "").toLowerCase();
        var bgColor = gv("--bg", "transparent");
        var tileBg  = (bgApply === "tiles" || bgApply === "both") ? bgColor : "transparent";

        var css =
          // Overlay and colors for navigation / close button / captions
          ".goverlay{background:" +
            toRgba(ds.lbOverlay || "#000000", ds.lbOverlayAlpha || "0.92") +
            "!important;}" +
          ".glightbox-clean .gclose path{fill:" +
            (ds.lbClose || "#FFFFFF") +
            "!important;}" +
          ".glightbox-clean .gnext path,.glightbox-clean .gprev path{fill:" +
            (ds.lbNav || "#FFFFFF") +
            "!important;}" +
          ".glightbox-container .gslide-title,.glightbox-container .gslide-desc{color:" +
            (ds.lbCaption || "#FFFFFF") +
            "!important;}" +
          ".glightbox-clean .gslide-description{background:" +
            (ds.lbCaptionBg || "rgba(0,0,0,0.75)") +
            "!important;}" +

          // Frame and border-radius on the image inside the lightbox (same as tiles)
          ".glightbox-container .gslide-image img{" +
            "border:" + frameWidth + " " + frameStyle + " " + frameColor + " !important;" +
            "border-radius:" + radius + " !important;" +
            "background:" + tileBg + " !important;" +
            "box-sizing:border-box;" +
          "}";

        injectCss("mg-glx-theme-" + group, css);
      });
  });
})();
