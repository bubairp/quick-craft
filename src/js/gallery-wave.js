/**
 * Gallery "wave" — "A million reasons to hang" (imageGallery layout "fan").
 * Behaviour measured on the reference site (partiful.com) in Chrome/Playwright, Nov 2026:
 *
 *  - The gallery is one viewport tall (≥1200px wide; 596px below) and the cards are centred in
 *    it. Card size = 16.1% of the gallery width (32.2% below 810px); everything else scales
 *    with the card, so resizing the window re-flows the whole thing (ResizeObserver).
 *  - 20 cards on a path: spacing 0.403 × card, slope set by the gallery's proportions, plus a gentle wave
 *    (0.07 × card high, one wavelength per loop) that travels left; y is clamped so cards
 *    stay inside the gallery (half height − 0.632 × card − 12px). Left cards sit on top.
 *  - Cards drift left at 0.175 steps/s. Page scroll pushes them along, direction-aware
 *    (down = forward/left, up = back/right), 0.3px per scrolled px.
 *  - Drag (mouse/finger): the row follows the pointer smoothly and glides on after release.
 *  - Hover (mouse): the drift pauses; the card turns 12° and lifts 0.705 × card; the two
 *    cards on its left (which overlap it) slide away along the path (0.286 / 0.044 × card).
 *    The lift/push transitions are CSS (app.css, .gallery-fan__*).
 *
 * Markup: templates/_blocks/imageGallery.twig (layout "fan")
 *   <div class="gallery-fan" data-gallery-wave>
 *     <ul class="gallery-fan__row"><li class="gallery-fan__item">
 *       <div class="gallery-fan__lift"><div class="gallery-fan__media"><img …></div></div>
 *     </li>…</ul>
 *   </div>
 * Without JS: a horizontal scroll row. Reduced motion: laid out on the path, no automatic
 * movement (drag still works).
 */
const PHONE = 810;            // px: below this, cards are twice as large relative to width
const CARD = 0.161;           // card size / gallery width
const CARD_PHONE = 0.322;
const STEP = 0.403;           // spacing / card
// The path rises to the right and reaches the top/bottom limit at the outermost cards:
// slope = −limit / ((n/2 − 0.5) × spacing). Taller galleries → steeper (measured −0.15…−0.53).
const WAVE = 0.0705;          // wave height / card
const WAVE_SPEED = 1.5;       // steps per second, travelling left
const DRIFT = 0.175;          // steps per second, cards move left
const SCROLL = 0.3;           // card px per scrolled px
const HALF_HEIGHT = 0.632;    // half the skewed card's height / card
const EDGE = 12;              // px kept free at the top and bottom
const PUSH = [0.286, 0.044];  // left neighbours of a hovered card slide away (× card)
const FOLLOW = 0.6;           // s: the row eases towards the pointer (reference feels floaty)
const GLIDE = 0.35;           // s: time constant of the momentum after a drag
const GLIDE_MAX = 2.5;        // steps/s: cap, so a fast flick only glides a little further
                              // (reference: a 600px drag moves the row ≈ 690px in total)
const DRAG_THRESHOLD = 4;     // px before a press counts as a drag

