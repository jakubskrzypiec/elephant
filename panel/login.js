'use strict';
const form=document.querySelector('#login-form');
form.addEventListener('submit',async event=>{
  event.preventDefault();const button=form.querySelector('button'),status=document.querySelector('#login-error');button.disabled=true;status.textContent='';
  try{const session=await fetch('../api.php?action=session',{credentials:'same-origin',cache:'no-store'}).then(r=>r.json());const response=await fetch('../api.php?action=login',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-CSRF-Token':session.csrf},body:JSON.stringify(Object.fromEntries(new FormData(form)))});const data=await response.json();if(!response.ok||!data.ok)throw Error(data.error||'Nie udało się zalogować.');location.assign('./');}catch(error){status.textContent=error.message||'Nie udało się połączyć z serwerem.';button.disabled=false;}
});
