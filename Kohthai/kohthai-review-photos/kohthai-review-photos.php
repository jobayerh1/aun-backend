<?php
/**
 * Plugin Name:       Kohthai Review Photos
 * Plugin URI:        https://kohthaibd.com/
 * Description:       Makes photo uploads in product reviews work on phones: shrinks photos before they are sent, lets the gallery be used instead of forcing the camera, and says so plainly when an upload fails. Companion to Customer Reviews for WooCommerce — it changes nothing in that plugin.
 * Version:           1.0.0
 * Author:            Kohthai
 * Author URI:        https://kohthaibd.com/
 * License:           GPL-2.0+
 * Text Domain:       kohthai-review-photos
 * Requires PHP:      7.4
 *
 * @package Kohthai_Review_Photos
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Why this exists.
 *
 * Customer Reviews for WooCommerce (5.120.0) uploads each review photo by AJAX,
 * and three things about how it does that made photos fail silently here:
 *
 * 1. The server's PHP per-file limit is below 11 MB, while the plugin's own
 *    check allows 10 MB. A phone photo between the two passes the check, PHP
 *    throws the file away, and WordPress's upload then fails.
 * 2. When that upload fails the plugin writes the error and then overwrites it
 *    with "200 OK" and no attachment. Its JavaScript reads `attachment.id` from
 *    that, crashes, and the thumbnail sits there with an empty progress bar.
 *    Its AJAX call has no error handler at all, so a network failure or any
 *    reply that is not JSON ends the same way. Nothing is ever shown.
 * 3. The file input carries capture="environment", which on a phone opens the
 *    rear camera directly. The photo she already took of the bag cannot be
 *    picked from the gallery.
 *
 * It also accepts no WebP, which is what most images saved from the web are.
 *
 * Everything here happens in the browser, around the other plugin's code
 * rather than inside it, so updating that plugin cannot undo it.
 */
final class Kohthai_Review_Photos {

	const VERSION = '1.0.0';

	/**
	 * A token printed inside the script so WP Rocket can be told to leave it
	 * alone. See exclude_from_rocket_delay().
	 */
	const DELAY_MARK = 'ktReviewPhotos';

	public static function init() {
		add_action( 'wp_footer', array( __CLASS__, 'print_script' ), 30 );
		add_filter( 'rocket_delay_js_exclusions', array( __CLASS__, 'exclude_from_rocket_delay' ) );
	}

	/**
	 * Keep WP Rocket from holding this script until the first interaction.
	 *
	 * ⚠️ data-no-defer and friends do NOT cover "Delay JavaScript Execution";
	 * it is a separate feature. If this were delayed, the camera-only input
	 * would still be in place on a phone until something else woke the page.
	 */
	public static function exclude_from_rocket_delay( $excluded ) {
		if ( ! is_array( $excluded ) ) {
			$excluded = array();
		}
		$excluded[] = self::DELAY_MARK;
		return $excluded;
	}

