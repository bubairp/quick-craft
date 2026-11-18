/**
 * Scroll slider (reference site: "Text blast / Collect info / Build hype").
 * A sticky full-screen panel; as the page scrolls, each slide's text moves up and out
 * while the next slide's phone screen slides up over the previous one (stacking cards).
 *
 * Markup (templates/_blocks/featureRows.twig, layout "stickyPhone"):
 *   <div data-scroll-slider="3">                    ← number of slides
 *     <div data-scroll-slider-track hidden>          ← desktop version, shown by JS
 *       <div class="sticky top-0 h-screen overflow-hidden">
 *         <div data-slide="0">…text…</div>           ← moves with scroll
 *         <div data-slide-media="0">…phone…</div>    ← stacks
 *       </div>
 *     </div>
 *     <div data-scroll-slider-fallback>…stacked rows…</div>
 *   </div>
 *
 * Active only at ≥1024px and without prefers-reduced-motion; otherwise the fallback shows.
 */
const DESKTOP = '(min-width: 1024px)';
const SCREENS_PER_SLIDE = 1.35;   // scroll distance per slide, in viewport heights
const MOVE_WINDOW = [0.25, 0.75]; // part of each step during which the slide moves (rest = pause)

const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

function setup(root) {
  const count = parseInt(root.dataset.scrollSlider, 10) || 0;
  const track = root.querySelector('[data-scroll-slider-track]');
  const fallback = root.querySelector('[data-scroll-slider-fallback]');
  if (count < 2 || !track || !fallback) return;

  const slides = [...track.querySelectorAll('[data-slide]')];
  const media = [...track.querySelectorAll('[data-slide-media]')];
  const mq = window.matchMedia(DESKTOP);
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  let active = false;
  let frame = 0;

  const render = () => {
    frame = 0;
    if (!active) return;
    const rect = track.getBoundingClientRect();
    const scrollable = track.offsetHeight - window.innerHeight;
    const progress = clamp(-rect.top / scrollable, 0, 1);       // 0 … 1 through the section
    const u = progress * (count - 1);                              // 0 … count-1
    const step = Math.min(Math.floor(u), count - 2);
    const local = clamp((u - step - MOVE_WINDOW[0]) / (MOVE_WINDOW[1] - MOVE_WINDOW[0]), 0, 1);
    const pos = step + ease(local);                                // eased slide position

    slides.forEach((el, i) => {
      el.style.transform = `translate3d(0, ${(i - pos) * 100}%, 0)`;
      el.inert = Math.abs(i - pos) > 0.5;
    });
    media.forEach((el, i) => {
      el.style.transform = `translate3d(0, ${Math.max(0, i - pos) * 110}%, 0)`;
    });
  };

  const onScroll = () => { if (!frame) frame = requestAnimationFrame(render); };

  const apply = () => {
    const on = mq.matches && !reduce.matches;
    if (on === active) return;
    active = on;
    track.hidden = !on;
    fallback.hidden = on;
    if (on) {
      track.style.height = `${count * SCREENS_PER_SLIDE * 100}vh`;
      window.addEventListener('scroll', onScroll, { passive: true });
      window.addEventListener('resize', onScroll);
      render();
    } else {
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onScroll);
      [...slides, ...media].forEach((el) => { el.style.transform = ''; el.inert = false; });
    }
  };

  mq.addEventListener('change', apply);
  reduce.addEventListener('change', apply);
  apply();
}

export function initScrollSliders() {
  document.querySelectorAll('[data-scroll-slider]').forEach(setup);
}
