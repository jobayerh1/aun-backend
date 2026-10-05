/**
 * Smart Living Bangladesh — front-end behaviour.
 *
 *  - the gold circuit field behind every dark band
 *  - the header that condenses on scroll
 *  - the mobile menu
 *  - scroll reveals and the count-up figures
 *
 * No page routing: every page here is a real WordPress page.
 */
(function () {
  "use strict";

  var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var GOLD = "194,167,104";

  /* ==========================================================
     CIRCUIT FIELD
     A PCB-style graph drawn from the trace motif in the L of the
     logo. The static traces are cached to an offscreen canvas;
     only the travelling pulses are redrawn each frame.
     ========================================================== */
  function Field(cv, live) {
    this.cv = cv;
    this.live = !!live && !reduce;
    this.ctx = cv.getContext("2d");
    this.pulses = [];
    this.nodes = [];
    this.edges = [];
    this.buf = document.createElement("canvas");
    this.w = 0;
    this.h = 0;
    this.dpr = 1;
  }

  Field.prototype.build = function () {
    var w = this.cv.clientWidth, h = this.cv.clientHeight;
    if (!w || !h) return false;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);
    this.w = w; this.h = h; this.dpr = dpr;
    this.cv.width = Math.round(w * dpr);
    this.cv.height = Math.round(h * dpr);

    // Deterministic PRNG seeded on the founding date, so the pattern is
    // identical on every redraw and every reload.
    var seed = 20190401;
    function rnd() {
      seed = (seed * 1664525 + 1013904223) % 4294967296;
      return seed / 4294967296;
    }

    var GAP = w < 620 ? 86 : 112;
    var cols = Math.max(2, Math.ceil(w / GAP) + 1);
    var rows = Math.max(2, Math.ceil(h / GAP) + 1);

    var grid = [], nodes = [];
    for (var r = 0; r < rows; r++) {
      grid[r] = [];
      for (var c = 0; c < cols; c++) {
        var n = {
          x: c * GAP + (rnd() - 0.5) * GAP * 0.42,
          y: r * GAP + (rnd() - 0.5) * GAP * 0.42,
          e: []
        };
        grid[r][c] = n;
        nodes.push(n);
      }
    }

    // Manhattan routing with a 45-degree chamfer — how a real trace turns.
    function route(a, b) {
      var pts = [{ x: a.x, y: a.y }];
      var dx = b.x - a.x, dy = b.y - a.y;
      var ch = Math.min(Math.abs(dx), Math.abs(dy)) * 0.85;
      if (Math.abs(dx) > Math.abs(dy)) {
        pts.push({ x: a.x + (Math.abs(dx) - ch) * Math.sign(dx), y: a.y });
      } else {
        pts.push({ x: a.x, y: a.y + (Math.abs(dy) - ch) * Math.sign(dy) });
      }
      pts.push({ x: b.x, y: b.y });
      return pts;
    }

    var edges = [];
    function connect(a, b) {
      if (!a || !b) return;
      var pts = route(a, b);
      var len = 0, cum = [0];
      for (var i = 1; i < pts.length; i++) {
        len += Math.hypot(pts[i].x - pts[i - 1].x, pts[i].y - pts[i - 1].y);
        cum.push(len);
      }
      if (len < 8) return;
      var e = { a: a, b: b, pts: pts, cum: cum, len: len };
      edges.push(e);
      a.e.push(e);
      b.e.push(e);
    }

    for (var r2 = 0; r2 < rows; r2++) {
      for (var c2 = 0; c2 < cols; c2++) {
        if (c2 + 1 < cols && rnd() < 0.62) connect(grid[r2][c2], grid[r2][c2 + 1]);
        if (r2 + 1 < rows && rnd() < 0.48) connect(grid[r2][c2], grid[r2 + 1][c2]);
      }
    }

    this.nodes = nodes;
    this.edges = edges;

    // Cache the static layer.
    this.buf.width = this.cv.width;
    this.buf.height = this.cv.height;
    var b = this.buf.getContext("2d");
    b.setTransform(dpr, 0, 0, dpr, 0, 0);
    b.clearRect(0, 0, w, h);
    b.lineCap = "round";
    b.lineJoin = "round";

    edges.forEach(function (e) {
      b.beginPath();
      b.moveTo(e.pts[0].x, e.pts[0].y);
      for (var i = 1; i < e.pts.length; i++) b.lineTo(e.pts[i].x, e.pts[i].y);
      b.strokeStyle = "rgba(" + GOLD + "," + (0.07 + rnd() * 0.10).toFixed(3) + ")";
      b.lineWidth = rnd() > 0.82 ? 1.3 : 0.85;
      b.stroke();
    });
    nodes.forEach(function (n) {
      if (!n.e.length) return;
      b.beginPath();
      b.arc(n.x, n.y, 2.2, 0, Math.PI * 2);
      b.fillStyle = "rgba(" + GOLD + ",.20)";
      b.fill();
    });

    this.pulses.length = 0;
    if (this.live) {
      var count = Math.min(14, Math.max(5, Math.round(w * h / 115000)));
      for (var p = 0; p < count; p++) this.spawn();
      // Seed each pulse part-way along its own run, so the field is already
      // alive on the very first frame instead of filling in.
      this.pulses.forEach(function (pl) { pl.d = Math.random() * pl.total; });
    }
    return true;
  };

  Field.prototype.spawn = function () {
    if (!this.edges.length) return;
    var start = this.nodes[Math.floor(Math.random() * this.nodes.length)];
    if (!start.e.length) return;
    var chain = [], node = start, prev = null;
    var hops = 3 + Math.floor(Math.random() * 4);
    for (var i = 0; i < hops; i++) {
      var opts = node.e.filter(function (edge) { return edge !== prev; });
      if (!opts.length) break;
      var e = opts[Math.floor(Math.random() * opts.length)];
      chain.push({ e: e, from: node });
      prev = e;
      node = (e.a === node) ? e.b : e.a;
    }
    if (!chain.length) return;
    var total = chain.reduce(function (s, l) { return s + l.e.len; }, 0);
    this.pulses.push({ chain: chain, total: total, d: 0, speed: 112 + Math.random() * 96 });
  };

  // Point at arc-length d along the chain, walking each edge in the direction
  // the pulse actually entered it.
  Field.prototype.at = function (pulse, d) {
    if (d < 0) return null;
    var rem = d;
    for (var i = 0; i < pulse.chain.length; i++) {
      var link = pulse.chain[i], e = link.e;
      if (rem > e.len) { rem -= e.len; continue; }
      var fwd = (link.from === e.a);
      var t = fwd ? rem : (e.len - rem);
      for (var j = 1; j < e.cum.length; j++) {
        if (t <= e.cum[j]) {
          var seg = e.cum[j] - e.cum[j - 1];
          var k = seg ? (t - e.cum[j - 1]) / seg : 0;
          return {
            x: e.pts[j - 1].x + (e.pts[j].x - e.pts[j - 1].x) * k,
            y: e.pts[j - 1].y + (e.pts[j].y - e.pts[j - 1].y) * k
          };
        }
      }
      return { x: e.pts[e.pts.length - 1].x, y: e.pts[e.pts.length - 1].y };
    }
    return null;
  };

  Field.prototype.frame = function (dt) {
    var c = this.ctx;
    c.setTransform(1, 0, 0, 1, 0, 0);
    c.clearRect(0, 0, this.cv.width, this.cv.height);
    c.drawImage(this.buf, 0, 0);
    if (!this.live) return;
    c.setTransform(this.dpr, 0, 0, this.dpr, 0, 0);
    c.lineCap = "round";
    c.lineJoin = "round";

    var TAIL = 78, STEPS = 13;
    for (var i = this.pulses.length - 1; i >= 0; i--) {
      var p = this.pulses[i];
      p.d += p.speed * dt;
      if (p.d - TAIL > p.total) {
        this.pulses.splice(i, 1);
        this.spawn();
        continue;
      }

      var head = this.at(p, Math.min(p.d, p.total));
      for (var s = 0; s < STEPS; s++) {
        var d0 = p.d - (TAIL / STEPS) * s;
        var d1 = p.d - (TAIL / STEPS) * (s + 1);
        if (d0 < 0) break;
        var a = this.at(p, Math.min(d0, p.total));
        var b = this.at(p, Math.min(Math.max(d1, 0), p.total));
        if (!a || !b) continue;
        var f = 1 - s / STEPS;
        c.beginPath();
        c.moveTo(a.x, a.y);
        c.lineTo(b.x, b.y);
        c.strokeStyle = "rgba(226,206,158," + (f * f * 0.85).toFixed(3) + ")";
        c.lineWidth = 0.7 + f * 1.5;
        c.stroke();
      }
      if (head && p.d <= p.total) {
        c.beginPath();
        c.arc(head.x, head.y, 2.1, 0, Math.PI * 2);
        c.fillStyle = "rgba(255,247,228,.95)";
        c.fill();
        c.beginPath();
        c.arc(head.x, head.y, 6.5, 0, Math.PI * 2);
        c.fillStyle = "rgba(" + GOLD + ",.16)";
        c.fill();
      }
    }
  };

  /* ---------- registry + one shared rAF loop ---------- */
  var fields = [];
  var raf = null, last = 0;

  function buildFields() {
    fields.length = 0;
    var list = document.querySelectorAll("canvas[data-trace]");
    for (var i = 0; i < list.length; i++) {
      var cv = list[i];
      if (!cv.clientWidth || !cv.clientHeight) continue;
      var f = new Field(cv, cv.hasAttribute("data-live"));
      if (f.build()) fields.push(f);
    }
  }

  function loop(ts) {
    var dt = Math.min((ts - last) / 1000, 0.05);
    last = ts;
    var anyLive = false;
    for (var i = 0; i < fields.length; i++) {
      if (fields[i].live) { fields[i].frame(dt); anyLive = true; }
    }
    raf = (anyLive && !document.hidden) ? requestAnimationFrame(loop) : null;
  }

  function startLoop() {
    if (raf || document.hidden) return;
    last = performance.now();
    raf = requestAnimationFrame(loop);
  }

  function refresh() {
    buildFields();
    fields.forEach(function (f) { if (!f.live) f.frame(0); });
    startLoop();
  }

  document.addEventListener("visibilitychange", function () {
    if (!document.hidden) startLoop();
  });

  /* ==========================================================
     HEADER
     ========================================================== */
  var hdr = document.getElementById("sl-hdr");
  var burger = document.getElementById("sl-burger");

  function onScroll() {
    if (!hdr) return;
    if (window.scrollY > 30) hdr.classList.add("is-stuck");
    else hdr.classList.remove("is-stuck");
  }
  window.addEventListener("scroll", onScroll, { passive: true });

  if (burger && hdr) {
    burger.addEventListener("click", function () {
      var open = hdr.classList.toggle("is-open");
      burger.setAttribute("aria-expanded", open ? "true" : "false");
    });
    // Escape closes the mobile menu.
    document.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && hdr.classList.contains("is-open")) {
        hdr.classList.remove("is-open");
        burger.setAttribute("aria-expanded", "false");
        burger.focus();
      }
    });
  }

  /* ==========================================================
     SCROLL REVEAL + COUNT-UP
     Opt-in only: the CSS default is fully visible, so nothing can
     end up stranded at opacity 0 if any of this fails.
     ========================================================== */
  var canReveal = ("IntersectionObserver" in window) && !reduce;
  if (canReveal) document.documentElement.classList.add("reveal-on");

  function countUp(el) {
    var target = parseFloat(el.getAttribute("data-count"));
    if (isNaN(target)) return;
    var suffix = el.getAttribute("data-suffix") || "";
    var dur = 1100, t0 = performance.now();
    function tick(now) {
      var k = Math.min((now - t0) / dur, 1);
      k = 1 - Math.pow(1 - k, 3);
      var v = Math.round(target * k);
      el.innerHTML = v.toLocaleString("en-US") + (suffix ? "<sup>" + suffix + "</sup>" : "");
      if (k < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  function runCounts(scope) {
    var list = scope.querySelectorAll("[data-count]");
    for (var i = 0; i < list.length; i++) {
      if (!list[i].getAttribute("data-done")) {
        list[i].setAttribute("data-done", "1");
        countUp(list[i]);
      }
    }
  }

  function armReveals() {
    if (!canReveal) return;
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        en.target.classList.add("in");
        runCounts(en.target);
        io.unobserve(en.target);
      });
    }, { rootMargin: "0px 0px -8% 0px", threshold: 0.12 });

    var list = document.querySelectorAll(".rv");
    for (var i = 0; i < list.length; i++) {
      var el = list[i];
      // Anything already in view on load is revealed immediately, so the first
      // frame is never a blank page.
      if (el.getBoundingClientRect().top < window.innerHeight * 0.92) {
        el.classList.add("in");
        runCounts(el);
      } else {
        io.observe(el);
      }
    }
  }

  /* ---------- resize ---------- */
  var rt, lastW = window.innerWidth;
  window.addEventListener("resize", function () {
    clearTimeout(rt);
    rt = setTimeout(function () {
      // Ignore the mobile address-bar height wobble.
      if (Math.abs(window.innerWidth - lastW) < 2) return;
      lastW = window.innerWidth;
      refresh();
    }, 200);
  });

  /* ---------- boot ---------- */
  function boot() {
    onScroll();
    refresh();
    armReveals();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
  window.addEventListener("load", refresh);
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(refresh);
  }
})();
