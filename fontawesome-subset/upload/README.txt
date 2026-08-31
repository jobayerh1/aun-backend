FontAwesome Free 7.2.0 subset  -  aun-projector.com.bd
Generated 2026-08-29 from a crawl of the FULL sitemap.

HOW THE ICON LIST WAS BUILT
  sitemap_index.xml -> 6 sub-sitemaps -> 243 URLs, plus 13 pages sitemaps
  never list (cart, checkout, my-account, wishlist, track-order,
  spare-parts-status, repair-status, get-aun-care-app and their /bn/
  equivalents). 244 pages crawled in total.

  157 icons found in use.
  + 35 common UI icons as a safety buffer (bars, user, cart, spinner,
    chevrons, etc.) in case a plugin injects one via JavaScript.
  + the full ASCII range U+0020-U+007E, because FontAwesome maps some
    icons to literal characters (fa-hashtag is "#", fa-1 is "1").
  = 274 codepoints kept. Verified: 183/183 icon codepoints present.

SIZES
  fa-solid-900.woff2    114,740 -> 17,184 bytes   -86%
  fa-brands-400.woff2   110,088 ->  1,448 bytes   -99%
  fa-regular-400.woff2   18,924 ->  5,428 bytes   -72%
  TOTAL                 243,752 -> 24,060 bytes   214 KB saved

INSTALL   (target: wp-content/uploads/fontawesome/webfonts/)
  1. BACK UP: select the three existing .woff2 files -> Compress -> zip
     -> name it webfonts-ORIGINAL.zip. That is your instant rollback.
  2. Upload these three files, overwriting the originals.
     Filenames and codepoints are unchanged, so all.min.css needs no edit.
  3. Clear WP Rocket cache, then Cloudflare -> Purge Everything.
  4. Hard-refresh (Ctrl+Shift+R).

BRAND ICONS INCLUDED
  android, facebook-messenger, google-play, whatsapp, youtube
  (google-play and youtube were missed by an earlier partial crawl -
   this is why the full sitemap crawl was worth doing)

IF AN ICON IS EVER MISSING
  Send the page URL and the icon name; regenerating takes a minute.
  Or restore webfonts-ORIGINAL.zip to revert instantly.

STILL TO DO (separate from this)
  - Revolution Slider loads its own FontAwesome copy from
    wp-content/plugins/revslider/public/css/fonts/font-awesome/
  - wp-content/uploads/font-awesome/ looks like an unused duplicate
    folder; it 404s on the paths the site actually references.
  - all.min.css itself is 75 KB and still defines all 2,547 icons.
    It compresses well, so this matters far less than the fonts did.
