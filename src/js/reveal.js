/**
 * Reveal on scroll (reference site: sections/cards fade up as they enter the viewport).
 *
 * Usage in Twig: add `data-reveal` to an element. Siblings that should stagger get
 * `data-reveal-index="n"` (0, 1, 2 …); the delay is n × 90ms (see app.css).
 * Disabled for prefers-reduced-motion: content is simply shown.
 */
export function initReveal() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (!('IntersectionObserver' in window)) return;

  const items = document.querySelectorAll('[data-reveal]');
  if (!items.length) return;

  items.forEach((el) => {
    const i = el.getAttribute('data-reveal-index');
    if (i) el.style.setProperty('--reveal-index', i);
  });

  document.documentElement.classList.add('js-reveal');

  const io = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-revealed');
        io.unobserve(entry.target);
      }
    });
  }, { rootMargin: '0px 0px -10% 0px', threshold: 0.1 });

  items.forEach((el) => io.observe(el));

  // Fallback: when the page jumps (anchor link, End key, fast scroll), items that were
  // skipped over never intersect — reveal everything already above the viewport bottom.
  let ticking = false;
  const sweep = () => {
    ticking = false;
    const limit = window.innerHeight;
    document.querySelectorAll('[data-reveal]:not(.is-revealed)').forEach((el) => {
      if (el.getBoundingClientRect().top < limit) {
        el.classList.add('is-revealed');
        io.unobserve(el);
      }
    });
  };
  window.addEventListener('scroll', () => {
    if (!ticking) { ticking = true; requestAnimationFrame(sweep); }
  }, { passive: true });
}
