/**
 * "Get the app" image spiral (ctaBanner with images). Measured on the reference site
 * (partiful.com) at 1600×731 and 375×800, Nov 2026:
 *
 *  - The images lie on an oval spiral around the centre (oval 1.34× wider than tall),
 *    22 images per turn, each turn ≈ 7.7% of the section width further out (ours 6.8%: the
 *    rings were made a little tighter on request), until the spiral
 *    reaches the section's corners (desktop ≈ 5.3 turns / 125 images, phone ≈ 9 / 194).
 *  - Each image is turned along the spiral (90° to its angle) and its size grows with its
 *    distance from the centre (tiny in the middle, ≈ 0.9 × the ring spacing at the edge),
 *    with some random variation per image.
 *  - The whole spiral turns slowly anticlockwise, ≈ 2.5° per second. Scroll does not affect it.
 *
 * Markup: templates/_blocks/ctaBanner.twig — <ul data-cta-spiral><li class="cta-spiral__item">…
 * (more items than needed; the ones beyond the spiral's end are hidden). Re-flows on resize.
 * Reduced motion: laid out, not turning. Without JS: the list stays hidden (decoration only).
 */
const OVAL = 1.34;          // width / height of the spiral
const PER_TURN = 22;        // images per turn
const GAP = 0.068;          // ring spacing / section width (reference ≈ 0.077; a bit tighter on request)
const START = 0.015;        // innermost radius / section width
const SIZE = 1.0;           // image size at the outer edge / ring spacing (largest ≈ 135px at 1600)
const SPEED = -2.5;         // degrees per second (negative = anticlockwise on screen)

function setup(root) {
  const items = [...root.querySelectorAll('.cta-spiral__item')];
  if (!items.length) return;
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  // stable "random" size variation per image (0.75–1.25)
  const jitter = items.map((_, i) => 0.75 + 0.5 * (((Math.sin(i * 12.9898) * 43758.5453) % 1 + 1) % 1));

  let count = 0, gap = 0, r0 = 0;
  let angle = 0, lastT = 0, visible = false, running = false;

  const layout = () => {
    const w = root.clientWidth, h = root.clientHeight;
    gap = w * GAP;
    r0 = w * START;
    const outer = Math.hypot(w / 2 / OVAL, h / 2);          // reach the corners
    count = Math.min(items.length, Math.ceil(((outer - r0) / gap) * PER_TURN) + 1);
    items.forEach((el, i) => {
      if (i >= count) { el.hidden = true; return; }
      el.hidden = false;
      const r = r0 + (gap * i) / PER_TURN;
      el.style.width = `${Math.max(2, SIZE * gap * (r / outer) * jitter[i])}px`;
    });
    place();
  };

  const place = () => {
    for (let i = 0; i < count; i++) {
      const r = r0 + (gap * i) / PER_TURN;
      const a = (i * 360) / PER_TURN + angle;               // degrees
      const rad = (a * Math.PI) / 180;
      items[i].style.transform =
        `translate(-50%, -50%) translate(${Math.cos(rad) * r * OVAL}px, ${Math.sin(rad) * r}px) rotate(${a + 90}deg)`;
    }
  };

  const tick = (t) => {
    const dt = Math.min(0.05, (t - lastT) / 1000);
    lastT = t;
    angle += SPEED * dt;
    place();
    if (visible) requestAnimationFrame(tick);
    else running = false;
  };

  new ResizeObserver(layout).observe(root);
  if (!reduce) {
    new IntersectionObserver(([entry]) => {
      visible = entry.isIntersecting;
      if (visible && !running) {
        running = true;
        lastT = performance.now();
        requestAnimationFrame(tick);
      }
    }).observe(root);
  }
  root.classList.add('is-ready');
  layout();
}

export function initCtaSpiral() {
  document.querySelectorAll('[data-cta-spiral]').forEach(setup);
}
