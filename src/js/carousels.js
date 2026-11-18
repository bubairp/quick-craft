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
 *   data-carousel="gallery" imageGallery "slider" layout: coverflow, loop, free drag, continuous glide
 *   data-carousel="press"  press strip: centred, looping, click a logo to activate it,
 *                          auto-advances every 5s (paused on hover; off for reduced motion)
 *
 * Slide widths come from Tailwind classes on each slide (slidesPerView: 'auto').
 * Swiper is only downloaded on pages that contain a carousel.
 */
export async function initCarousels() {
  const roots = document.querySelectorAll('[data-carousel]');
  if (!roots.length) return;

  const [{ default: Swiper }, { Navigation, Keyboard, A11y, Autoplay, EffectCoverflow, FreeMode }] = await Promise.all([
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
      // Swiper alternative to the diagonal fan (gallery-wave.js): 3D coverflow with
      // overlapping cards, endless loop, free drag with momentum, continuous glide.
      const wrapper = el.querySelector('.swiper-wrapper');
      const swiper = new Swiper(el, {
        modules: [EffectCoverflow, FreeMode, Autoplay, Keyboard, A11y],
        slidesPerView: 'auto',
        centeredSlides: true,
        loop: true,
        grabCursor: true,
        // negative gap: cards overlap like the fan
        spaceBetween: -64,
        effect: 'coverflow',
        // modifier is clamped to ±1: cards beyond the neighbours keep the same angle/depth
        // instead of rotating further (unclamped, far cards flip round on wide screens)
        coverflowEffect: {
          rotate: 38, stretch: 0, depth: 160, slideShadows: false,
          modifier: (offset) => Math.max(-1, Math.min(1, offset)),
        },
        freeMode: { enabled: true, momentum: true, momentumRatio: 0.6 },
        // autoplay with delay 0 + a long linear transition = a continuous glide
        speed: reduceMotion ? 0 : 3500,
        autoplay: reduceMotion ? false : { delay: 0, disableOnInteraction: false },
        keyboard: { enabled: true, onlyInViewport: true },
        a11y: { enabled: true },
        on: {
          // linear easing only while gliding; drag release keeps Swiper's own easing
          autoplay: () => wrapper.classList.add('is-gliding'),
          touchStart: () => wrapper.classList.remove('is-gliding'),
        },
      });
      // stacking: cards nearer the centre on top (coverflow's own z-index is flat once clamped).
      // Registered after construction so it runs after the effect's handler.
      const stack = () => swiper.slides.forEach((s) => {
        s.style.zIndex = String(100 - Math.round(Math.abs(s.progress ?? 0) * 10));
      });
      swiper.on('setTranslate', stack);
      stack();
      // pause while off screen
      if (!reduceMotion) {
        new IntersectionObserver(([entry]) => {
          if (entry.isIntersecting) swiper.autoplay.resume(); else swiper.autoplay.pause();
        }).observe(el);
      }
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
