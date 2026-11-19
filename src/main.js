import './css/app.css';
import Alpine from 'alpinejs';
import { initSmoothScroll } from './js/smooth-scroll.js';
import { initCarousels } from './js/carousels.js';
import { initReveal } from './js/reveal.js';
import { initScrollSliders } from './js/scroll-slider.js';
import { initGalleryWave } from './js/gallery-wave.js';
import { initHeroChat } from './js/hero-chat.js';
import { initScrollSwipers } from './js/scroll-swiper.js';

/**
 * Header that hides while scrolling down and slides back in when scrolling up
 * (behaviour of the reference site). Used by templates/_partials/header.twig.
 */
Alpine.data('siteHeader', () => ({
  open: false,      // mobile menu
  menu: null,       // index of the open desktop dropdown (mega menu), or null
  hidden: false,    // auto-hide on scroll down
  lastY: 0,
  closeTimer: 0,
  init() {
    this.lastY = window.scrollY;
    window.addEventListener('scroll', () => {
      const y = window.scrollY;
      if (!this.open && this.menu === null && Math.abs(y - this.lastY) > 4) {
        this.hidden = y > this.lastY && y > 160;
        this.lastY = y;
      }
    }, { passive: true });
  },
  toggle() {
    this.open = !this.open;
    this.hidden = false;
  },
  openMenu(i) {
    clearTimeout(this.closeTimer);
    this.menu = i;
    this.hidden = false;
  },
  toggleMenu(i) {
    clearTimeout(this.closeTimer);
    this.menu = this.menu === i ? null : i;
  },
  // small delay so moving the pointer from the bar into the panel doesn't flicker
  closeMenu() {
    clearTimeout(this.closeTimer);
    this.closeTimer = setTimeout(() => { this.menu = null; }, 150);
  },
}));

window.Alpine = Alpine;
Alpine.start();

initSmoothScroll();
initCarousels();
initReveal();
initScrollSliders();
initGalleryWave();
initHeroChat();
initScrollSwipers();   // DEMO (featureRows "stickySwiper")
