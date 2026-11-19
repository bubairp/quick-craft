/**
 * DEMO — Swiper version of the scroll slider (compare with scroll-slider.js, used on Home).
 *
 * Markup: templates/_blocks/featureRows.twig, layout "stickySwiper"
 *   <div data-scroll-swiper="3">
 *     <div data-scroll-swiper-track hidden>                  ← desktop, shown by JS
 *       <div class="sticky top-0 h-screen">
 *         <div class="swiper" data-scroll-swiper-text>…      ← vertical, one row per slide
 *         <div class="swiper" data-scroll-swiper-media>…     ← phone screens, creative "stack"
 *     <div class="swiper" data-scroll-swiper-mobile>…        ← mobile: swipeable cards
 *
 * Desktop (≥1024px, no reduced motion): our code still measures how far the page has scrolled
 * through the section (same maths as scroll-slider.js: 1.35 screens per slide, move during
 * 25–75% of each step, eased) and hands that position to both Swipers with setProgress(p, 0).
 * Swiper does the rendering; touch/drag is off there because scrolling drives it.
 * Otherwise: the mobile Swiper (drag/swipe, keyboard).
 */
const DESKTOP = '(min-width: 1024px)';
const SCREENS_PER_SLIDE = 1.35;
const MOVE_WINDOW = [0.25, 0.75];

const clamp = (v, min, max) => Math.min(max, Math.max(min, v));
const ease = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);

function setup(root, Swiper, { EffectCreative, Keyboard, A11y }) {
  const count = parseInt(root.dataset.scrollSwiper, 10) || 0;
  const track = root.querySelector('[data-scroll-swiper-track]');
  const textEl = root.querySelector('[data-scroll-swiper-text]');
  const mediaEl = root.querySelector('[data-scroll-swiper-media]');
  const mobileEl = root.querySelector('[data-scroll-swiper-mobile]');
  if (count < 2 || !track || !textEl || !mediaEl || !mobileEl) return;

  const mq = window.matchMedia(DESKTOP);
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
  let text = null;
  let media = null;
  let mobile = null;
  let frame = 0;

  const render = () => {
    frame = 0;
    if (!text) return;
    const rect = track.getBoundingClientRect();
    const scrollable = track.offsetHeight - window.innerHeight;
    const progress = clamp(-rect.top / scrollable, 0, 1);
    const u = progress * (count - 1);
    const step = Math.min(Math.floor(u), count - 2);
    const local = clamp((u - step - MOVE_WINDOW[0]) / (MOVE_WINDOW[1] - MOVE_WINDOW[0]), 0, 1);
    const pos = step + ease(local);
    const p = pos / (count - 1);
    text.setProgress(p, 0);
    media.setProgress(p, 0);
    // Creative effect stacks by rounded progress, so an incoming phone would sit *under* the
    // current one for the first half of its move. Later screens always on top (as in Home's).
    media.slides.forEach((el, i) => { el.style.zIndex = String(i + 1); });
    // only the row in view is focusable / readable
    text.slides.forEach((el, i) => { el.inert = Math.abs(i - pos) > 0.5; });
  };
  const onScroll = () => { if (!frame) frame = requestAnimationFrame(render); };

  const desktopOn = () => {
    track.hidden = false;
    mobileEl.hidden = true;
    track.style.height = `${count * SCREENS_PER_SLIDE * 100}vh`;
    const common = { allowTouchMove: false, speed: 0, watchSlidesProgress: true };
    text = new Swiper(textEl, { ...common, direction: 'vertical', slidesPerView: 1 });
    media = new Swiper(mediaEl, {
      ...common,
      modules: [EffectCreative],
      effect: 'creative',
      // next screen comes up from below; earlier screens stay put underneath (stacking cards)
      creativeEffect: { limitProgress: 2, prev: { translate: [0, 0, 0] }, next: { translate: [0, '110%', 0] } },
    });
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    render();
  };
  const desktopOff = () => {
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('resize', onScroll);
    text?.destroy(true, true);
    media?.destroy(true, true);
    text = media = null;
    track.hidden = true;
    track.style.height = '';
    textEl.querySelectorAll('.swiper-slide').forEach((el) => { el.inert = false; });
    mobileEl.hidden = false;
  };

  const mobileOn = () => {
    if (mobile) return;
    mobile = new Swiper(mobileEl, {
      modules: [Keyboard, A11y],
      slidesPerView: 'auto',
      spaceBetween: 24,
      slidesOffsetBefore: 16,
      slidesOffsetAfter: 16,
      grabCursor: true,
      speed: reduce.matches ? 0 : 500,
      keyboard: { enabled: true, onlyInViewport: true },
      a11y: { enabled: true },
    });
  };
  const mobileOff = () => { mobile?.destroy(true, true); mobile = null; };

  const apply = () => {
    const scrollDriven = mq.matches && !reduce.matches;
    if (scrollDriven && !text) { mobileOff(); desktopOn(); }
    if (!scrollDriven) { if (text) desktopOff(); mobileOn(); }
  };
  mq.addEventListener('change', apply);
  reduce.addEventListener('change', apply);
  apply();
}

export async function initScrollSwipers() {
  const roots = document.querySelectorAll('[data-scroll-swiper]');
  if (!roots.length) return;
  const [{ default: Swiper }, modules] = await Promise.all([
    import('swiper'),
    import('swiper/modules'),
    import('swiper/css'),
  ]);
  roots.forEach((root) => setup(root, Swiper, modules));
}
