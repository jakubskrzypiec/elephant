const toggle = document.querySelector('[data-menu-toggle]');
const mobileMenu = document.querySelector('[data-mobile-menu]');

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

// Hero: frames cross-fade so only the light and shadows move.
// Each new frame fades in on top of the previous one (no dip in brightness),
// and the order runs forth and back (1→9→1) so the loop never jumps.
const heroFrames = [...document.querySelectorAll('[data-hero-frames] img')];
if (heroFrames.length > 1 && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const HOLD = 2000; // ms per frame
  const FADE = 1600; // must match the transition in style.css
  let lastSwitch = 0;
  const idx = [...heroFrames.keys()];
  const order = [...idx, ...idx.slice(1, -1).reverse()];
  let step = 0;
  let z = 1;
  let current = heroFrames[0];

  const next = () => {
    // Wait until the current frame has fully faded in (timers can drift).
    if (document.hidden || performance.now() - lastSwitch < FADE + 150) return;
    const upcoming = heroFrames[order[(step + 1) % order.length]];
    if (!upcoming.complete || !upcoming.naturalWidth) return; // not loaded yet
    step = (step + 1) % order.length;
    const prev = current;
    current = upcoming;
    // Instantly hide everything under the fully visible previous frame.
    heroFrames.forEach(img => {
      if (img !== prev && img.classList.contains('is-on')) {
        img.style.transition = 'none';
        img.classList.remove('is-on');
      }
    });
    void current.offsetWidth;
    heroFrames.forEach(img => { img.style.transition = ''; });
    current.style.zIndex = ++z;
    current.classList.add('is-on');
    lastSwitch = performance.now();
  };

  const start = () => {
    heroFrames.forEach(img => { if (img.dataset.src) img.src = img.dataset.src; });
    setInterval(next, HOLD);
  };
  if (document.readyState === 'complete') start();
  else window.addEventListener('load', start);
}

// Contact form: no backend yet, so open the visitor's mail app with the message filled in.
const contactForm = document.querySelector('[data-contact-form]');
contactForm?.addEventListener('submit', e => {
  e.preventDefault();
  const data = new FormData(contactForm);
  const get = key => (data.get(key) || '').toString().trim();
  const name = get('name');
  const metraz = get('metraz');
  const rooms = data.getAll('pomieszczenia').join(', ');

  // Only answered questions go into the e-mail.
  const rows = [
    ['Oferta', get('oferta')],
    ['Rodzaj przestrzeni', get('typ')],
    ['Metraż', metraz && `${metraz} m²`],
    ['Miasto', get('miasto')],
    ['Stan', get('stan')],
    ['Pomieszczenia', rooms],
    ['Start', get('termin')],
    ['Budżet', get('budzet')],
  ].filter(([, value]) => value).map(([label, value]) => `${label}: ${value}`);

  const message = get('message');
  const contact = [name, get('email'), get('phone')].filter(Boolean).join('\n');
  const subject = `Zapytanie ze strony${get('oferta') ? ' — ' + get('oferta') : ''}${name ? ' — ' + name : ''}`;
  const body = [rows.join('\n'), message && `Opis:\n${message}`, contact].filter(Boolean).join('\n\n');
  location.href = `mailto:elephant.interiordesignstudio@gmail.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
});

const wrap = document.querySelector('[data-carousel]');
const track = document.querySelector('[data-track]');
if (wrap && track) {
  track.innerHTML += track.innerHTML;
  let x = 0;
  let paused = false;
  let dragging = false;
  let lastX = 0;
  let velocity = 0;

  const halfWidth = () => track.scrollWidth / 2;
  const loop = () => {
    if (!paused && !dragging) x -= 0.22;
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
    dragging = true;
    paused = true;
    lastX = e.clientX;
    velocity = 0;
    wrap.setPointerCapture?.(e.pointerId);
  });
  wrap.addEventListener('pointermove', e => {
    if (!dragging) return;
    const dx = e.clientX - lastX;
    x += dx;
    velocity = dx;
    lastX = e.clientX;
  });
  const endDrag = () => { dragging = false; paused = false; };
  wrap.addEventListener('pointerup', endDrag);
  wrap.addEventListener('pointercancel', endDrag);
}
