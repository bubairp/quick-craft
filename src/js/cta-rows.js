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
 *
 * Spotlight options (data-cta-spotlight): "colour" (layout rowsSpot) — the rows are grayscale
 * and a full-colour copy of them (cloned here, moved in step) shows only inside a soft circle
 * around the cursor (CSS mask, --mx/--my/--spot-r); "gray" (rowsSpotInvert) — the reverse. The circle eases after the pointer, grows in on
 * enter and shrinks away on leave; touch works while dragging a finger. Works with reduced
 * motion too (rows still, spotlight still follows).
 */
const SPEED = [6, 16];        // px per second, random per row (slow and calm, on request)
const SWAY = 0.25;            // each row's speed drifts ± this fraction …
const SWAY_PERIOD = [8, 14];  // … over a random period (s), so the motion feels organic
const RAMP = 1.2;             // s: rows ease in when the section comes into view
const SPOT_RADIUS = 220;      // px: circle around the cursor (spotlight options; enlarged on request)
const SPOT_FOLLOW = 0.25;     // s: how softly the circle follows the pointer (smooth glide)

function setup(root) {
  const rows = [...root.querySelectorAll('.cta-rows__row')];
  if (!rows.length) return;
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Spotlight: a copy on top of the rows, moved together with them, visible only inside the
  // circle. data-cta-spotlight="colour": grey rows, colour circle; "gray": the reverse.
  let colour = null;
  if (root.hasAttribute('data-cta-spotlight')) {
    const invert = root.getAttribute('data-cta-spotlight') === 'gray';
    colour = root.cloneNode(true);
    colour.removeAttribute('data-cta-rows');
    colour.removeAttribute('data-cta-spotlight');
    colour.classList.add('cta-rows--spot', invert ? 'cta-rows--spot-gray' : 'cta-rows--spot-colour');
    root.after(colour);
    setupSpotlight(root.closest('section') || root.parentElement, colour);
  }
  const colourRows = colour ? [...colour.querySelectorAll('.cta-rows__row')] : [];

  const state = rows.map((el, i) => ({
    el,
    twin: colourRows[i] || null,
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
      const tf = `translate3d(${-frac * s.width}px, 0, 0)`;
      s.el.style.transform = tf;
      if (s.twin) s.twin.style.transform = tf;
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

/** Colour circle that follows the pointer over the section (mask on the colour copy). */
function setupSpotlight(area, layer) {
  let x = 0, y = 0, tx = 0, ty = 0, r = 0, tr = 0, lastT = 0, running = false, placed = false;

  const apply = () => {
    layer.style.setProperty('--mx', `${x}px`);
    layer.style.setProperty('--my', `${y}px`);
    layer.style.setProperty('--spot-r', `${r}px`);
  };
  const tick = (t) => {
    const dt = Math.min(0.1, (t - lastT) / 1000);
    lastT = t;
    const k = 1 - Math.exp(-dt / SPOT_FOLLOW);
    x += (tx - x) * k;
    y += (ty - y) * k;
    r += (tr - r) * k;
    apply();
    if (Math.abs(tx - x) > 0.3 || Math.abs(ty - y) > 0.3 || Math.abs(tr - r) > 0.3) requestAnimationFrame(tick);
    else running = false;
  };
  const start = () => {
    if (running) return;
    running = true;
    lastT = performance.now();
    requestAnimationFrame(tick);
  };

  // the pointer's screen position; the circle is placed relative to the section, so it is
  // recalculated on scroll too (otherwise the circle drifts away from a still cursor)
  let cx = 0, cy = 0, inside = false;
  const aim = () => {
    const b = area.getBoundingClientRect();
    tx = cx - b.left;
    ty = cy - b.top;
  };
  area.addEventListener('pointermove', (e) => {
    cx = e.clientX;
    cy = e.clientY;
    inside = true;
    aim();
    if (!placed) { x = tx; y = ty; placed = true; }      // appear where the pointer is
    tr = SPOT_RADIUS;
    start();
  });
  const leave = () => { inside = false; tr = 0; start(); };
  area.addEventListener('pointerleave', leave);
  area.addEventListener('pointercancel', leave);
  window.addEventListener('scroll', () => {
    if (!inside) return;
    aim();
    x = tx;                                              // stay exactly under the cursor
    y = ty;
    apply();
  }, { passive: true });
  apply();
}

export function initCtaRows() {
  document.querySelectorAll('[data-cta-rows]').forEach(setup);
}
