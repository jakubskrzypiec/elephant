// Portfolio categories progressively enhance the complete collection.
const filters = [...document.querySelectorAll('[data-filter]')];
const portfolioItems = [...document.querySelectorAll('[data-category]')];
filters.forEach(button => button.addEventListener('click', () => {
  const category = button.dataset.filter;
  const grid=document.querySelector(".portfolio-grid");
  if(grid)grid.dataset.filtered=String(category!=="Wszystkie");
  filters.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
  let visible = 0;
  portfolioItems.forEach(item => { item.hidden = category !== 'Wszystkie' && item.dataset.category !== category; if (!item.hidden) visible++; });
  const count = document.querySelector('[data-filter-count]');
  if (count) count.textContent = `${visible} ${visible === 1 ? 'projekt' : visible < 5 ? 'projekty' : 'projektów'}`;
}));
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
