/**
 * Rotating words in a banner heading (Organizers; behaviour of partiful.com/org-profiles).
 *
 * Markup (templates/_partials/banner.twig):
 *   <span class="rotating-words" data-rotating-words>
 *     <span class="rotating-words__word">…</span> × n
 *   </span>
 * Every second the active word slides up and out (`is-leaving`) and the next one slides up from
 * below (`is-active`); the styles are `.rotating-words` in app.css. The first word shows without
 * JS. Reduced motion: no rotation. Pauses while the tab is hidden.
 */
const INTERVAL = 1000;

export function initRotatingWords() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  document.querySelectorAll('[data-rotating-words]').forEach((el) => {
    const words = [...el.querySelectorAll('.rotating-words__word')];
    if (words.length < 2) return;

    let i = 0;
    words[0].classList.add('is-active');
    el.setAttribute('data-ready', '');

    setInterval(() => {
      if (document.hidden) return;
      const prev = words[i];
      i = (i + 1) % words.length;
      words.forEach((w) => w.classList.remove('is-leaving'));
      prev.classList.replace('is-active', 'is-leaving');
      words[i].classList.add('is-active');
    }, INTERVAL);
  });
}