	/** Product pages only — that is the only place the review form lives. */
	public static function print_script() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}
		echo self::script_tag(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup.
	}

	/**
	 * The whole script, with the standing WP Rocket and Cloudflare guards.
	 *
	 * Kept separate from print_script() so it can be tested without faking a
	 * product page.
	 */
	public static function script_tag() {
		return '<script data-no-optimize="1" data-no-minify="1" data-no-defer="1" data-cfasync="false">'
			. self::js()
			. '</script>';
	}

	private static function js() {
		return <<<'JS'
(function(){
"use strict";
/* ktReviewPhotos - the marker exclude_from_rocket_delay() matches on. */
window.ktReviewPhotos = true;

var ACTION = "cr_upload_local_images_frontend";

/* Anything bigger than this, or in a format the review plugin refuses, is
   redrawn as a JPEG no wider or taller than MAX_SIDE. That lands a phone photo
   at a few hundred kilobytes: under the server's limit, quick on mobile data,
   and still far sharper than a review photo is ever shown. */
var MAX_SIDE = 2048, QUALITY = 0.85, SHRINK_OVER = 1.5 * 1024 * 1024;

var MSG = {
  preparing: "Preparing your photos…",
  notSaved:  "That photo couldn't be saved. Please try again, or choose a different photo.",
  network:   "The upload was interrupted. Please check your connection and try again.",
  busy:      "The shop couldn't take that photo just now. Please try again in a moment."
};

var passing = false;   /* the change event we re-send ourselves, with the new files */
var pending = [];      /* thumbnail numbers of the uploads about to be sent, in order */

function input(){ return document.getElementById("cr_review_image"); }

function status(text, isError){
  var s = document.querySelector(".cr-upload-images-status");
  if (!s) return;
  s.textContent = text;
  if (isError) s.classList.add("cr-upload-images-status-error");
  else s.classList.remove("cr-upload-images-status-error");
}

/* ---- 1. Let a phone choose from the gallery --------------------------- */

/* capture="environment" opens the rear camera straight away and hides the
   gallery. The photo of the bag she already took is in the gallery. */
function freeGallery(){
  var el = input();
  if (el && el.hasAttribute("capture")) el.removeAttribute("capture");
}
freeGallery();
if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", freeGallery);

/* ---- 2. Shrink before sending ------------------------------------------ */

function needsWork(f){
  if (!f || !f.type || f.type.indexOf("image/") !== 0) return false; /* videos go as they are */
  if (f.type === "image/gif") return false;                          /* redrawing loses the animation */
  var accepted = f.type === "image/jpeg" || f.type === "image/jpg" || f.type === "image/png";
  return !accepted || f.size > SHRINK_OVER;
}

function toJpeg(f){
  return new Promise(function(resolve){
    var url = URL.createObjectURL(f), img = new Image();
    img.onload = function(){
      var w = img.naturalWidth, h = img.naturalHeight;
      if (!w || !h) { URL.revokeObjectURL(url); resolve(f); return; }
      var k = Math.min(1, MAX_SIDE / Math.max(w, h));
      var c = document.createElement("canvas");
      c.width = Math.round(w * k); c.height = Math.round(h * k);
      var g = c.getContext("2d");
      /* JPEG has no transparency; a see-through PNG would otherwise go black. */
      g.fillStyle = "#fff"; g.fillRect(0, 0, c.width, c.height);
      g.drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(url);
      c.toBlob(function(b){
        if (!b) { resolve(f); return; }
        var name = String(f.name || "photo").replace(/\.[^.]+$/, "") + ".jpg";
        try { resolve(new File([b], name, { type: "image/jpeg", lastModified: Date.now() })); }
        catch (e) { resolve(f); }
      }, "image/jpeg", QUALITY);
    };
    /* A format this browser cannot read (HEIC on most Android phones) goes as it
       is, and the review plugin says which types it accepts. */
    img.onerror = function(){ URL.revokeObjectURL(url); resolve(f); };
    img.src = url;
  });
}

/* Remember which thumbnail each upload belongs to. The review plugin numbers
   them from data-lastindex, and it bumps that number straight after sending, so
   it has to be read here, before its own handler runs. */
function remember(el){
  pending = [];
  var base = parseInt(el.getAttribute("data-lastindex"), 10) || 1;
  var n = el.files ? el.files.length : 0;
  for (var i = 0; i < n; i++) pending.push(base + i);
}

/* Capture phase on the document runs before the review plugin's own handler on
   the input, so the files can be swapped before it ever looks at them. */
document.addEventListener("change", function(e){
  var el = e.target;
  if (!el || el.id !== "cr_review_image") return;

  if (passing) { passing = false; remember(el); return; }

  var files = Array.prototype.slice.call(el.files || []);
  if (!files.length || !files.some(needsWork) || typeof DataTransfer === "undefined") {
    remember(el);
    return;
  }

  e.stopImmediatePropagation();
  e.stopPropagation();
  status(MSG.preparing, false);

  Promise.all(files.map(function(f){ return needsWork(f) ? toJpeg(f) : f; })).then(function(out){
    try {
      var dt = new DataTransfer();
      out.forEach(function(f){ dt.items.add(f); });
      el.files = dt.files;
    } catch (err) { /* cannot swap: the originals go, which is no worse than before */ }
    passing = true;
    el.dispatchEvent(new Event("change", { bubbles: true }));
  });
}, true);

/* ---- 3. Never fail in silence ------------------------------------------ */

function isUpload(o){
  var d = o && o.data;
  return !!(d && typeof FormData !== "undefined" && d instanceof FormData && d.get && d.get("action") === ACTION);
}

function fail(idx, xhr){
  var $ = window.jQuery;
  if ($) {
    if (idx != null) $(".cr-upload-images-container-" + idx).remove();
    else $(".cr-upload-images-preview .cr-upload-images-containers").not(".cr-upload-ok").first().remove();
  }
  var body = xhr && typeof xhr.responseText === "string" ? xhr.responseText : "";
  if (body.indexOf("Checking your browser") !== -1)  status(MSG.busy, true);
  else if (xhr && xhr.status === 0)                   status(MSG.network, true);
  else if (xhr && xhr.status >= 400)                  status(MSG.busy, true);
  else                                                status(MSG.notSaved, true);
}

function hook($){
  /* A prefilter sees the request before its callbacks are attached, so the
     plugin's success handler can be wrapped and an error handler added where it
     has none. */
  $.ajaxPrefilter(function(opts){
    if (!isUpload(opts)) return;
    var idx = pending.length ? pending.shift() : null;
    var theirs = opts.success;

    opts.success = function(resp){
      var code = resp && Number(resp.code);
      var saved = code === 200 && resp.attachment && resp.attachment.id;
      /* 500 and above: the plugin shows its own message. Leave it to it. */
      if (saved || code >= 500) return theirs && theirs.apply(this, arguments);
      /* 200 with no attachment (the failed-upload bug), or 100 (no file
         arrived). Calling theirs here would crash on attachment.id. */
      fail(idx, null);
    };

    var theirError = opts.error;
    opts.error = function(xhr){
      fail(idx, xhr);
      if (theirError) return theirError.apply(this, arguments);
    };
  });
}

if (window.jQuery) hook(window.jQuery);
else document.addEventListener("DOMContentLoaded", function(){ if (window.jQuery) hook(window.jQuery); });
})();
JS;
	}
}

Kohthai_Review_Photos::init();
