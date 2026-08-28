/* AUN Care promo animation — web build.
   Ported from animation/aun-care-promo.html.

   Differences from the authoring file, all deliberate:
   - no control bar (frame/mood/lang are set by the shortcode)
   - ratio="auto" picks a frame per viewport and re-runs on breakpoint change
   - the loop only runs while the stage is on screen (IntersectionObserver)
   - honours prefers-reduced-motion by not animating at all
   - supports more than one instance on a page
*/
(function () {
  'use strict';

  var COPY = {
    en: {
      0: 'AUN Care',
      1: 'Every repair and parts order, live',
      2: 'Even what to watch tonight',
      3: 'Scan the barcode once.<br>Your warranty is sorted.',
      4: 'Firmware, manuals, videos and help<br>for your exact model',
      5: 'Ask in the app.<br>No phone calls, nothing forgotten.',
      6: 'Most brands sell you a box.<br>AUN stays after the sale.<small>aun-projector.com.bd</small>'
    },
    bn: {
      0: 'AUN Care',
      1: 'প্রতিটি রিপেয়ার আর পার্টস অর্ডার — লাইভ',
      2: 'আজ রাতে কী দেখবেন, সেটাও',
      3: 'একবার বারকোড স্ক্যান করুন।<br>ওয়ারেন্টি নিশ্চিত।',
      4: 'আপনার মডেলের ফার্মওয়্যার, ম্যানুয়াল,<br>ভিডিও আর হেল্প',
      5: 'অ্যাপেই জিজ্ঞেস করুন।<br>ফোন করার দরকার নেই।',
      6: 'অন্যরা শুধু বক্স বিক্রি করে।<br>AUN বিক্রির পরেও পাশে থাকে।<small>aun-projector.com.bd</small>'
    }
  };

  var D = 20000;
  var EASE = 'cubic-bezier(.4,0,.2,1)';
  var SPRING = 'cubic-bezier(.22,1.2,.36,1)';
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function splitWords(el) {
    (function walk(node) {
      Array.prototype.slice.call(node.childNodes).forEach(function (n) {
        if (n.nodeType === 3) {
          var frag = document.createDocumentFragment();
          n.textContent.split(/(\s+)/).forEach(function (part) {
            if (!part.trim()) { frag.appendChild(document.createTextNode(part)); return; }
            var s = document.createElement('span');
            s.className = 'w';
            s.textContent = part;
            frag.appendChild(s);
          });
          n.replaceWith(frag);
        } else if (n.nodeType === 1 && n.tagName !== 'BR') {
          walk(n);
        }
      });
    })(el);
  }

  /* auto frame: 16:9 desktop, 1:1 tablet, 9:16 phone */
  function autoRatio() {
    var w = window.innerWidth;
    if (w >= 900) return '16:9';
    if (w >= 600) return '1:1';
    return '9:16';
  }

  function initStage(stage) {
    var anims = [];
    var running = false;

    var q = function (sel) { return stage.querySelector(sel); };
    var qa = function (sel) { return Array.prototype.slice.call(stage.querySelectorAll(sel)); };
    var view = function (name) { return q('.aun-promo-view[data-view="' + name + '"]'); };

    /* captions */
    var lang = COPY[stage.dataset.lang] ? stage.dataset.lang : 'en';
    qa('.aun-promo-caption p').forEach(function (p) {
      var key = p.getAttribute('data-c');
      if (COPY[lang][key] == null) return;
      p.innerHTML = COPY[lang][key];
      splitWords(p);
    });

    /* ------------------------------------------------------------------
       QUALITY TIER

       What actually costs frames here, in order:
         1. animated `filter: blur()` — a blur cannot be cached while its
            element is transforming, so it is re-rendered every frame. The
            caption used to blur EVERY WORD (~40 animated blurs at once),
            which is why even a flagship phone stuttered.
         2. `mix-blend-mode` on the full-size grain — forces a blend layer.
         3. sheer animation count (~80 running at once).

       The old check only degraded phones with <=2GB RAM or <=4 cores, so a
       Pixel 9 ran everything at full cost. Any PHONE now gets the reduced
       tier regardless of how powerful it claims to be — a big screen is a
       better signal of "can afford this" than a spec number.
       ------------------------------------------------------------------ */
    var conn = navigator.connection || {};
    var weakHW = (navigator.deviceMemory && navigator.deviceMemory <= 3) ||
                 (navigator.hardwareConcurrency && navigator.hardwareConcurrency <= 4);
    var isPhone = window.innerWidth <= 820;

    var tier = 'full';
    if (isPhone || weakHW) tier = 'reduced';
    if (reduced || conn.saveData) tier = 'still';
    setTier(tier);

    function setTier(t) {
      tier = t;
      stage.classList.toggle('is-reduced', t === 'reduced');
      stage.classList.toggle('is-still', t === 'still');
    }

    /* dust motes — the cheapest thing here, but still a layer each */
    var motes = q('.aun-promo-motes');
    var MOTES = [];
    var MOTE_N = tier === 'still' ? 0 : (tier === 'reduced' ? 6 : 16);
    for (var i = 0; i < MOTE_N; i++) {
      var m = document.createElement('span');
      var s = 2 + Math.random() * 4;
      m.style.width = s + 'px';
      m.style.height = s + 'px';
      m.style.left = (Math.random() * 100) + '%';
      m.style.top = (Math.random() * 100) + '%';
      motes.appendChild(m);
      MOTES.push(m);
    }

    var at = function (t) { return Math.min(1, Math.max(0, t / D)); };
    /* `bg` marks a purely decorative layer — beam, light spill, dust, sheen.
       These are the only things paused while the page scrolls, because nobody
       is watching them. The phone, the screens and the captions keep running,
       so the scene never visibly freezes under your finger. */
    var bgAnims = [];
    var A = function (el, kf, opt, bg) {
      if (!el) return;
      var o = { duration: D, iterations: Infinity, easing: 'linear' };
      for (var k in (opt || {})) o[k] = opt[k];
      var an = el.animate(kf, o);
      anims.push(an);
      if (bg) bgAnims.push(an);
    };

    /* Size each scroll port from the REAL chrome heights. The screen box is a
       hair taller than the artwork's aspect, and trusting ratios instead of
       measuring showed a cut band above the nav bar. */
    function layout() {
      var sc = q('.aun-promo-screen');
      if (!sc) return;
      var sh = sc.clientHeight;
      qa('.aun-promo-port').forEach(function (p) {
        var v = p.closest('.aun-promo-view');
        var top = v.querySelector('.aun-promo-chrome-top');
        var nav = v.querySelector('.aun-promo-chrome-nav');
        var th = top ? top.offsetHeight : 0;
        var nh = nav ? nav.offsetHeight : 0;
        p.style.top = th + 'px';
        p.style.height = Math.max(0, sh - th - nh) + 'px';
      });
    }

    function stop() {
      anims.forEach(function (a) { a.cancel(); });
      anims.length = 0;
      bgAnims.length = 0;
      running = false;
    }

    function run() {
      if (reduced) { layout(); return; }
      stop();
      layout();
      running = true;

      A(q('.aun-promo-beam'), [
        { transform: 'rotate(-9deg) scale(1.05)', opacity: .55 },
        { transform: 'rotate(9deg) scale(1.13)', opacity: .95, offset: .6 },
        { transform: 'rotate(-9deg) scale(1.05)', opacity: .55 }
      ], {}, true);

      A(q('.aun-promo-phone'), [
        { opacity: 0, transform: 'translateY(26px) translateZ(-90px) rotateY(-13deg) rotateX(5deg) scale(.97)', easing: SPRING },
        { opacity: 1, transform: 'translateY(0) translateZ(0) rotateY(-7deg) rotateX(2.2deg) scale(1)', offset: .075, easing: 'ease-in-out' },
        { transform: 'translateY(-6px) translateZ(26px) rotateY(3deg) rotateX(-1deg) scale(1.01)', offset: .52, easing: 'ease-in-out' },
        { opacity: 1, transform: 'translateY(0) translateZ(0) rotateY(8deg) rotateX(1.5deg) scale(1)', offset: .965, easing: 'cubic-bezier(.4,0,1,1)' },
        { opacity: 0, transform: 'translateY(26px) translateZ(-90px) rotateY(13deg) rotateX(5deg) scale(.97)' }
      ]);

      A(q('.aun-promo-spill'), [
        { opacity: 0, transform: 'translate(-50%,-50%) scale(.82)' },
        { opacity: .85, transform: 'translate(-50%,-50%) scale(1)', offset: .09, easing: 'ease-in-out' },
        { opacity: 1, transform: 'translate(-50%,-50%) scale(1.06)', offset: .5, easing: 'ease-in-out' },
        { opacity: .9, transform: 'translate(-50%,-50%) scale(1)', offset: .95 },
        { opacity: 0, transform: 'translate(-50%,-50%) scale(.82)' }
      ], {}, true);

      MOTES.forEach(function (m, i) {
        var dx = (i % 2 ? 1 : -1) * (14 + (i * 7) % 26);
        var dy = -(30 + (i * 13) % 70);
        A(m, [
          { transform: 'translate(0,0)', opacity: 0 },
          { opacity: .5 + (i % 5) * .1, offset: .12 },
          { opacity: .35 + (i % 4) * .12, offset: .7 },
          { transform: 'translate(' + dx + 'px,' + dy + 'px)', opacity: 0 }
        ], { delay: -(i * 900) % D }, true);
      });

      A(q('.aun-promo-spin'), [
        { transform: 'translate(-50%,-50%) rotate(0deg)' },
        { transform: 'translate(-50%,-50%) rotate(6000deg)' }
      ]);

      var scenes = [
        ['splash', 0, 2000],
        ['home', 2000, 8000],
        ['devices', 8000, 10000],
        ['detail', 10000, 14000],
        ['support', 14000, 20000]
      ];
      var F = 320;
      scenes.forEach(function (sc) {
        var id = sc[0], a = sc[1], b = sc[2];
        var inScale = (id === 'home') ? 1.06 : 1.018;
        A(view(id), [
          { opacity: 0, transform: 'scale(' + inScale + ')', offset: 0 },
          { opacity: 0, transform: 'scale(' + inScale + ')', offset: at(a - F), easing: EASE },
          { opacity: 1, transform: 'none', offset: at(a + F) },
          { opacity: 1, transform: 'none', offset: at(b - F), easing: EASE },
          { opacity: 0, transform: 'scale(.99)', offset: at(b + F) },
          { opacity: 0, transform: 'scale(' + inScale + ')', offset: 1 }
        ]);
      });

      var gl = [{ opacity: 0, transform: 'translateX(-70%)', offset: 0 }];
      [2000, 8000, 10000, 14000].forEach(function (c) {
        gl.push({ opacity: 0, transform: 'translateX(-70%)', offset: at(c - 120) });
        gl.push({ opacity: .55, transform: 'translateX(-10%)', offset: at(c + 180), easing: 'ease-out' });
        gl.push({ opacity: 0, transform: 'translateX(70%)', offset: at(c + 620) });
      });
      gl.push({ opacity: 0, transform: 'translateX(-70%)', offset: 1 });
      A(q('.aun-promo-gloss'), gl, {}, true);

      function scroll(name, a, b) {
        var v = view(name);
        if (!v) return;
        var port = v.querySelector('.aun-promo-port');
        var trk = v.querySelector('.aun-promo-track');
        if (!port || !trk) return;
        var dist = Math.max(0, trk.clientHeight - port.clientHeight);
        if (!dist) return;
        A(trk, [
          { transform: 'translateY(0)', offset: 0 },
          { transform: 'translateY(0)', offset: at(a), easing: 'cubic-bezier(.45,.05,.35,1)' },
          { transform: 'translateY(' + (-dist) + 'px)', offset: at(b) },
          { transform: 'translateY(' + (-dist) + 'px)', offset: 1 }
        ]);
      }
      scroll('home', 2300, 6400);
      scroll('support', 14400, 18600);

      A(q('.aun-promo-tap'), [
        { opacity: 0, transform: 'translate(-50%,-50%) scale(.2)', offset: 0 },
        { opacity: 0, transform: 'translate(-50%,-50%) scale(.2)', offset: at(11550) },
        { opacity: .95, transform: 'translate(-50%,-50%) scale(.55)', offset: at(11720), easing: 'ease-out' },
        { opacity: 0, transform: 'translate(-50%,-50%) scale(1.5)', offset: at(12250) },
        { opacity: 0, transform: 'translate(-50%,-50%) scale(.2)', offset: 1 }
      ]);
      A(q('.aun-promo-layer2'), [
        { opacity: 0, offset: 0 }, { opacity: 0, offset: at(11850), easing: EASE },
        { opacity: 1, offset: at(12220) }, { opacity: 1, offset: 1 }
      ]);

      [[0, 0, 2000], [1, 2000, 5000], [2, 5000, 8000], [3, 8000, 10000],
       [4, 10000, 14000], [5, 14000, 17000], [6, 17000, 20000]].forEach(function (c) {
        var key = c[0], a = c[1], b = c[2];
        var p = q('.aun-promo-caption p[data-c="' + key + '"]');
        if (!p) return;
        A(p, [
          { opacity: 0, offset: 0 }, { opacity: 0, offset: at(a - 1) }, { opacity: 1, offset: at(a + 60) },
          { opacity: 1, offset: at(b - 360), easing: EASE }, { opacity: 0, offset: at(b) }, { opacity: 0, offset: 1 }
        ]);
        /* Per-word stagger is a desktop-only luxury.
           It used to animate `filter: blur()` on every word — roughly 40
           simultaneous animated blurs, the single most expensive thing in this
           scene and the reason phones stuttered. Blur is gone entirely; the
           stagger now uses transform + opacity, which the GPU handles for free.
           On phones the whole line simply fades as one element (7 animations
           instead of ~47). */
        if (tier !== 'full') return;

        Array.prototype.slice.call(p.querySelectorAll('.w')).forEach(function (w, i) {
          var t0 = a + i * 55;
          A(w, [
            { opacity: 0, transform: 'translateY(9px)', offset: 0 },
            { opacity: 0, transform: 'translateY(9px)', offset: at(t0), easing: SPRING },
            { opacity: 1, transform: 'none', offset: at(t0 + 420) },
            { opacity: 1, transform: 'none', offset: at(b - 360), easing: EASE },
            { opacity: 0, transform: 'translateY(-7px)', offset: at(b) },
            { opacity: 0, transform: 'translateY(9px)', offset: 1 }
          ]);
        });
      });
    }

    /* responsive frame */
    function applyRatio() {
      if (stage.dataset.arMode !== 'auto') return false;
      var want = autoRatio();
      if (stage.dataset.ar === want) return false;
      stage.dataset.ar = want;
      return true;
    }
    applyRatio();

    /* ------------------------------------------------------------------
       PAUSE WHILE THE PAGE IS SCROLLING

       Scrolling and animating compete for the same compositor. Freezing the
       loop while the finger is moving is what makes the page feel smooth as
       you pass this section; the animation resumes a moment after the scroll
       settles and nobody notices it stopped.
       ------------------------------------------------------------------ */
    var scrolling = false, scrollTimer;
    function pauseForScroll() {
      if (!running) return;
      if (!scrolling) {
        scrolling = true;
        /* Only the decorative layers. Pausing everything froze the phone and the
           captions mid-scroll, which was visible and worse than the jank it was
           meant to cure. The scene keeps playing; only the beam, spill, dust and
           sheen hold still, and nobody watches those. */
        bgAnims.forEach(function (a) { a.pause(); });
      }
      clearTimeout(scrollTimer);
      scrollTimer = setTimeout(function () {
        scrolling = false;
        if (running && onScreen) bgAnims.forEach(function (a) { a.play(); });
      }, 120);
    }
    window.addEventListener('scroll', pauseForScroll, { passive: true });

    /* ------------------------------------------------------------------
       FRAME-RATE WATCHDOG

       Device specs lie in both directions, so measure instead. Sample real
       frame times for ~1.5s once the loop is running and step the quality
       down if the device cannot keep up. Runs once, costs nothing after.
       ------------------------------------------------------------------ */
    function watchFrames() {
      if (tier === 'still' || reduced) return;
      var frames = 0, t0 = performance.now(), last = t0, slow = 0;
      (function tick(now) {
        if (!running || scrolling) { t0 = last = performance.now(); frames = slow = 0; }
        else {
          frames++;
          if (now - last > 28) slow++;   // slower than ~36fps
          last = now;
        }
        if (now - t0 < 1500) { requestAnimationFrame(tick); return; }
        var fps = frames / ((now - t0) / 1000);
        if (fps < 40 || slow > frames * 0.35) {
          if (tier === 'full') { setTier('reduced'); run(); requestAnimationFrame(function (n) { t0 = last = n; frames = slow = 0; requestAnimationFrame(tick); }); }
          else { setTier('still'); stop(); }
        }
      })(performance.now());
    }

    /* only animate while visible — saves battery on phones */
    var onScreen = true;
    if ('IntersectionObserver' in window) {
      onScreen = false;
      new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          onScreen = e.isIntersecting;
          if (onScreen) {
            if (!running) run();
            else {
              anims.forEach(function (a) { a.play(); });
              // a scroll may still be in flight — keep the decorative layers held
              if (scrolling) bgAnims.forEach(function (a) { a.pause(); });
            }
          } else if (running) {
            anims.forEach(function (a) { a.pause(); });
          }
        });
      }, { rootMargin: '160px' }).observe(stage);
    }

    /* ⚠️ Mobile browsers fire `resize` while you SCROLL, because the address bar
       collapses and expands and the viewport height changes with it. Rebuilding the
       animation on that made it restart from the beginning on every swipe.
       Only a WIDTH change is a real layout change; height-only events are ignored. */
    var rt, lastW = window.innerWidth;
    window.addEventListener('resize', function () {
      if (window.innerWidth === lastW) return;
      lastW = window.innerWidth;
      clearTimeout(rt);
      rt = setTimeout(function () {
        applyRatio();
        if (!onScreen) { stop(); return; }
        // Carry the playback position across the rebuild so a genuine resize or a
        // rotation resumes where it was instead of jumping back to the splash.
        var at = anims.length ? (anims[0].currentTime || 0) : 0;
        run();
        anims.forEach(function (a) { try { a.currentTime = at; } catch (e) {} });
      }, 150);
    });

    /* images decide the layout, so wait for them */
    function start() {
      if (onScreen || !('IntersectionObserver' in window)) { run(); watchFrames(); }
      else layout();
    }
    if (document.readyState === 'complete') start();
    else window.addEventListener('load', start);
  }

  function boot() {
    Array.prototype.slice.call(document.querySelectorAll('.aun-promo-stage')).forEach(initStage);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
