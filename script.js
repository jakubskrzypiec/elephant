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

  // Only answered questions go into the e-mail.
  const rows = [
    ['Oferta', get('oferta')],
    ['Rodzaj przestrzeni', get('typ')],
    ['Metraż', metraz && `${metraz} m²`],
  ].filter(([, value]) => value).map(([label, value]) => `${label}: ${value}`);

  const message = get('message');
  const contact = [name, get('email'), get('phone')].filter(Boolean).join('\n');
  const subject = `Zapytanie ze strony${get('oferta') ? ' — ' + get('oferta') : ''}${name ? ' — ' + name : ''}`;
  const body = [rows.join('\n'), message && `Opis:\n${message}`, contact].filter(Boolean).join('\n\n');
  location.href = `mailto:elephant.interiordesignstudio@gmail.com?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
});

// Portfolio remains still until the visitor chooses to browse.
const wrap = document.querySelector('[data-carousel]');
const projectPrev = document.querySelector('[data-project-prev]');
const projectNext = document.querySelector('[data-project-next]');
if (wrap && projectPrev && projectNext) {
  const updateButtons = () => {
    projectPrev.disabled = wrap.scrollLeft < 2;
    projectNext.disabled = wrap.scrollLeft >= wrap.scrollWidth - wrap.clientWidth - 2;
  };
  const browse = direction => wrap.scrollBy({left: direction * wrap.clientWidth * .7, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
  projectPrev.addEventListener('click', () => browse(-1));
  projectNext.addEventListener('click', () => browse(1));
  wrap.addEventListener('scroll', updateButtons, {passive: true});
  let drag = null;
  wrap.addEventListener('pointerdown', e => {
    if (e.pointerType !== 'mouse' || e.button !== 0) return;
    drag = {x:e.clientX, scroll:wrap.scrollLeft};
    wrap.setPointerCapture(e.pointerId);
    e.preventDefault();
  });
  wrap.addEventListener('pointermove', e => { if (drag) wrap.scrollLeft = drag.scroll + drag.x - e.clientX; });
  ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(event => wrap.addEventListener(event, () => drag = null));
  new ResizeObserver(updateButtons).observe(wrap);
  updateButtons();
}

// Process: the range, touch scrolling and mouse dragging share one position.
const processViewport = document.querySelector('[data-process-viewport]');
const processRange = document.querySelector('[data-process-range]');
const processCards = [...document.querySelectorAll('.process-card')];
const processCurrent = document.querySelector('[data-process-current]');
if (processViewport && processRange && processCards.length) {
  const numerals = ['I', 'II', 'III', 'IV'];
  const maxScroll = () => Math.max(0, processViewport.scrollWidth - processViewport.clientWidth);
  const syncProcess = () => {
    const max = maxScroll();
    const progress = max ? processViewport.scrollLeft / max : 0;
    const index = Math.max(0, Math.min(processCards.length - 1, Math.round(progress * (processCards.length - 1))));
    processRange.value = String(progress * 100);
    processRange.disabled = max === 0;
    processRange.setAttribute('aria-valuetext', processCards[index].querySelector('h3').textContent);
    processCards.forEach((card, i) => card.classList.toggle('is-active', i === index));
    processCurrent.textContent = `${numerals[index]} / ${numerals[processCards.length - 1]}`;
  };
  processRange.addEventListener('input', () => {
    processViewport.scrollLeft = Number(processRange.value) / 100 * maxScroll();
    syncProcess();
  });
  processViewport.addEventListener('scroll', syncProcess, { passive: true });
  processViewport.addEventListener('keydown', e => {
    const direction = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
    if (!direction) return;
    e.preventDefault();
    processViewport.scrollBy({ left: direction * maxScroll() / (processCards.length - 1), behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
  });
  let drag = null;
  processViewport.addEventListener('pointerdown', e => {
    if (e.pointerType !== 'mouse' || e.button !== 0) return;
    drag = { x: e.clientX, scroll: processViewport.scrollLeft };
    processViewport.setPointerCapture(e.pointerId);
    processViewport.classList.add('is-dragging');
    e.preventDefault();
  });
  processViewport.addEventListener('pointermove', e => {
    if (drag) processViewport.scrollLeft = drag.scroll + drag.x - e.clientX;
  });
  const stopDrag = () => { drag = null; processViewport.classList.remove('is-dragging'); };
  processViewport.addEventListener('pointerup', stopDrag);
  processViewport.addEventListener('pointercancel', stopDrag);
  processViewport.addEventListener('lostpointercapture', stopDrag);
  new ResizeObserver(syncProcess).observe(processViewport);
  syncProcess();
}

// Material light moves only while visible, and respects reduced-motion settings.
const motionPreference = matchMedia('(prefers-reduced-motion: reduce)');
const materialScenes = [...document.querySelectorAll('[data-material-motion]')];
const visibleScenes = new Set();
const updateMaterialMotion = () => materialScenes.forEach(scene => {
  const animate = visibleScenes.has(scene) && !document.hidden && !motionPreference.matches;
  scene.classList.toggle('is-visible', animate);
  const water = scene.querySelector('[data-water-surface]');
  if (water) {
    if (animate) water.unpauseAnimations();
    else water.pauseAnimations();
  }
});
materialScenes.forEach(scene => scene.querySelector('[data-water-surface]')?.pauseAnimations());
const materialObserver = new IntersectionObserver(entries => {
  entries.forEach(entry => {
    if (entry.isIntersecting) visibleScenes.add(entry.target);
    else visibleScenes.delete(entry.target);
  });
  updateMaterialMotion();
}, {threshold: 0.05});
materialScenes.forEach(scene => materialObserver.observe(scene));
motionPreference.addEventListener('change', updateMaterialMotion);
document.addEventListener('visibilitychange', updateMaterialMotion);
