<?php
/**
 * Plugin Name:  AUN About Page Counters
 * Description:  Lightweight count-up animations for the About page (sales auto-increment monthly, years since start, rating with smooth finish). Outputs a tiny vanilla JS snippet in the footer only on the target page. Uses IntersectionObserver and honours prefers-reduced-motion.
 * Version:      1.1.0
 * Author:       Smart Living Bangladesh
 * License:      GPL-2.0+
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'aun_apc_is_target_page' ) ) {
  /**
   * Change 'about-us' below if your About page slug/ID differs.
   * You can also override it via: add_filter('aun_apc_target', fn()=> 'your-slug-or-id');
   */
  function aun_apc_is_target_page() {
    $target = apply_filters( 'aun_apc_target', 'about-us' ); // slug or ID
    return ( ! is_admin() && ! is_feed() && ! is_preview() && is_page( $target ) );
  }
}

add_action( 'wp_footer', function () {
  if ( ! aun_apc_is_target_page() ) return;
  ?>
  <script id="aun-countup-js" data-cfasync="false">
  (function () {
    // ---------- helpers ----------
    function fmt(n, dec){
      try { return new Intl.NumberFormat('en-US',{minimumFractionDigits:dec,maximumFractionDigits:dec}).format(n); }
      catch(e){ return dec ? Number(n).toFixed(dec) : (n+'').replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    }
    function easeOutCubic(t){ return 1 - Math.pow(1 - t, 3); }
    function easeOutQuint(t){ return 1 - Math.pow(1 - t, 5); } // smoother finish
    function inView(el){
      var r=el.getBoundingClientRect(), vh=innerHeight||document.documentElement.clientHeight;
      return r.top < vh*0.85 && r.bottom > vh*0.15;
    }
    function monthStart(d){ return new Date(d.getFullYear(), d.getMonth(), 1); }
    function nextMonth(d){ return new Date(d.getFullYear(), d.getMonth()+1, 1); }
    function wholeYearsSince(since, now){
      var y = now.getFullYear() - since.getFullYear();
      var anniv = new Date(now.getFullYear(), since.getMonth(), since.getDate());
      if (now < anniv) y--;
      return Math.max(0, y);
    }

    // Accessibility: honour users who prefer reduced motion — show final value instantly.
    var REDUCED = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

    // ---------- sales: +5 in 8 equal jumps per month (40/mo) ----------
    function stepsSince(baseDate, now, stepsPerMonth){
      var total = 0, spm = stepsPerMonth || 8;
      var mStart = monthStart(baseDate), nowMStart = monthStart(now);
      while (mStart <= nowMStart) {
        var mNext = nextMonth(mStart);
        var segLen = (mNext - mStart) / spm;

        var startIdx = 0;
        if (baseDate >= mStart && baseDate < mNext) {
          startIdx = Math.floor((baseDate - mStart) / segLen);
          startIdx = Math.min(Math.max(startIdx,0), spm);
        }

        var endIdx;
        if (now >= mNext) endIdx = spm;
        else {
          endIdx = Math.floor((now - mStart) / segLen);
          endIdx = Math.min(Math.max(endIdx,0), spm);
        }

        total += Math.max(0, endIdx - startIdx);
        mStart = mNext;
      }
      return total;
    }
    function computeSalesTarget(el){
      var base = parseInt(el.getAttribute('data-base-count') || '0', 10);
      var baseDateStr = (el.getAttribute('data-base-date') || '').trim();
      var step = parseInt(el.getAttribute('data-step') || '5', 10);
      var spm  = parseInt(el.getAttribute('data-steps-per-month') || '8', 10);
      var baseDate = baseDateStr ? new Date(baseDateStr + 'T00:00:00') : null;
      if (!baseDate || isNaN(baseDate.getTime())) return base;
      var now = new Date(); if (now < baseDate) now = baseDate;
      return base + stepsSince(baseDate, now, spm) * step;
    }

    // ---------- years: whole years since data-since ----------
    function computeYearsTarget(el){
      var sinceStr = (el.getAttribute('data-since') || '').trim();
      if (!sinceStr) return 0;
      var since = new Date(sinceStr + 'T00:00:00');
      if (isNaN(since.getTime())) return 0;
      return wholeYearsSince(since, new Date());
    }

    // ---------- generic animator (sales/years) ----------
    function animate(el, target){
      if (el.__running) return; el.__running = true;
      var start = parseFloat(el.getAttribute('data-start') || '0');
      var dur   = parseInt(el.getAttribute('data-duration') || '1200', 10);
      var dec   = parseInt(el.getAttribute('data-decimals') || '0', 10);
      if (REDUCED) { el.textContent = fmt(dec ? target : Math.floor(target), dec); return; }
      var t0 = null, factor = Math.pow(10, dec);

      function tick(ts){
        if (!t0) t0 = ts;
        var p = Math.min((ts - t0)/dur, 1);
        var raw = start + (target - start) * easeOutCubic(p);
        var val = dec ? Math.round(raw * factor) / factor : Math.floor(raw);
        el.textContent = fmt(val, dec);
        if (p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    }

    // ---------- rating animator (smooth finish, extra decimals during motion) ----------
    function animateRating(el){
      if (el.__running) return; el.__running = true;

      var start = parseFloat(el.getAttribute('data-start') || '0');
      var target = parseFloat(el.getAttribute('data-target') || '0');
      var dur   = parseInt(el.getAttribute('data-duration') || '1600', 10);
      var finalDec = parseInt(el.getAttribute('data-decimals') || '1', 10);
      var animDec  = parseInt(el.getAttribute('data-anim-decimals') || String(finalDec), 10);
      if (REDUCED) { el.textContent = fmt(target, finalDec); return; }

      var t0 = null;
      function tick(ts){
        if (!t0) t0 = ts;
        var p = Math.min((ts - t0) / dur, 1); // 0 → 1
        var raw = start + (target - start) * easeOutQuint(p);

        var shownDec = (p < 0.9) ? Math.max(animDec, finalDec) : finalDec;
        var factor = Math.pow(10, shownDec);
        var val = Math.round(raw * factor) / factor;

        if (p === 1) { val = target; shownDec = finalDec; }

        try {
          el.textContent = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: shownDec,
            maximumFractionDigits: shownDec
          }).format(val);
        } catch(e){
          el.textContent = shownDec ? val.toFixed(shownDec) : String(Math.floor(val));
        }

        if (p < 1) requestAnimationFrame(tick);
      }
      requestAnimationFrame(tick);
    }

    // ---------- boot all counters ----------
    // Animate one counter using the correct animator for its type.
    function trigger(el){
      if (el.__running) return;
      if (el.classList.contains('js-countup-rating')) { animateRating(el); return; }
      var target = el.classList.contains('js-countup-year') ? computeYearsTarget(el) : computeSalesTarget(el);
      el.setAttribute('data-target', String(target));
      animate(el, target);
    }

    function boot(){
      var els = document.querySelectorAll('.js-countup, .js-countup-year, .js-countup-rating');
      if (!els.length) return;

      // Prefer IntersectionObserver — efficient, no scroll thrashing. Fall back to
      // scroll checks on very old browsers.
      if ('IntersectionObserver' in window) {
        var io = new IntersectionObserver(function(entries){
          entries.forEach(function(en){
            if (en.isIntersecting) { trigger(en.target); io.unobserve(en.target); }
          });
        }, { threshold: 0.25 });
        els.forEach(function(el){ io.observe(el); });
      } else {
        var check = function(){ els.forEach(function(el){ if (!el.__running && inView(el)) trigger(el); }); };
        check();
        addEventListener('scroll', check, {passive:true});
        addEventListener('resize', check, {passive:true});
        setTimeout(check, 1000); // late loaders
      }
    }

    (document.readyState !== 'loading') ? boot() : document.addEventListener('DOMContentLoaded', boot, {once:true});
    addEventListener('load', boot, {once:true});
  })();
  </script>
  <?php
}, 99);
