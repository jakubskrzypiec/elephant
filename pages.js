// Full-screen project index; native modal handles focus and Escape.
const projectListToggle = document.querySelector('[data-project-list-toggle]');
if (projectListToggle) {
  const listDialog = document.querySelector('#lista-projektow-overlay');
  let previousOverflow = '';
  projectListToggle.hidden = false;
  projectListToggle.addEventListener('click', () => {
    previousOverflow = document.body.style.overflow;
    listDialog.showModal();
    document.body.style.overflow = 'hidden';
    projectListToggle.setAttribute('aria-expanded', 'true');
  });
  listDialog.querySelector('[data-project-list-close]').addEventListener('click', () => listDialog.close());
  listDialog.addEventListener('close', () => {
    document.body.style.overflow = previousOverflow;
    projectListToggle.setAttribute('aria-expanded', 'false');
    projectListToggle.focus({preventScroll:true});
  });
}
const dialog = document.querySelector('.photo-dialog');
if (dialog) {
  const photos = [...document.querySelectorAll('[data-photo]')];
  const image = dialog.querySelector('img');
  const caption = dialog.querySelector('p');
  const frame = document.createElement('figure');
  image.before(frame);
  frame.append(image, caption);
  let current = 0;
  const render = index => {
    current = (index + photos.length) % photos.length;
    const photo = photos[current];
    image.src = photo.dataset.photo;
    image.alt = photo.dataset.caption;
    caption.textContent = `${current + 1} / ${photos.length}`;
  };
  caption.setAttribute('aria-live', 'polite');
  if (photos.length > 1) {
    for (const [step, label] of [[-1, 'Poprzednie zdjęcie'], [1, 'Następne zdjęcie']]) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = step === -1 ? 'photo-step photo-prev' : 'photo-step photo-next';
      button.setAttribute('aria-label', label);
      button.innerHTML = '<svg viewBox="0 0 30 16" aria-hidden="true" focusable="false"><path d="M3 8h24m-5-4 5 4-5 4"/></svg>';
      button.addEventListener('click', () => render(current + step));
      dialog.append(button);
    }
  }
  photos.forEach((button, index) => button.addEventListener('click', () => {render(index); dialog.showModal();}));
  dialog.querySelector('.photo-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', e => {if (e.target === dialog || e.target === frame) dialog.close();});
  dialog.addEventListener('keydown', e => {if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {e.preventDefault(); render(current + (e.key === 'ArrowRight' ? 1 : -1));}});
}
const offerSelect = document.querySelector('[name="oferta"]');
const offerParam = new URLSearchParams(location.search).get('oferta');
if (offerSelect && [...offerSelect.options].some(option => option.value === offerParam)) offerSelect.value = offerParam;
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') {
    toggle.setAttribute('aria-expanded', 'false'); mobileMenu.classList.remove('is-open'); mobileMenu.setAttribute('aria-hidden', 'true'); toggle.focus({preventScroll:true});
  }
});
