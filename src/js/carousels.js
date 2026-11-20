/**
 * Swiper carousels. Markup contract:
 *
 *   <div data-carousel[="press"]>
 *     <div class="swiper"><ul class="swiper-wrapper"><li class="swiper-slide w-72!">…</li></ul></div>
 *     <button data-carousel-prev>…</button> <button data-carousel-next>…</button>   (optional)
 *   </div>
 *
 * Variants:
 *   data-carousel          plain row with prev/next
 *   data-carousel="loop"   testimonials row (templates/_blocks/testimonials.twig, carousel layout):
 *                          endless loop, auto-advances every 3.5s (paused on hover; off for reduced motion)
 *   data-carousel="words"  rotating words in a banner heading: vertical loop, creative effect
 *                          (old word up + out, next up from below), ~1s per word; off for reduced motion
 *   data-carousel="logos"  logo ticker (testimonials › logoRow): endless loop at a steady speed,
 *                          no drag (still for reduced motion)
 *   data-carousel="press"  press strip: centred, looping, click a logo to activate it,
 *                          auto-advances every 5s (paused on hover; off for reduced motion)
 *
 * Slide widths come from Tailwind classes on each slide (slidesPerView: 'auto').
 * Swiper is only downloaded on pages that contain a carousel.
 */
/**
 * Lazy images inside a Swiper row are positioned with transforms, so the browser can't tell
 * when a far card is about to come into view: a fast drag/wheel to the end showed empty tiles
 * for a moment. When the carousel gets within a screen of the viewport, load and decode every
 * image in it (the loop copies share the same few URLs, so this is only a handful of files).
 */
function preloadImages(root) {
  const load = () => root.querySelectorAll('img[loading="lazy"]').forEach((img) => {
    img.loading = 'eager';
    img.decode?.().catch(() => {});
  });
  if (!('IntersectionObserver' in window)) return load();
  const io = new IntersectionObserver(([entry]) => {
    if (!entry.isIntersecting) return;
    io.disconnect();
    load();
  }, { rootMargin: '100% 0px' });
  io.observe(root);
}

