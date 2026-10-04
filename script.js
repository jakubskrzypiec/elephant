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

// Process: continuous scrolling with eased wheel motion and drag momentum.
const processViewport = document.querySelector('[data-process-viewport]');
const processRange = document.querySelector('[data-process-range]');
const processCards = [...document.querySelectorAll('.process-card')];
const processCurrent = document.querySelector('[data-process-current]');
if (processViewport && processRange && processCards.length) {
  const numerals = ['I', 'II', 'III', 'IV', 'V'];
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  const maxScroll = () => Math.max(0, processViewport.scrollWidth - processViewport.clientWidth);
  const clamp = value => Math.max(0, Math.min(maxScroll(), value));
  let target = processViewport.scrollLeft, frame = 0, drag = null;
  const stop = () => { cancelAnimationFrame(frame); frame = 0; };
  const syncProcess = () => {
    const max = maxScroll(), progress = max ? processViewport.scrollLeft / max : 0;
    const index = Math.min(processCards.length - 1, Math.max(0, Math.round(progress * (processCards.length - 1))));
    processRange.value = String(progress * (processCards.length - 1));
    processRange.disabled = max === 0;
    processRange.style.setProperty('--process-progress', `${progress * 100}%`);
    processRange.setAttribute('aria-valuetext', processCards[index].querySelector('h3').textContent);
    processCards.forEach((card, i) => card.classList.toggle('is-active', i === index));
    processCurrent.textContent = `${numerals[index]} / ${numerals[processCards.length - 1]}`;
  };
  let lastFrame = 0;
  const tick = time => {
    const dt = Math.min(40, time - lastFrame || 16.7); lastFrame = time;
    const distance = target - processViewport.scrollLeft;
    if (Math.abs(distance) < 1) { processViewport.scrollLeft = target; frame = 0; syncProcess(); return; }
    processViewport.scrollLeft += distance * (1 - Math.exp(-dt / 95));
    syncProcess(); frame = requestAnimationFrame(tick);
  };
  const glideTo = value => {
    target = clamp(value);
    if (preference.matches) { stop(); processViewport.scrollLeft = target; syncProcess(); return; }
    if (!frame) { lastFrame = performance.now(); frame = requestAnimationFrame(tick); }
  };
  processRange.addEventListener('input', () => {
    stop(); target = Number(processRange.value) / (processCards.length - 1) * maxScroll();
    processViewport.scrollLeft = target; syncProcess();
  });
  processViewport.addEventListener('wheel', e => {
    if (e.ctrlKey || drag) return;
    const delta = (Math.abs(e.deltaX) > Math.abs(e.deltaY) ? e.deltaX : e.deltaY) * (e.deltaMode === 1 ? 18 : e.deltaMode === 2 ? processViewport.clientWidth : 1);
    const next = clamp((frame ? target : processViewport.scrollLeft) + delta);
    if (Math.abs(next - (frame ? target : processViewport.scrollLeft)) < 1) return;
    e.preventDefault(); glideTo(next);
  }, {passive:false});
  processViewport.addEventListener('scroll', () => {
    if (!frame && !drag) target = processViewport.scrollLeft;
    syncProcess();
  }, {passive:true});
  processViewport.addEventListener('keydown', e => {
    const direction = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
    if (!direction && e.key !== 'Home' && e.key !== 'End') return;
    e.preventDefault();
    glideTo(e.key === 'Home' ? 0 : e.key === 'End' ? maxScroll() : (frame ? target : processViewport.scrollLeft) + direction * maxScroll() / (processCards.length - 1));
  });
  const cursor = document.createElement('span');
  cursor.className = 'process-drag-cursor'; cursor.setAttribute('aria-hidden', 'true');
  cursor.innerHTML = '<span>↔</span><small>Przeciągnij</small>';
  document.body.append(cursor);
  processViewport.addEventListener('pointerdown', e => {
    if (e.pointerType !== 'mouse' || e.button !== 0) return;
    stop(); drag = {x:e.clientX, scroll:processViewport.scrollLeft, last:e.clientX, time:performance.now(), velocity:0};
    processViewport.setPointerCapture(e.pointerId);
    processViewport.classList.add('is-dragging'); cursor.classList.add('is-dragging');
    e.preventDefault();
  });
  processViewport.addEventListener('pointermove', e => {
    if (e.pointerType === 'mouse') {
      cursor.style.setProperty('--cursor-x', `${e.clientX}px`); cursor.style.setProperty('--cursor-y', `${e.clientY}px`);
      cursor.classList.add('is-visible');
    }
    if (drag) {
      const now = performance.now();
      drag.velocity = (drag.last - e.clientX) / Math.max(8, now - drag.time);
      drag.last = e.clientX; drag.time = now;
      processViewport.scrollLeft = clamp(drag.scroll + drag.x - e.clientX);
      target = processViewport.scrollLeft; syncProcess();
    }
  });
  processViewport.addEventListener('pointerleave', () => cursor.classList.remove('is-visible'));
  window.addEventListener('scroll', () => cursor.classList.remove('is-visible'), {passive:true});
  const stopDrag = e => {
    if (!drag) return;
    const momentum = e.type === 'pointerup' && performance.now() - drag.time < 100 ? drag.velocity * 150 : 0;
    drag = null; processViewport.classList.remove('is-dragging'); cursor.classList.remove('is-dragging');
    glideTo(processViewport.scrollLeft + momentum);
  };
  processViewport.addEventListener('pointerup', stopDrag);
  processViewport.addEventListener('pointercancel', stopDrag);
  processViewport.addEventListener('lostpointercapture', stopDrag);
  preference.addEventListener('change', () => { stop(); target = processViewport.scrollLeft; });
  new ResizeObserver(() => { stop(); target = clamp(processViewport.scrollLeft); syncProcess(); }).observe(processViewport);
  syncProcess();
}

// Reveal once, float only in view, and let the illustrations follow the pointer.
const processScene = document.querySelector('.process');
if (processScene && processViewport) {
  const reducedProcessMotion = matchMedia('(prefers-reduced-motion: reduce)');
  processScene.classList.add('process-motion-ready');
  const processObserver = new IntersectionObserver(entries => {
    const visible = entries.some(entry => entry.isIntersecting);
    processScene.classList.toggle('is-process-visible', visible && !document.hidden);
    if (visible) processScene.classList.add('is-process-revealed');
  }, {threshold:0.08});
  processObserver.observe(processViewport);
  document.addEventListener('visibilitychange', () => {
    const rect = processViewport.getBoundingClientRect();
    processScene.classList.toggle('is-process-visible', !document.hidden && rect.bottom > 0 && rect.top < innerHeight);
  });
  processCards.forEach(card => {
    const reset = () => {
      card.style.removeProperty('--process-rx');
      card.style.removeProperty('--process-ry');
    };
    card.addEventListener('pointermove', e => {
      if (e.pointerType !== 'mouse' || reducedProcessMotion.matches || processViewport.classList.contains('is-dragging')) return;
      const rect = card.getBoundingClientRect();
      card.style.setProperty('--process-rx', `${(0.5 - (e.clientY - rect.top) / rect.height) * 5}deg`);
      card.style.setProperty('--process-ry', `${((e.clientX - rect.left) / rect.width - 0.5) * 7}deg`);
    });
    card.addEventListener('pointerleave', reset);
    card.addEventListener('pointerdown', reset);
    reducedProcessMotion.addEventListener('change', reset);
  });
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
