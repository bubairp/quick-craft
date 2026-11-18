/**
 * Swiper carousels. Markup contract:
 *
 *   <div data-carousel[="press"]>
 *     <div class="swiper"><ul class="swiper-wrapper"><li class="swiper-slide w-72!">…</li></ul></div>
 *     <button data-carousel-prev>…</button> <button data-carousel-next>…</button>   (optional)
 *   </div>
 *
 * Variants:
 *   data-carousel          testimonials row (templates/_blocks/testimonials.twig, carousel layout)
 *   data-carousel="gallery" imageGallery "slider" layout: diagonal fan, loop, free drag, continuous glide
 *   data-carousel="press"  press strip: centred, looping, click a logo to activate it,
 *                          auto-advances every 5s (paused on hover; off for reduced motion)
 *
 * Slide widths come from Tailwind classes on each slide (slidesPerView: 'auto').
 * Swiper is only downloaded on pages that contain a carousel.
 */
/**
 * Swiper version of the diagonal fan, same look and motion as gallery-wave.js / the reference
 * (partiful.com "A million reasons to hang"):
 *  - Swiper moves a straight row; CSS tilts the whole .swiper to the fan's slope (md+) and
 *    turns each card back upright with the same rotateY (see .gallery-slider in app.css).
 *  - Cards drift slowly down-left and move faster while the page scrolls (driven per frame,
 *    not by Swiper autoplay, because autoplay can't speed up mid-transition).
 *  - Drag / swipe with momentum (Swiper freeMode); the drift pauses while touched or gliding.
 *  - Endless: when the row nears its end, Swiper's own loopFix moves the first slides to the
 *    end (the same call Swiper makes during a drag), so the movement never jumps.
 */
const FAN_ANGLE = Math.atan(0.3);                  // slope of the fan line (gallery-wave SLOPE)
const DRIFT = 28 / Math.cos(FAN_ANGLE);            // px/s along the tilted row (28 px/s on screen)
const SCROLL_FACTOR = 1.2 / Math.cos(FAN_ANGLE);   // extra px per scrolled px

function initGallerySwiper(Swiper, modules, el, reduceMotion) {
  const swiper = new Swiper(el, {
    modules,
    slidesPerView: 'auto',
    loop: true,
    loopAdditionalSlides: 4,
    grabCursor: true,
    spaceBetween: 12,
    // md+: cards overlap like the fan (step ≈ 100px along the line, card 256px)
    breakpoints: { 768: { spaceBetween: -156 } },
    freeMode: { enabled: true, momentum: true, momentumRatio: 0.6 },
    keyboard: { enabled: true, onlyInViewport: true },
    a11y: { enabled: true },
  });

  // Stacking like the fan: cards further left sit on top (DOM order = left → right)
  const stack = () => swiper.slides.forEach((s, i) => { s.style.zIndex = String(swiper.slides.length - i); });
  swiper.on('loopFix', stack);
  stack();

  if (reduceMotion) return;

  // Move the row by `dx` px to the left, looping like a drag does
  const advance = (dx) => {
    const last = swiper.slidesSizesGrid[swiper.slidesSizesGrid.length - 1] ?? 0;
    if (swiper.getTranslate() - dx < swiper.maxTranslate() + last + swiper.params.spaceBetween) {
      swiper.loopFix({
        direction: 'next',
        setTranslate: true,
        activeSlideIndex: swiper.slides.length - swiper.slidesPerViewDynamic(),
      });
    }
    swiper.setTranslate(swiper.getTranslate() - dx);
    swiper.updateActiveIndex();
    swiper.updateSlidesClasses();
  };

  let visible = false;
  let running = false;
  let lastT = 0;
  let lastY = window.scrollY;
  const tick = (t) => {
    const dt = Math.min(0.05, (t - lastT) / 1000);
    lastT = t;
    const y = window.scrollY;
    const scrolled = Math.abs(y - lastY);
    lastY = y;
    // let Swiper own the row while it is being dragged or gliding after a release
    if (!swiper.touchEventsData.isTouched && !swiper.animating && !swiper.destroyed) {
      advance(DRIFT * dt + scrolled * SCROLL_FACTOR);
    }
    if (visible) requestAnimationFrame(tick);
    else running = false;
  };
  new IntersectionObserver(([entry]) => {
    visible = entry.isIntersecting;
    if (visible && !running) {
      running = true;
      lastT = performance.now();
      lastY = window.scrollY;
      requestAnimationFrame(tick);
    }
  }).observe(el);
}

export async function initCarousels() {
  const roots = document.querySelectorAll('[data-carousel]');
  if (!roots.length) return;

  const [{ default: Swiper }, { Navigation, Keyboard, A11y, Autoplay, FreeMode }] = await Promise.all([
    import('swiper'),
    import('swiper/modules'),
    import('swiper/css'),
  ]);

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  roots.forEach((root) => {
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

    if (variant === 'gallery') {
      initGallerySwiper(Swiper, [FreeMode, Keyboard, A11y], el, reduceMotion);
      return;
    }

    if (variant === 'press') {
      const swiper = new Swiper(el, {
        ...base,
        spaceBetween: 0,
        centeredSlides: true,
        loop: true,
        slideToClickedSlide: true,
        initialSlide: Math.max(0, [...el.querySelectorAll('.swiper-slide')].findIndex((s) => s.querySelector('.press-slide__quote'))),
        autoplay: reduceMotion ? false : { delay: 5000, pauseOnMouseEnter: true, disableOnInteraction: false },
      });
      // Logo buttons (mouse, Enter, Space) make their slide active
      // (capture phase: Swiper stops click propagation inside the slider)
      root.addEventListener('click', (event) => {
        const slide = event.target.closest('.swiper-slide');
        if (slide && !slide.classList.contains('swiper-slide-active') && slide.dataset.swiperSlideIndex !== undefined) {
          swiper.slideToLoop(Number(slide.dataset.swiperSlideIndex));
        }
      }, true);
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
