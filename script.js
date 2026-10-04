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

// A seamless portfolio loop; hover, focus or the pause control stops movement.
const wrap = document.querySelector('[data-carousel]');
const track = document.querySelector('[data-track]');
const pauseControl = document.querySelector('[data-carousel-pause]');
if (wrap && track) {
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  const originals = [...track.children];
  originals.forEach(card => {
    const clone = card.cloneNode(true);
    clone.dataset.clone = '';
    clone.setAttribute('aria-hidden', 'true');
    track.append(clone);
  });
  let hovered = false, focused = false, paused = false, visible = false, touching = false, drag = null;
  let lastTime = 0, position = 0;
  const cycleWidth = () => track.querySelector('[data-clone]').offsetLeft - originals[0].offsetLeft;
  const syncControl = () => {
    pauseControl.hidden = preference.matches;
    pauseControl.setAttribute('aria-pressed', String(paused));
    pauseControl.textContent = paused ? 'Wznów ruch' : 'Zatrzymaj ruch';
  };
  const normalize = () => {
    if (preference.matches) return;
    const cycle = cycleWidth();
    if (cycle > 0 && wrap.scrollLeft >= cycle) wrap.scrollLeft -= cycle;
  };
  const loop = time => {
    const elapsed = Math.min(64, time - (lastTime || time));
    lastTime = time;
    if (visible && !document.hidden && !preference.matches && !hovered && !focused && !paused && !touching && !drag) {
      position += elapsed * .028;
      const cycle = cycleWidth();
      if (cycle > 0) position %= cycle;
      wrap.scrollLeft = position;
    } else position = wrap.scrollLeft;
    requestAnimationFrame(loop);
  };
  wrap.addEventListener('mouseenter', () => hovered = true);
  wrap.addEventListener('mouseleave', () => hovered = false);
  wrap.addEventListener('focusin', () => focused = true);
  wrap.addEventListener('focusout', () => focused = wrap.contains(document.activeElement));
  wrap.addEventListener('scroll', normalize, {passive:true});
  let touchEndTimer;
  wrap.addEventListener('touchstart', () => {clearTimeout(touchEndTimer);touching = true;}, {passive:true});
  wrap.addEventListener('touchend', () => {touchEndTimer = setTimeout(() => touching = false, 1000);}, {passive:true});
  wrap.addEventListener('touchcancel', () => touching = false, {passive:true});
  wrap.addEventListener('pointerdown', e => {
    if (e.pointerType !== 'mouse' || e.button !== 0) return;
    drag = {x:e.clientX, scroll:wrap.scrollLeft};
    wrap.setPointerCapture(e.pointerId);
    wrap.classList.add('is-dragging');
    e.preventDefault();
  });
  wrap.addEventListener('pointermove', e => {
    if (!drag) return;
    const cycle = cycleWidth();
    const next = drag.scroll + drag.x - e.clientX;
    wrap.scrollLeft = preference.matches ? next : ((next % cycle) + cycle) % cycle;
  });
  ['pointerup','pointercancel','lostpointercapture'].forEach(event => wrap.addEventListener(event, () => {
    drag = null;
    wrap.classList.remove('is-dragging');
  }));
  pauseControl.addEventListener('click', () => {paused = !paused;syncControl();});
  preference.addEventListener('change', () => {wrap.scrollLeft = position = 0;syncControl();});
  new IntersectionObserver(entries => {visible = entries[0].isIntersecting;}, {threshold:.05}).observe(wrap);
  syncControl();
  requestAnimationFrame(loop);
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
    processRange.value = String(index);
    document.querySelectorAll('[data-process-step]').forEach((button, i) => button.setAttribute('aria-pressed', String(i === index)));
    processRange.disabled = max === 0;
    processRange.setAttribute('aria-valuetext', processCards[index].querySelector('h3').textContent);
    processCards.forEach((card, i) => card.classList.toggle('is-active', i === index));
    processCurrent.textContent = `${numerals[index]} / ${numerals[processCards.length - 1]}`;
  };
  processRange.addEventListener('input', () => {
    processViewport.scrollLeft = Number(processRange.value) / (processCards.length - 1) * maxScroll();
    syncProcess();
  });
  document.querySelectorAll('[data-process-step]').forEach(button => button.addEventListener('click', () => {
    processViewport.scrollTo({left:Number(button.dataset.processStep) / (processCards.length - 1) * maxScroll(), behavior:matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
  }));
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
