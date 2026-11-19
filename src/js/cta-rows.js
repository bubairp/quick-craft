/**
 * "Get the app" collage rows (ctaBanner layout "rows"; an alternative to cta-spiral.js).
 *
 * Markup: templates/_blocks/ctaBanner.twig —
 *   <div data-cta-rows><div class="cta-rows__row"> [set of images] [same set again] </div>…</div>
 *
 * Each row gets a random speed (SPEED range, px/s) and direction on every page load, and
 * slides sideways forever: the row holds its set twice, so moving it by exactly one set's
 * width brings it back to the same picture (seamless loop). Images get a small random tilt.
 * Rows only move while the section is on screen. Reduced motion: still (tilt kept).
 */
const SPEED = [14, 42];       // px per second, random per row
const TILT = 6;               // max ± degrees per image

function setup(root) {
  const rows = [...root.querySelectorAll('.cta-rows__row')];
  if (!rows.length) return;
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  root.querySelectorAll('.cta-rows__item').forEach((el) => {
    el.style.rotate = `${(Math.random() * 2 - 1) * TILT}deg`;
  });

  const state = rows.map((el) => ({
    el,
    speed: SPEED[0] + Math.random() * (SPEED[1] - SPEED[0]),
    dir: Math.random() < 0.5 ? -1 : 1,
    offset: Math.random(),          // start at a random point of the loop (fraction of a set)
    width: 0,                       // width of one set, px
  }));

  const measure = () => {
    state.forEach((s) => { s.width = s.el.scrollWidth / 2; });
    place();
  };

  const place = () => {
    state.forEach((s) => {
      if (!s.width) return;
      // offset is a fraction of one set; x runs from −width to 0 and wraps
      const frac = ((s.offset % 1) + 1) % 1;
      s.el.style.transform = `translate3d(${-frac * s.width}px, 0, 0)`;
    });
  };

  let visible = false, running = false, lastT = 0;
  const tick = (t) => {
    const dt = Math.min(0.05, (t - lastT) / 1000);
    lastT = t;
    state.forEach((s) => { if (s.width) s.offset += (-s.dir * s.speed * dt) / s.width; });
    place();
    if (visible) requestAnimationFrame(tick);
    else running = false;
  };

  new ResizeObserver(measure).observe(root);
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
  measure();
}

export function initCtaRows() {
  document.querySelectorAll('[data-cta-rows]').forEach(setup);
}
