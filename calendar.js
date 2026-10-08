/* Preference calendar prototype. It does not expose availability or create bookings. */
document.querySelectorAll('[data-consultation-calendar]').forEach(calendar => {
  const form = calendar.closest('.contact-main')?.querySelector('[data-contact-form]');
  if (!form) return;
  const today = new Date(); today.setHours(0, 0, 0, 0);
  const firstMonth = new Date(today.getFullYear(), today.getMonth(), 1);
  let monthOffset = 0, selected = null;
  const grid = calendar.querySelector('[data-calendar-days]');
  const heading = calendar.querySelector('[data-calendar-month]');
  const previous = calendar.querySelector('[data-calendar-prev]');
  const next = calendar.querySelector('[data-calendar-next]');
  const selection = calendar.querySelector('[data-calendar-selection]');
  const time = calendar.querySelector('[data-calendar-time]');
  const apply = calendar.querySelector('[data-calendar-apply]');
  const status = calendar.querySelector('[data-calendar-status]');
  const format = new Intl.DateTimeFormat('pl-PL', { day: 'numeric', month: 'long', year: 'numeric' });
  const iso = date => `${date.getFullYear()}-${String(date.getMonth()+1).padStart(2,'0')}-${String(date.getDate()).padStart(2,'0')}`;
  const resetDraft = () => {
    form.elements.preferred_date.value = '';
    form.elements.preferred_time.value = '';
    status.textContent = '';
  };
  const render = () => {
    const month = new Date(firstMonth.getFullYear(), firstMonth.getMonth()+monthOffset, 1);
    heading.textContent = new Intl.DateTimeFormat('pl-PL', { month: 'long', year: 'numeric' }).format(month);
    previous.disabled = monthOffset === 0;
    next.disabled = monthOffset === 12;
    grid.replaceChildren();
    const start = (month.getDay()+6)%7;
    for (let i=0; i<start; i++) {
      const blank = document.createElement('span'); blank.setAttribute('aria-hidden','true'); grid.append(blank);
    }
    const days = new Date(month.getFullYear(), month.getMonth()+1, 0).getDate();
    for (let day=1; day<=days; day++) {
      const date = new Date(month.getFullYear(), month.getMonth(), day);
      const button = document.createElement('button'); button.type = 'button'; button.textContent = String(day);
      button.dataset.calendarDate = iso(date);
      button.setAttribute('aria-label', format.format(date));
      button.setAttribute('aria-pressed', String(selected && iso(selected) === iso(date)));
      button.disabled = date < today;
      if (iso(date) === iso(today)) button.classList.add('is-today');
      button.addEventListener('click', () => {
        selected = date;
        resetDraft();
        selection.textContent = format.format(date);
        apply.disabled = false;
        grid.querySelectorAll('button').forEach(item => item.setAttribute('aria-pressed', String(item === button)));
      });
      grid.append(button);
    }
  };
  previous.addEventListener('click', () => { if (monthOffset>0) { monthOffset--; render(); } });
  next.addEventListener('click', () => { if (monthOffset<12) { monthOffset++; render(); } });
  time.addEventListener('change', resetDraft);
  apply.addEventListener('click', () => {
    if (!selected) return;
    form.elements.preferred_date.value = format.format(selected);
    form.elements.preferred_time.value = time.value;
    status.textContent = `Termin ${format.format(selected)}${time.value ? ', '+time.value : ''} został dodany do zapytania. Wyślij formularz powyżej, aby zapytać o dostępność.`;
    calendar.dispatchEvent(new CustomEvent('consultation-preference', { bubbles:true, detail:{ date:iso(selected), time:time.value } }));
  });
  render();
});
