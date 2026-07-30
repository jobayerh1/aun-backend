(function(){
  function initOne(marquee){
    if (!marquee || marquee.__marqueeInit) return;
    var track = marquee.querySelector('.logos-marquee__track');
    if (!track) return;
    marquee.__marqueeInit = true;

    // Config via data-attrs (optional)
    var inertiaAttr = marquee.getAttribute('data-inertia');
    var inertiaOn = inertiaAttr === null ? true : (inertiaAttr !== '0'); // default ON
    var frictionAttr = parseFloat(marquee.getAttribute('data-friction'));
    var friction = (frictionAttr > 0 && frictionAttr < 1) ? frictionAttr : 0.92; // 0.9–0.95 recommended
    var maxMomAttr = parseFloat(marquee.getAttribute('data-max-momentum'));
    var maxMomentum = (maxMomAttr > 0) ? maxMomAttr : 1400; // px/s cap

    // Disable native drag inside the marquee
    var draggables = marquee.querySelectorAll('a, img');
    for (var i=0;i<draggables.length;i++){ draggables[i].setAttribute('draggable','false'); }
    marquee.addEventListener('dragstart', function(ev){
      if (ev.target && ev.target.closest('a, img, .ux-logo, .logo-item')) ev.preventDefault();
    }, true);

    var pos = 0;                 // pixels progressed through the half-track
    var dragging = false;
    var moved = false;
    var dragStartX = 0;
    var dragStartPos = 0;
    var paused = false;
    var lastT = performance.now();
    var half = 1;                // will compute
    var speedSeconds = 35;       // seconds to traverse half
    var speedPxPerSec = 100;     // computed
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Momentum (inertia)
    var momentumVx = 0;          // px/s
    var lastPointerT = 0;
    var lastPointerX = 0;

    function computeMetrics(){
      // Measure after images load
      half = track.scrollWidth / 2;
      var varSpeed = getComputedStyle(track).getPropertyValue('--speed');
      var s = parseFloat(varSpeed);
      speedSeconds = isNaN(s) ? 35 : s;
      speedPxPerSec = half / speedSeconds;
      // If no explicit max set, tie to base speed a bit
      if (!(maxMomAttr > 0)) maxMomentum = Math.max(800, speedPxPerSec * 2);
    }

    function normalize(x){
      x = x % half;
      if (x < 0) x += half;
      return x;
    }

    function apply(){
      track.style.transform = 'translateX(' + (-pos) + 'px)';
    }

    function raf(t){
      var dt = (t - lastT) / 1000;
      lastT = t;
      if (!paused && !dragging && !reduceMotion) {
        // base speed + momentum
        var v = speedPxPerSec + (inertiaOn ? momentumVx : 0);
        pos = normalize(pos + v * dt);
        // decay momentum (exponential)
        if (inertiaOn && momentumVx !== 0){
          var decay = Math.pow(friction, dt * 60); // normalize to 60fps
          momentumVx *= decay;
          if (Math.abs(momentumVx) < 1) momentumVx = 0;
        }
        apply();
      }
      requestAnimationFrame(raf);
    }

    function getClientX(ev){
      if (typeof ev.clientX === 'number') return ev.clientX;
      if (ev.touches && ev.touches[0] && typeof ev.touches[0].clientX === 'number') return ev.touches[0].clientX;
      if (ev.changedTouches && ev.changedTouches[0] && typeof ev.changedTouches[0].clientX === 'number') return ev.changedTouches[0].clientX;
      return 0;
    }

    function onPointerDown(e){
      var hit = e.target.closest('a, img, .ux-logo, .logo-item');
      if (!hit) return;
      dragging = true; moved = false;
      marquee.classList.add('grabbing'); marquee.classList.add('dragging');
      dragStartX = lastPointerX = getClientX(e);
      dragStartPos = pos;
      lastPointerT = performance.now();
      if (e.pointerId && e.target.setPointerCapture) { try { e.target.setPointerCapture(e.pointerId); } catch(_){} }
    }

    function onPointerMove(e){
      if (!dragging) return;
      var x = getClientX(e);
      var now = performance.now();
      var delta = x - dragStartX;
      if (Math.abs(delta) > 3) moved = true;
      pos = normalize(dragStartPos - delta); // right drag moves logos right
      apply();
      // Capture instantaneous velocity for momentum
      var dx = x - lastPointerX;
      var dt = (now - lastPointerT) / 1000;
      if (dt > 0){
        var instV = -(dx / dt); // sign to match pos direction
        // Smooth with EMA
        momentumVx = inertiaOn ? (0.85 * momentumVx + 0.15 * instV) : 0;
        // Clamp
        if (momentumVx >  maxMomentum) momentumVx =  maxMomentum;
        if (momentumVx < -maxMomentum) momentumVx = -maxMomentum;
      }
      lastPointerX = x; lastPointerT = now;
      if (e.preventDefault) e.preventDefault(); // avoid text selection
    }

    function onPointerUp(){
      if (!dragging) return;
      dragging = false;
      marquee.classList.remove('grabbing'); marquee.classList.remove('dragging');
      // If no real movement, don't nudge
      if (!moved) momentumVx = 0;
    }

    function onClick(ev){
      if (moved) { ev.preventDefault(); ev.stopImmediatePropagation(); moved = false; }
    }

    function onHoverEnter(e){
      if (e.target.closest('a, img, .ux-logo, .logo-item')) paused = true;
    }
    function onHoverLeave(e){
      if (e.target.closest('a, img, .ux-logo, .logo-item')) paused = false;
    }

    function readyMetrics(){ computeMetrics(); apply(); }
    if (document.readyState === 'complete') readyMetrics();
    else window.addEventListener('load', readyMetrics);
    window.addEventListener('resize', computeMetrics);
    computeMetrics();

    marquee.addEventListener('pointerdown', onPointerDown);
    window.addEventListener('pointermove', onPointerMove, { passive: false });
    window.addEventListener('pointerup', onPointerUp);
    marquee.addEventListener('touchstart', onPointerDown, { passive: true });
    window.addEventListener('touchmove', onPointerMove, { passive: false });
    window.addEventListener('touchend', onPointerUp);

    marquee.addEventListener('click', onClick, true);
    marquee.addEventListener('mouseover', onHoverEnter);
    marquee.addEventListener('mouseout', onHoverLeave);
    marquee.addEventListener('focusin', onHoverEnter);
    marquee.addEventListener('focusout', onHoverLeave);

    requestAnimationFrame(function(t){ lastT = t; requestAnimationFrame(raf); });
  }

  var mo = null;
  function initAll(){
    var nodes = document.querySelectorAll('.logos-marquee');
    for (var i=0;i<nodes.length;i++) initOne(nodes[i]);
    // Once a marquee is found and initialised, stop observing the DOM.
    // Perpetual whole-document observation is a needless performance cost.
    if (nodes.length && mo){ mo.disconnect(); mo = null; }
  }

  if (document.readyState !== 'loading') initAll();
  document.addEventListener('DOMContentLoaded', initAll);
  window.addEventListener('load', initAll);

  mo = new MutationObserver(function(){ initAll(); });
  mo.observe(document.documentElement, { childList: true, subtree: true });
  // Safety net: stop observing a few seconds after load regardless, so we never
  // keep an observer running for the life of the page.
  window.addEventListener('load', function(){
    setTimeout(function(){ if (mo){ mo.disconnect(); mo = null; } }, 4000);
  });
})();
