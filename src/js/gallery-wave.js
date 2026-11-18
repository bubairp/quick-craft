/**
 * Gallery "wave" (reference site: invite cards drift endlessly along a diagonal,
 * and move faster while the page scrolls).
 *
 * Markup: templates/_blocks/imageGallery.twig (layout "fan")
 *   <div class="gallery-fan" data-gallery-wave>
 *     <ul class="gallery-fan__row"><li class="gallery-fan__item">…</li>…</ul>
 *   </div>
 * JS adds .is-wave (cards become absolutely positioned, see app.css) and moves each
 * card along the line every frame. Without JS or with reduced motion: static fan.
 */
const STEP_X = 96;          // px between cards along x (reference ≈ 91)
const SLOPE = -0.3;         // y per x: the line rises to the right (reference ≈ -0.32)
const DRIFT = 28;           // px per second along x when idle
const SCROLL_FACTOR = 1.2;  // extra movement per scrolled px

function setup(root) {
  const items = [...root.querySelectorAll('.gallery-fan__item')];
  if (items.length < 3) return;

  root.classList.add('is-wave');
  const loop = items.length * STEP_X;     // total length of the conveyor
  let offset = 0;
  let lastY = window.scrollY;
  let lastT = performance.now();
  let visible = false;

  const place = () => {
    const width = root.clientWidth;
    items.forEach((el, i) => {
      // position along x, wrapped into [-STEP_X*2, loop - STEP_X*2)
      let x = (i * STEP_X - offset) % loop;
      if (x < 0) x += loop;
      x -= STEP_X * 2;
      const y = x * SLOPE;
      el.style.translate = `${x}px ${y}px`;
      el.style.zIndex = String(Math.round(width - x)); // cards on the left overlap those on the right
    });
  };

  const tick = (t) => {
    const dt = Math.min(0.05, (t - lastT) / 1000);
    lastT = t;
    const y = window.scrollY;
    offset += DRIFT * dt + Math.abs(y - lastY) * SCROLL_FACTOR;
    lastY = y;
    place();
    if (visible) requestAnimationFrame(tick);
  };

  // Only animate while on screen
  new IntersectionObserver(([entry]) => {
    const was = visible;
    visible = entry.isIntersecting;
    if (visible && !was) {
      lastT = performance.now();
      lastY = window.scrollY;
      requestAnimationFrame(tick);
    }
  }).observe(root);

  place();
}

export function initGalleryWave() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.querySelectorAll('[data-gallery-wave]').forEach(setup);
}
