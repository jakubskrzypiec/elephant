const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// HERO — deliberately slow: 1.1 s pause after load, gentle page turn, then ~5 s of stillness.
const frames = [...document.querySelectorAll('.hero-frame')];
if (frames.length && !reducedMotion) {
  let current = 0;
  const show = (index) => {
    frames.forEach((frame, i) => frame.classList.toggle('is-visible', i === index));
    current = index;
  };

  const sleep = (ms) => new Promise(resolve => setTimeout(resolve, ms));
  const moveTo = async (target) => {
    const direction = target > current ? 1 : -1;
    while (current !== target) {
      show(current + direction);
      await sleep(520);
    }
  };

  const loop = async () => {
    await sleep(1100);
    while (true) {
      await moveTo(frames.length - 1);
      await sleep(5000);
      await moveTo(0);
      await sleep(5000);
    }
  };
  loop();
}

// Mobile menu.
const menuBtn = document.querySelector('[data-menu-toggle]');
const mobileMenu = document.querySelector('[data-mobile-menu]');
if (menuBtn && mobileMenu) {
  const closeMenu = () => {
    mobileMenu.classList.remove('is-open');
    mobileMenu.setAttribute('aria-hidden', 'true');
    menuBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  };
  menuBtn.addEventListener('click', () => {
    const open = !mobileMenu.classList.contains('is-open');
    mobileMenu.classList.toggle('is-open', open);
    mobileMenu.setAttribute('aria-hidden', String(!open));
    menuBtn.setAttribute('aria-expanded', String(open));
    document.body.style.overflow = open ? 'hidden' : '';
  });
  mobileMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));
}

// Offer accordion.
document.querySelectorAll('.offer-item').forEach(item => {
  const trigger = item.querySelector('.offer-trigger');
  const icon = item.querySelector('.offer-plus');
  trigger?.addEventListener('click', () => {
    const willOpen = !item.classList.contains('is-open');
    document.querySelectorAll('.offer-item').forEach(other => {
      other.classList.remove('is-open');
      other.querySelector('.offer-trigger')?.setAttribute('aria-expanded', 'false');
      const otherIcon = other.querySelector('.offer-plus');
      if (otherIcon) otherIcon.textContent = '+';
    });
    if (willOpen) {
      item.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
      if (icon) icon.textContent = '−';
    }
  });
});

// Projects — endless, very slow auto-scroll + mouse/touch drag.
const carousel = document.querySelector('[data-carousel]');
const track = document.querySelector('[data-track]');
if (carousel && track) {
  [...track.children].forEach(card => track.appendChild(card.cloneNode(true)));
  let isDragging = false;
  let startX = 0;
  let startScroll = 0;
  let paused = false;

  const normalize = () => {
    const half = carousel.scrollWidth / 2;
    if (carousel.scrollLeft >= half) carousel.scrollLeft -= half;
    if (carousel.scrollLeft < 0) carousel.scrollLeft += half;
  };

  const animate = () => {
    if (!reducedMotion && !isDragging && !paused) {
      carousel.scrollLeft += 0.32;
      normalize();
    }
    requestAnimationFrame(animate);
  };
  requestAnimationFrame(animate);

  carousel.addEventListener('pointerdown', e => {
    isDragging = true;
    carousel.classList.add('is-dragging');
    carousel.setPointerCapture(e.pointerId);
    startX = e.clientX;
    startScroll = carousel.scrollLeft;
  });
  carousel.addEventListener('pointermove', e => {
    if (!isDragging) return;
    carousel.scrollLeft = startScroll - (e.clientX - startX);
    normalize();
  });
  const stopDrag = () => {
    isDragging = false;
    carousel.classList.remove('is-dragging');
  };
  carousel.addEventListener('pointerup', stopDrag);
  carousel.addEventListener('pointercancel', stopDrag);
  carousel.addEventListener('mouseenter', () => paused = true);
  carousel.addEventListener('mouseleave', () => { paused = false; stopDrag(); });
}

// Quiet scroll reveals.
if (!reducedMotion) {
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-in');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12, rootMargin: '0px 0px -4% 0px' });
  document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
} else {
  document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-in'));
}
