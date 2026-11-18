import './css/app.css';
import Alpine from 'alpinejs';
import { initSmoothScroll } from './js/smooth-scroll.js';
import { initCarousels } from './js/carousels.js';
import { initReveal } from './js/reveal.js';
import { initScrollSliders } from './js/scroll-slider.js';
import { initGalleryWave } from './js/gallery-wave.js';

/**
 * Header that hides while scrolling down and slides back in when scrolling up
 * (behaviour of the reference site). Used by templates/_partials/header.twig.
 */
Alpine.data('siteHeader', () => ({
  open: false,
  hidden: false,
  lastY: 0,
  init() {
    this.lastY = window.scrollY;
    window.addEventListener('scroll', () => {
      const y = window.scrollY;
      if (!this.open && Math.abs(y - this.lastY) > 4) {
        this.hidden = y > this.lastY && y > 160;
        this.lastY = y;
      }
    }, { passive: true });
  },
  toggle() {
    this.open = !this.open;
    this.hidden = false;
  },
}));

window.Alpine = Alpine;
Alpine.start();

initSmoothScroll();
initCarousels();
initReveal();
initScrollSliders();
initGalleryWave();
