const header = document.querySelector('[data-header]');
const toggle = document.querySelector('[data-menu-toggle]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

window.addEventListener('scroll', () => header?.classList.toggle('is-scrolled', window.scrollY > 24), { passive: true });

toggle?.addEventListener('click', () => {
  const open = toggle.getAttribute('aria-expanded') === 'true';
  toggle.setAttribute('aria-expanded', String(!open));
  mobileMenu?.classList.toggle('is-open', !open);
  mobileMenu?.setAttribute('aria-hidden', String(open));
});
mobileMenu?.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
  toggle?.setAttribute('aria-expanded','false');
  mobileMenu.classList.remove('is-open');
  mobileMenu.setAttribute('aria-hidden','true');
}));

const revealObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('is-visible');
      revealObserver.unobserve(entry.target);
    }
  });
}, { threshold: .12, rootMargin: '0px 0px -30px 0px' });
document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

const wrap = document.querySelector('[data-carousel]');
const track = document.querySelector('[data-track]');
if (wrap && track) {
  // duplicate once to keep the strip visually continuous
  track.innerHTML += track.innerHTML;
  let x = 0;
  let paused = false;
  let dragging = false;
  let lastX = 0;
  let velocity = 0;

  const halfWidth = () => track.scrollWidth / 2;
  const loop = () => {
    if (!paused && !dragging) x -= 0.28;
    if (!dragging && Math.abs(velocity) > .03) {
      x += velocity;
      velocity *= .94;
    }
    const half = halfWidth();
    if (x <= -half) x += half;
    if (x > 0) x -= half;
    track.style.transform = `translate3d(${x}px,0,0)`;
    requestAnimationFrame(loop);
  };
  requestAnimationFrame(loop);

  wrap.addEventListener('mouseenter', () => paused = true);
  wrap.addEventListener('mouseleave', () => { paused = false; dragging = false; });
  wrap.addEventListener('pointerdown', e => {
    dragging = true; paused = true; lastX = e.clientX; velocity = 0; wrap.setPointerCapture?.(e.pointerId);
  });
  wrap.addEventListener('pointermove', e => {
    if (!dragging) return;
    const dx = e.clientX - lastX;
    x += dx; velocity = dx; lastX = e.clientX;
  });
  const endDrag = () => { dragging = false; paused = false; };
  wrap.addEventListener('pointerup', endDrag);
  wrap.addEventListener('pointercancel', endDrag);
}

// Hero animation intentionally disabled for this revision.
// The final newspaper-page motion will be reintroduced only after approving realistic source frames.