export async function initCarousels() {
  const roots = document.querySelectorAll('[data-carousel]');
  if (!roots.length) return;

  const [{ default: Swiper }, { Navigation, Keyboard, A11y, Autoplay, Mousewheel }] = await Promise.all([
    import('swiper'),
    import('swiper/modules'),
    import('swiper/css'),
  ]);

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  roots.forEach(async (root) => {
    const el = root.querySelector('.swiper');
    if (!el || el.swiper) return;
    const variant = root.dataset.carousel;

    const base = {
      modules: [Navigation, Keyboard, A11y, Autoplay],
      slidesPerView: 'auto',
      grabCursor: true,
      watchOverflow: true,
      speed: reduceMotion ? 0 : 500,
      keyboard: { enabled: true, onlyInViewport: true },
      a11y: { enabled: true },
    };

    if (variant === 'words') {
      // Rotating words in a banner heading (Organizers; partiful.com/org-profiles): a vertical
      // loop, a new word about every second. Creative effect: the old word slides up and fades,
      // the next comes up from below. Decorative (aria-hidden; the heading has the first word as
      // sr-only text), so no drag, keyboard or A11y module. Reduced motion: the first word stays.
      if (reduceMotion) return;
      const [{ EffectCreative }] = await Promise.all([import('swiper/modules'), import('swiper/css/effect-creative')]);
      new Swiper(el, {
        modules: [Autoplay, EffectCreative],
        direction: 'vertical',
        loop: true,
        allowTouchMove: false,
        speed: 450,
        autoplay: { delay: 600, disableOnInteraction: false },
        effect: 'creative',
        creativeEffect: {
          limitProgress: 1,
          prev: { translate: [0, '-110%', 0], opacity: 0 },
          next: { translate: [0, '110%', 0], opacity: 0 },
        },
      });
      return;
    }

    if (variant === 'logos') {
      // Logo ticker (Organizers, under the banner): moves at a steady speed in an endless loop:
      // autoplay with no delay + a long linear transition (linear timing: app.css). No drag, no
      // keyboard (it's decorative motion; the logos are plain images). Reduced motion: still.
      preloadImages(root);
      new Swiper(el, {
        ...base,
        modules: [Autoplay],
        spaceBetween: 32,
        breakpoints: { 768: { spaceBetween: 48 } },
        loop: true,
        loopAdditionalSlides: 5,
        allowTouchMove: false,
        grabCursor: false,
        keyboard: { enabled: false },
        speed: reduceMotion ? 0 : 5000,
        autoplay: reduceMotion ? false : { delay: 0, disableOnInteraction: false },
      });
      return;
    }

    if (variant === 'press') {
      // Smooth mouse like the testimonials row: drag settles on a logo with a soft ease-out,
      // sideways trackpad / shift+wheel moves one logo; spare loop slides so long drags
      // never run out. No freeMode (it breaks the loop, see build-log 2026-11-18).
      const swiper = new Swiper(el, {
        ...base,
        modules: [...base.modules, Mousewheel],
        spaceBetween: 0,
        centeredSlides: true,
        loop: true,
        loopAdditionalSlides: 4,
        slideToClickedSlide: true,
        speed: reduceMotion ? 0 : 700,
        mousewheel: { enabled: true, forceToAxis: true, thresholdDelta: 10 },
        initialSlide: Math.max(0, [...el.querySelectorAll('.swiper-slide')].findIndex((s) => s.querySelector('.press-slide__quote'))),
        autoplay: reduceMotion ? false : { delay: 5000, pauseOnMouseEnter: true, disableOnInteraction: false },
      });
      preloadImages(root);
      // Logo buttons (mouse, Enter, Space) make their slide active
      // (capture phase: Swiper stops click propagation inside the slider).
      // allowClick is false right after a drag: releasing over a logo is not a click.
      root.addEventListener('click', (event) => {
        if (!swiper.allowClick) return;
        const slide = event.target.closest('.swiper-slide');
        if (slide && !slide.classList.contains('swiper-slide-active') && slide.dataset.swiperSlideIndex !== undefined) {
          swiper.slideToLoop(Number(slide.dataset.swiperSlideIndex));
        }
      }, true);
      return;
    }

    if (variant === 'loop') {
      // Testimonials row (reference: partiful.com "social life runs on…"): endless loop that
      // advances one card about every 4s; arrows never run out. Pauses on hover, after which
      // it carries on; no auto-advance for reduced motion.
      // centeredSlides: the cut-off cards on the left and right are the same width.
      // Smooth mouse: drag follows the pointer and settles on a card with a soft ease-out
      // (CSS on .swiper-wrapper); a sideways trackpad swipe / shift+wheel moves one card
      // (vertical wheel still scrolls the page).
      // No freeMode: its momentum glide can run to the last slide without Swiper's loopFix,
      // leaving the right side empty (seen with mouse scrolling). Slide-by-slide always loops.
      preloadImages(root);
      new Swiper(el, {
        ...base,
        modules: [...base.modules, Mousewheel],
        spaceBetween: 16,
        loop: true,
        // spare slides kept on each side when the loop reshuffles (at drag start / each move):
        // without them a long, fast mouse drag ran past the last slide and showed empty space
        loopAdditionalSlides: 6,
        centeredSlides: true,
        speed: reduceMotion ? 0 : 700,
        mousewheel: { enabled: true, forceToAxis: true, thresholdDelta: 10 },
        autoplay: reduceMotion ? false : { delay: 3500, pauseOnMouseEnter: true, disableOnInteraction: false },
        navigation: {
          prevEl: root.querySelector('[data-carousel-prev]'),
          nextEl: root.querySelector('[data-carousel-next]'),
        },
      });
      return;
    }

    new Swiper(el, {
      ...base,
      spaceBetween: 16,
      navigation: {
        prevEl: root.querySelector('[data-carousel-prev]'),
        nextEl: root.querySelector('[data-carousel-next]'),
        disabledClass: 'opacity-40',
      },
    });
  });
}
