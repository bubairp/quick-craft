/**
 * Footer wordmark (templates/_partials/footer.twig; styles: .footer-wordmark in app.css).
 *
 * 1. Fit: sets --wordmark-size so the word fills the panel's width exactly, at every screen size
 *    (measured at 100px, then scaled; re-fitted when the panel resizes or the font loads).
 * 2. Animate: when the footer comes into view, the letters rise in from below the edge one by one
 *    (--i = the letter's index → staggered delay). Hover on a letter is pure CSS.
 * Reduced motion: fitted, not animated.
 */
export function initFooterWordmark() {
  const el = document.querySelector('[data-footer-wordmark]');
  if (!el) return;

  const fit = () => {
    const available = el.clientWidth;
    if (!available) return;
    el.style.setProperty('--wordmark-size', '100px');
    // the letters' total width (scrollWidth would include the clipped overflow too)
    const chars = [...el.children];
    const used = chars.length
      ? chars[chars.length - 1].getBoundingClientRect().right - chars[0].getBoundingClientRect().left
      : el.scrollWidth;
    if (used > 0) el.style.setProperty('--wordmark-size', `${Math.floor((100 * available) / used * 100) / 100}px`);
  };

  fit();
  if ('ResizeObserver' in window) new ResizeObserver(fit).observe(el);
  document.fonts?.ready.then(fit);

  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  if (!('IntersectionObserver' in window)) return;

  [...el.children].forEach((char, i) => char.style.setProperty('--i', i));
  el.setAttribute('data-animate', '');
  const io = new IntersectionObserver(([entry]) => {
    if (!entry.isIntersecting) return;
    el.classList.add('is-in');
    io.disconnect();
  }, { threshold: 0.4 });
  io.observe(el);
}
