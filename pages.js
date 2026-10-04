// Switch the portfolio between photographic collection and city index.
const projectListToggle = document.querySelector('[data-project-list-toggle]');
if (projectListToggle) {
  const cityList = document.querySelector('#lista-projektow');
  const gallery = document.querySelector('#realizacje');
  projectListToggle.hidden = false;
  projectListToggle.addEventListener('click', () => {
    const showList = projectListToggle.getAttribute('aria-expanded') !== 'true';
    projectListToggle.setAttribute('aria-expanded', String(showList));
    cityList.hidden = !showList;
    gallery.hidden = showList;
    projectListToggle.innerHTML = showList ? 'Galeria projektów <span aria-hidden="true">↗</span>' : 'Lista projektów <span aria-hidden="true">↗</span>';
  });
}
const dialog = document.querySelector('.photo-dialog');
if (dialog) {
  const photos = [...document.querySelectorAll('[data-photo]')];
  let current = 0;
  const render = index => { current = (index + photos.length) % photos.length; const photo = photos[current]; dialog.querySelector('img').src = photo.dataset.photo; dialog.querySelector('img').alt = photo.dataset.caption; dialog.querySelector('p').textContent = photo.dataset.caption; };
  photos.forEach((button, index) => button.addEventListener('click', () => {render(index); dialog.showModal();}));
  dialog.querySelector('.photo-close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('click', e => {if (e.target === dialog) {const rect=dialog.getBoundingClientRect(); if (e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom) dialog.close();}});
  dialog.addEventListener('keydown', e => {if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {e.preventDefault(); render(current + (e.key === 'ArrowRight' ? 1 : -1));}});
}
const offerSelect = document.querySelector('[name="oferta"]');
const offerParam = new URLSearchParams(location.search).get('oferta');
if (offerSelect && [...offerSelect.options].some(option => option.value === offerParam)) offerSelect.value = offerParam;
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') {
    toggle.setAttribute('aria-expanded', 'false'); mobileMenu.classList.remove('is-open'); mobileMenu.setAttribute('aria-hidden', 'true'); toggle.focus();
  }
});
