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
 *   data-carousel="press"  press strip: centred, looping, click a logo to activate it,
 *                          auto-advances every 5s (paused on hover; off for reduced motion)
 *
 * Slide widths come from Tailwind classes on each slide (slidesPerView: 'auto').
 * Swiper is only downloaded on pages that contain a carousel.
 */
export async function initCarousels() {
  const roots = document.querySelectorAll('[data-carousel]');
  if (!roots.length) return;

  const [{ default: Swiper }, { Navigation, Keyboard, A11y, Autoplay }] = await Promise.all([
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
