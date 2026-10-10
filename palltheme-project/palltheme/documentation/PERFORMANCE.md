# Performance Guide

Palltheme is built to score 90+ on PageSpeed and pass Core Web Vitals on decent hosting. This is what the theme already does, and what you should add.

## Built in

- **No jQuery dependency, no sliders/animation libraries.** One small deferred script (`main.js`) plus `woocommerce.js` only on store pages. Sliders use native CSS scroll-snap.
- **Conditional assets:** the theme's store CSS/JS load only where products appear. On pages without products (About, Contact, blog archives…) the theme also removes the assets WooCommerce, YITH Wishlist and YITH Compare add to every page — about 25 files including React, lodash and moment. WooCommerce's order-attribution script stays everywhere because it records where a visitor arrived from. A page counts as a store page when it is a WooCommerce page, the search results, or its content/Elementor layout contains a product section, a WooCommerce shortcode/block/widget, the wishlist or compare shortcode, or related products. Turn this off with `add_filter( 'palltheme_trim_store_assets', '__return_false' );` or adjust the list with the `palltheme_store_asset_handles` filter. `comment-reply` loads only on posts with open comments; the Lottie player only if a Lottie animation is on the page; Google Maps only when the visitor clicks the map.
- **YITH Wishlist classic buttons:** the demo importer switches YITH Wishlist to its PHP-template ("classic") buttons. Its newer React buttons add ~1 MB of JavaScript to every store page; the classic buttons do the same job with one small jQuery file. The theme's header counter works with both.
- **Block styles per page** (Customizer → Performance): classic themes normally load WordPress's whole 140 KB block-library stylesheet everywhere; Palltheme loads only the styles of blocks a page actually uses.
- **No emoji script** (Customizer → Performance): WordPress's emoji detection script is removed — every current browser renders emoji natively.
- **No duplicate fonts:** Elementor's global typography mirrors the theme fonts, so Elementor's own copy of those Google Fonts is not loaded.
- **Inline SVG icons** — only the icons used on a page are output. No icon font.
- **Fonts:** Google Fonts with `display=swap` + preconnect, or switch to **System fonts** (Customizer → Typography) for zero font requests.
- **Images:** native `loading="lazy"`, `fetchpriority="high"` on the hero/LCP image, WordPress responsive `srcset`. An *Image + Text* section placed first on a page can be marked **First section on the page** so its photo loads immediately instead of lazily (the demo does this on About and Careers).
- **No layout shift:** WooCommerce's product-gallery slider is several times taller for one frame while it starts, which pushed the product summary down and back on phones — the theme holds the gallery at its final height until it has faded in. Breadcrumbs stay on one line (the current page title is shortened with "…") so a font swap can't make them wrap and move the page.
- **Background videos:** never on mobile, never with Save-Data or reduced-motion, sources injected after load (`preload="none"`), poster image fallback.
- **Animations:** CSS transitions driven by IntersectionObserver; respects `prefers-reduced-motion`; can be disabled globally.
- **Queries:** `no_found_rows`, `fields => ids` where possible, transients for related posts and live-search results; product filtering uses WooCommerce's own indexed query parameters (scales to thousands of products). Live search responses send `Cache-Control: public, max-age=300` for CDN caching.
- **Cache-safe:** dynamic bits (cart count, mini cart) use WooCommerce fragments; "recently viewed" is stored in the visitor's browser, so full-page caching never leaks one visitor's data to another. The contact form works on cached pages.
- **Dark mode** is applied before first paint (no flash) with a 300-byte inline script.

## Add these

1. **Page cache:** LiteSpeed Cache or WP Rocket. Exclude cart, checkout and my-account (both plugins do this automatically for WooCommerce).
2. **Object cache:** Redis or Memcached (ask your host) — large catalogs benefit the most.
3. **Image optimisation:** ShortPixel or Imagify with WebP/AVIF delivery.
4. **CDN / Cloudflare:** fully compatible. Use "Cache Everything" only with a WooCommerce-aware setup (bypass on `woocommerce_items_in_cart` / `wp_woocommerce_session_*` cookies).
5. **Minify/combine:** safe to minify CSS/JS. If you use "Delay JavaScript", exclude `palltheme-scheme` (the dark-mode inline script) so the color scheme applies before paint.
6. **WooCommerce:** enable HPOS (High-Performance Order Storage) and keep "Product data lookup tables" regenerated.
7. **Background videos:** keep under ~4 MB, 720p, 10–20 s loops, WebM + MP4.

## Critical CSS

Compatible with WP Rocket "Remove Unused CSS"/"Critical CSS" and LiteSpeed "UCSS/CCSS". If something looks unstyled after enabling them, add `pt-reveal`, `is-visible`, `is-scrolled`, `is-open` and `pt-view-list` to the safelist (these classes are added by JavaScript).
