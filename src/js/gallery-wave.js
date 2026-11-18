/**
 * Gallery "wave" (reference site: invite cards drift endlessly along a diagonal,
 * move faster while the page scrolls, and can be dragged with the mouse/finger).
 *
 * Markup: templates/_blocks/imageGallery.twig (layout "fan")
 *   <div class="gallery-fan" data-gallery-wave>
 *     <ul class="gallery-fan__row"><li class="gallery-fan__item">…</li>…</ul>
 *   </div>
 * JS adds .is-wave (cards become absolutely positioned, see app.css) and moves each
 * card along the line every frame. Without JS or with reduced motion: static fan.
 *
 * Drag: pointer movement is projected onto the diagonal; on release the cards keep
 * gliding with momentum, then the idle drift takes over again.
 */
const STEP_X = 96;          // px between cards along x (reference ≈ 91)
const SLOPE = -0.3;         // y per x: the line rises to the right (reference ≈ -0.32)
const DRIFT = 28;           // px per second along x when idle
const SCROLL_FACTOR = 1.2;  // extra movement per scrolled px
const FRICTION = 0.94;      // momentum decay per frame after a drag
const DRAG_THRESHOLD = 4;   // px before a press counts as a drag

function setup(root) {
  const items = [...root.querySelectorAll('.gallery-fan__item')];
  if (items.length < 3) return;

  root.classList.add('is-wave');
  const loop = items.length * STEP_X;     // total length of the conveyor
  let offset = 0;
  let lastY = window.scrollY;
  let lastT = performance.now();
  let visible = false;
  let running = false;

  // drag state
  let dragging = false;
  let pointerId = null;
  let startX = 0;
  let startY = 0;
  let prevX = 0;
  let prevY = 0;
  let moved = false;
  let velocity = 0;                        // px per frame along x, applied after release

  const place = () => {
    const width = root.clientWidth;
    items.forEach((el, i) => {
      let x = (i * STEP_X - offset) % loop;
      if (x < 0) x += loop;
      x -= STEP_X * 2;
      el.style.translate = `${x}px ${x * SLOPE}px`;
      el.style.zIndex = String(Math.round(width - x)); // cards on the left overlap those on the right
    });
  };

  // pointer delta → movement along the diagonal (x units)
  const along = (dx, dy) => (dx + SLOPE * dy) / (1 + SLOPE * SLOPE);

  const tick = (t) => {
    const dt = Math.min(0.05, (t - lastT) / 1000);
    lastT = t;
    const y = window.scrollY;
    if (!dragging) {
      if (Math.abs(velocity) > 0.05) {
        offset -= velocity;
        velocity *= FRICTION;
      } else {
        velocity = 0;
        offset += DRIFT * dt;
      }
      offset += Math.abs(y - lastY) * SCROLL_FACTOR;
    }
    lastY = y;
    place();
    if (visible || dragging || velocity) requestAnimationFrame(tick);
    else running = false;
  };

  const start = () => {
    if (running) return;
    running = true;
    lastT = performance.now();
    lastY = window.scrollY;
    requestAnimationFrame(tick);
  };

  // ── Drag ───────────────────────────────────────────────────────────────
  root.addEventListener('pointerdown', (e) => {
    if (e.button !== 0) return;
    dragging = true;
    moved = false;
    pointerId = e.pointerId;
    startX = prevX = e.clientX;
    startY = prevY = e.clientY;
    velocity = 0;
    start();
  });

  window.addEventListener('pointermove', (e) => {
    if (!dragging || e.pointerId !== pointerId) return;
    if (!moved && Math.hypot(e.clientX - startX, e.clientY - startY) > DRAG_THRESHOLD) {
      moved = true;
      root.classList.add('is-dragging');
      try { root.setPointerCapture(pointerId); } catch { /* pointer already gone */ }
    }
    if (!moved) return;
    const d = along(e.clientX - prevX, e.clientY - prevY);
    offset -= d;          // dragging right moves the cards right
    velocity = d;         // last frame's movement becomes the release momentum
    prevX = e.clientX;
    prevY = e.clientY;
  });

  const end = (e) => {
    if (!dragging || (e && e.pointerId !== undefined && e.pointerId !== pointerId)) return;
    dragging = false;
    root.classList.remove('is-dragging');
    try { if (root.hasPointerCapture(pointerId)) root.releasePointerCapture(pointerId); } catch { /* ignore */ }
    pointerId = null;
  };
  window.addEventListener('pointerup', end);
  window.addEventListener('pointercancel', end);
  // never leave a drag stuck (e.g. mouse released outside the window)
  window.addEventListener('blur', () => end());

  // a drag must not trigger a click on a card link, nor drag the image out
  root.addEventListener('click', (e) => { if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; } }, true);
  root.addEventListener('dragstart', (e) => e.preventDefault());

  // Only animate while on screen
  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (visible) start();
  }).observe(root);

  place();
}

export function initGalleryWave() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.querySelectorAll('[data-gallery-wave]').forEach(setup);
}
