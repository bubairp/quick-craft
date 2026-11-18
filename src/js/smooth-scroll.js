/**
 * Smooth scrolling with Lenis (same feel as the reference site: lerp 0.1, smooth wheel).
 * Skipped entirely for users who prefer reduced motion — native scrolling is used instead.
 * Add data-lenis-prevent to any element that must keep native scrolling (e.g. a scrollable panel).
 */
import Lenis from 'lenis';
import 'lenis/dist/lenis.css';

export function initSmoothScroll() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    return null;
  }

  const lenis = new Lenis({
    autoRaf: true,
    lerp: 0.1,
    smoothWheel: true,
    anchors: true,
  });

  window.lenis = lenis;
  return lenis;
}
