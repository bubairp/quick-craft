/**
 * Swiper carousels. Markup contract (see templates/_blocks/testimonials.twig):
 *
 *   <div data-carousel>
 *     <div class="swiper"><ul class="swiper-wrapper"><li class="swiper-slide w-72">…</li></ul></div>
 *     <button data-carousel-prev>…</button> <button data-carousel-next>…</button>
 *   </div>
 *
 * Slide widths come from Tailwind classes on each slide (slidesPerView: 'auto').
 * Swiper is only downloaded on pages that contain a carousel.
 */
export async function initCarousels() {
  const roots = document.querySelectorAll('[data-carousel]');
  if (!roots.length) return;

  const [{ default: Swiper }, { Navigation, Keyboard, A11y }] = await Promise.all([
    import('swiper'),
    import('swiper/modules'),
    import('swiper/css'),
  ]);

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  roots.forEach((root) => {
    const el = root.querySelector('.swiper');
    if (!el || el.swiper) return;

    new Swiper(el, {
      modules: [Navigation, Keyboard, A11y],
      slidesPerView: 'auto',
      spaceBetween: 16,
      grabCursor: true,
      watchOverflow: true,
      speed: reduceMotion ? 0 : 500,
      keyboard: { enabled: true, onlyInViewport: true },
      a11y: { enabled: true },
      navigation: {
        prevEl: root.querySelector('[data-carousel-prev]'),
        nextEl: root.querySelector('[data-carousel-next]'),
        disabledClass: 'opacity-40',
      },
    });
  });
}
