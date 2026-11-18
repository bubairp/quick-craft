/**
 * Hero chat animation (reference: partiful.com hero).
 * Messages appear one at a time at the bottom; when the stack is taller than the window
 * it slides up so the newest message stays in view. After the last message the chat fades,
 * resets and plays again.
 *
 * Markup: templates/_components/hero-chat.twig
 *   [data-hero-chat] > [data-chat-window] > [data-chat-track] > [data-chat-msg]…
 * Reduced motion: everything stays visible, no timers.
 */
const FIRST_DELAY = 600;   // ms before the first message
const INTERVAL = 2600;     // ms between messages (reference ≈ 3s)
const END_PAUSE = 3500;    // ms to hold the full chat before restarting

const HIDDEN = ['opacity-0', 'translate-y-4', 'scale-95'];

function setup(root) {
  const win = root.querySelector('[data-chat-window]');
  const track = root.querySelector('[data-chat-track]');
  const msgs = [...root.querySelectorAll('[data-chat-msg]')];
  if (!win || !track || msgs.length < 2) return;

  let shown = 0;
  let timer = 0;

  const hideAll = () => msgs.forEach((m) => m.classList.add(...HIDDEN));

  const follow = () => {
    // keep the newest visible message at the bottom of the window
    const last = msgs[shown - 1];
    if (!last) { track.style.transform = ''; return; }
    const overflow = last.offsetTop + last.offsetHeight - win.clientHeight + 12;
    track.style.transform = `translate3d(0, ${-Math.max(0, overflow)}px, 0)`;
  };

  const step = () => {
    if (shown < msgs.length) {
      msgs[shown].classList.remove(...HIDDEN);
      shown += 1;
      follow();
      timer = setTimeout(step, shown < msgs.length ? INTERVAL : END_PAUSE);
    } else {
      // restart: fade out, jump back to the top, play again
      root.classList.add('opacity-0');
      timer = setTimeout(() => {
        shown = 0;
        hideAll();
        follow();
        root.classList.remove('opacity-0');
        timer = setTimeout(step, FIRST_DELAY);
      }, 500);
    }
  };

  root.classList.add('transition-opacity', 'duration-500');
  hideAll();

  // play only while on screen
  new IntersectionObserver(([entry]) => {
    clearTimeout(timer);
    if (entry.isIntersecting) timer = setTimeout(step, shown ? INTERVAL : FIRST_DELAY);
  }).observe(root);
}

export function initHeroChat() {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  document.querySelectorAll('[data-hero-chat]').forEach(setup);
}
