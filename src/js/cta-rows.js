/**
 * "Get the app" collage rows (ctaBanner layout "rows"; an alternative to cta-spiral.js).
 *
 * Markup: templates/_blocks/ctaBanner.twig —
 *   <div data-cta-rows><div class="cta-rows__row"> [set of images] [same set again] </div>…</div>
 *
 * Each row gets a random speed (SPEED range, px/s) and direction on every page load; the speed
 * drifts gently (±SWAY over a random period) and eases in when the section comes into view;
 * slides sideways forever: the row holds its set twice, so moving it by exactly one set's
 * width brings it back to the same picture (seamless loop). Images keep their shape (straight
 * squares — no tilt, on request). Rows only move while the section is on screen.
 * Reduced motion: still.
 */
const SPEED = [10, 26];       // px per second, random per row (calm, so it reads as smooth)
const SWAY = 0.25;            // each row's speed drifts ± this fraction …
const SWAY_PERIOD = [8, 14];  // … over a random period (s), so the motion feels organic
const RAMP = 1.2;             // s: rows ease in when the section comes into view

function setup(root) {
  const rows = [...root.querySelectorAll('.cta-rows__row')];
  if (!rows.length) return;
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const state = rows.map((el) => ({
    el,
    speed: SPEED[0] + Math.random() * (SPEED[1] - SPEED[0]),
    dir: Math.random() < 0.5 ? -1 : 1,
    period: SWAY_PERIOD[0] + Math.random() * (SWAY_PERIOD[1] - SWAY_PERIOD[0]),
    phase: Math.random() * Math.PI * 2,
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

  let visible = false, running = false, lastT = 0, clock = 0, ramp = 0;
  const tick = (t) => {
    const dt = Math.min(0.1, (t - lastT) / 1000);
    lastT = t;
    clock += dt;
    ramp = Math.min(1, ramp + dt / RAMP);
    const ease = ramp * ramp * (3 - 2 * ramp);                  // smoothstep ease-in
    state.forEach((s) => {
      if (!s.width) return;
      const v = s.speed * (1 + SWAY * Math.sin((2 * Math.PI * clock) / s.period + s.phase));
      s.offset += (-s.dir * v * ease * dt) / s.width;
    });
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
        ramp = 0;                    // ease in again each time the section comes into view
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