function setup(root) {
  const items = [...root.querySelectorAll('.gallery-fan__item')];
  const n = items.length;
  if (n < 3) return;
  const lifts = items.map((el) => el.querySelector('.gallery-fan__lift'));
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  root.classList.add('is-wave');

  let card = 0, step = 1, clampY = 0, slope = 0;   // layout (px; slope = dy/dx)
  let target = 0, current = 0;            // row offset, in steps (grows = cards move left)
  let wave = 0;                           // wave phase, in steps
  let glide = 0;                          // momentum after a drag, steps per second
  let hovered = -1;
  let visible = false, running = false;
  let lastT = 0, lastY = window.scrollY;

  // ── layout (re-run on resize) ────────────────────────────────────────────
  const layout = () => {
    const w = root.clientWidth, h = root.clientHeight;
    card = w * (window.innerWidth < PHONE ? CARD_PHONE : CARD);
    step = card * STEP;
    clampY = Math.max(0, h / 2 - card * HALF_HEIGHT - EDGE);
    slope = -clampY / ((n / 2 - 0.5) * step);
    root.style.setProperty('--card', `${card}px`);
    setPush();
    place();
  };

  // ── place every card on the path ─────────────────────────────────────────
  const place = () => {
    items.forEach((el, i) => {
      let s = (i - current) % n;              // position along the row, in steps
      if (s < -n / 2) s += n;
      if (s >= n / 2) s -= n;
      const x = s * step;
      const y = Math.max(-clampY, Math.min(clampY,
        slope * x + card * WAVE * Math.sin((2 * Math.PI * (s + wave)) / n)));
      el.style.transform = `translate3d(${x}px, ${y}px, 0)`;
      el.style.zIndex = String(Math.round((n / 2 - s) * 10));   // left cards on top
    });
  };

  // ── hover: lift the card, slide its two left neighbours away ─────────────
  const setPush = () => {
    lifts.forEach((el) => { el.style.removeProperty('--push-x'); el.style.removeProperty('--push-y'); });
    if (hovered < 0) return;
    PUSH.forEach((amount, k) => {
      const el = lifts[(hovered - k - 1 + n) % n];
      const dx = -amount * card;
      el.style.setProperty('--push-x', `${dx}px`);
      el.style.setProperty('--push-y', `${slope * dx}px`);
    });
  };
  const hover = (i) => {
    if (i === hovered) return;
    if (hovered >= 0) items[hovered].classList.remove('is-hovered');
    hovered = i;
    if (hovered >= 0) items[hovered].classList.add('is-hovered');
    setPush();
  };
  items.forEach((el, i) => {
    el.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse' && !dragging) hover(i); });
    el.addEventListener('pointerleave', (e) => { if (e.pointerType === 'mouse' && hovered === i) hover(-1); });
  });

  // ── animation loop ───────────────────────────────────────────────────────
  const tick = (t) => {
    const dt = Math.min(0.05, (t - lastT) / 1000);
    lastT = t;
    const y = window.scrollY;
    if (!reduce) {
      if (!dragging && hovered < 0) target += DRIFT * dt;
      if (!dragging) target += ((y - lastY) * SCROLL) / step;   // signed: up scroll moves back
      wave += WAVE_SPEED * dt;
    }
    lastY = y;
    if (!dragging && Math.abs(glide) > 0.01) {
      target += glide * dt;
      glide *= Math.exp(-dt / GLIDE);
    } else if (!dragging) {
      glide = 0;
    }
    current += (target - current) * (1 - Math.exp(-dt / FOLLOW));
    place();
    const settling = Math.abs(target - current) > 0.001 || glide !== 0;
    if (visible || dragging || settling) requestAnimationFrame(tick);
    else running = false;
  };
  const start = () => {
    if (running) return;
    running = true;
    lastT = performance.now();
    lastY = window.scrollY;
    requestAnimationFrame(tick);
  };

  // ── drag ─────────────────────────────────────────────────────────────────
  let dragging = false, pressed = false, moved = false, pointerId = null;
  let startX = 0, startY = 0, prevX = 0, prevT = 0;
  let samples = [];                      // recent [time, x] pointer samples, for release speed
  root.addEventListener('pointerdown', (e) => {
    if (e.button !== 0) return;
    pressed = true;
    moved = false;
    pointerId = e.pointerId;
    startX = prevX = e.clientX;
    startY = e.clientY;
    prevT = performance.now();
    samples = [[prevT, prevX]];
    glide = 0;
  });
  window.addEventListener('pointermove', (e) => {
    if (!pressed || e.pointerId !== pointerId) return;
    if (!moved) {
      const dx = e.clientX - startX, dy = e.clientY - startY;
      if (Math.hypot(dx, dy) < DRAG_THRESHOLD) return;
      if (e.pointerType !== 'mouse' && Math.abs(dy) > Math.abs(dx)) { pressed = false; return; } // vertical swipe = page scroll
      moved = dragging = true;
      hover(-1);
      root.classList.add('is-dragging');
      try { root.setPointerCapture(pointerId); } catch { /* pointer already gone */ }
      start();
    }
    const now = performance.now();
    target -= (e.clientX - prevX) / step;                     // dragging right moves cards right
    prevX = e.clientX;
    prevT = now;
    samples.push([now, e.clientX]);
    while (samples.length > 2 && now - samples[0][0] > 100) samples.shift();
  });
  const end = (e) => {
    if (!pressed || (e && e.pointerId !== undefined && e.pointerId !== pointerId)) return;
    pressed = false;
    if (dragging) {
      dragging = false;
      // release speed over the last ~100ms (not the last two events, which can be 1ms apart),
      // capped; no momentum if the pointer had stopped before release
      const now = performance.now();
      const [t0, x0] = samples[0];
      const v = now - prevT < 80 && now - t0 > 0 ? -((prevX - x0) / step) / ((now - t0) / 1000) : 0;
      glide = Math.max(-GLIDE_MAX, Math.min(GLIDE_MAX, v));
      root.classList.remove('is-dragging');
      try { if (root.hasPointerCapture(pointerId)) root.releasePointerCapture(pointerId); } catch { /* ignore */ }
      start();
    }
    pointerId = null;
  };
  window.addEventListener('pointerup', end);
  window.addEventListener('pointercancel', end);
  window.addEventListener('blur', () => end());
  root.addEventListener('click', (e) => { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
  root.addEventListener('dragstart', (e) => e.preventDefault());

  // ── images: lazy loading can't tell when a moving card comes into view (the cards are
  //    positioned by transforms), so cards scrolled in by the drift/drag showed up empty.
  //    Load all of them once the gallery is within a screen of the viewport.
  const preload = new IntersectionObserver(([entry]) => {
    if (!entry.isIntersecting) return;
    preload.disconnect();
    root.querySelectorAll('img[loading="lazy"]').forEach((img) => {
      img.loading = 'eager';
      img.decode?.().catch(() => {});
    });
  }, { rootMargin: '100% 0px' });
  preload.observe(root);

  // ── resize + visibility ──────────────────────────────────────────────────
  new ResizeObserver(layout).observe(root);
  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (visible) start();
  }).observe(root);

  layout();
}

export function initGalleryWave() {
  document.querySelectorAll('[data-gallery-wave]').forEach(setup);
}
