/* Real PHP/PDO integration tests. Runs in a fresh private directory; never sends SMTP mail. */
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net'),cp=require('node:child_process');
const root=path.resolve(__dirname,'../..');
const php=process.env.ELEPHANT_PHP||(process.platform==='win32'?path.join(process.env.LOCALAPPDATA,'ElephantPanel/tools/php/php.exe'):'php');
async function freePort(){const s=net.createServer();await new Promise(r=>s.listen(0,'127.0.0.1',r));const p=s.address().port;await new Promise(r=>s.close(r));return p;}
function run(file,args=[],env={}){const r=cp.spawnSync(php,[path.join(root,'panel-backend',file),...args],{cwd:root,env:{...process.env,...env},encoding:'utf8'});assert.equal(r.status,0,r.stderr||r.stdout);return r.stdout;}
function client(base){const cookies=new Map();return async(action,method='GET',body,csrf,extra={})=>{const headers={...extra};if(cookies.size)headers.Cookie=[...cookies].map(([k,v])=>k+'='+v).join('; ');if(csrf)headers['X-CSRF-Token']=csrf;const form=body instanceof FormData;if(body&&!form)headers['Content-Type']='application/json';const response=await fetch(base+'/api.php?action='+action,{method,headers,...(body?{body:form?body:JSON.stringify(body)}:{})});for(const c of response.headers.getSetCookie()){const pair=c.split(';')[0],at=pair.indexOf('=');cookies.set(pair.slice(0,at),pair.slice(at+1));}const raw=await response.text();let data;try{data=JSON.parse(raw);}catch{}return {status:response.status,data,raw,headers:response.headers};};}
(async()=>{
 const port=await freePort(),base='http://127.0.0.1:'+port,privateDir=fs.mkdtempSync(path.join(os.tmpdir(),'elephant-backend-test-'));
 run('setup-local.php',[privateDir,String(port)]);const env={ELEPHANT_CONFIG:path.join(privateDir,'config.php')};const login=fs.readFileSync(path.join(privateDir,'local-login.txt'),'utf8'),password=login.match(/Password: (.+)/)[1];
 let serverLog='';const server=cp.spawn(php,['-S','127.0.0.1:'+port,'-t',root,path.join(root,'panel-backend/local-router.php')],{cwd:root,env:{...process.env,...env},stdio:['ignore','pipe','pipe']});server.stdout.on('data',d=>serverLog+=d);server.stderr.on('data',d=>serverLog+=d);
 try {
  for(let i=0;i<100;i++){try{await fetch(base+'/api.php?action=session');break;}catch{await new Promise(r=>setTimeout(r,80));}}
  const admin=client(base),publicClient=client(base),attacker=client(base);
  const unauthorized=await admin('state');assert.equal(unauthorized.status,401);let session=await admin('session');assert.equal(session.data.authenticated,false);let token=session.data.csrf;
  assert(unauthorized.headers.get('set-cookie').includes('HttpOnly'));assert(unauthorized.headers.get('set-cookie').includes('SameSite=Strict'));
  assert.equal((await admin('login','POST',{email:'admin@elephant.local',password})).status,403);
  assert.equal((await admin('login','POST',{email:'admin@elephant.local',password:'incorrect'},token)).status,401);
  let signed=await admin('login','POST',{email:'admin@elephant.local',password},token);assert.equal(signed.status,200);assert.notEqual(signed.data.csrf,token);token=signed.data.csrf;
  assert.equal((await admin('state','PUT',{},'invalid')).status,403);
  let snapshot=(await admin('state')).data;assert.equal(snapshot.state.projects.length,5);assert.equal(snapshot.state.articles.length,6);assert.equal(snapshot.state.inquiries.length,0);assert.equal(snapshot.state.bookings.length,0);
  assert.deepEqual(snapshot.google,{configured:false,connected:false,email:null});
  assert.equal((await admin('google-connect','POST',{},'invalid')).status,403);
  assert.equal((await admin('google-connect','GET')).status,405);
  assert.equal((await admin('google-connect','POST',{},token)).status,503);
  assert.equal((await attacker('google-connect','POST',{},token)).status,401);
  assert.equal((await admin('google-disconnect','POST',{},'invalid')).status,403);
  assert.equal((await fetch(base+'/panel/google.php?state=invalid')).status,400);
  const initialRevision=snapshot.revision;
  const publicToken=(await publicClient('public')).data.csrf;
  assert.equal((await publicClient('contact','POST',{name:'Test',email:'test@example.com',message:'Test',consent:true},publicToken,{Origin:'https://evil.invalid'})).status,403);
  assert.equal((await publicClient('contact','POST',{name:'Test',email:'test@example.com',message:'Test'},publicToken)).status,422);
  let response=await publicClient('contact','POST',{name:'Test',surname:'Integracyjny',email:'test@example.com',message:'Zapytanie testowe — bez wysyłki SMTP.',consent:true,oferta:'Projekt kompleksowy',city:'Wrocław'},publicToken);assert.equal(response.status,201,response.raw);
  snapshot=(await admin('state')).data;assert.equal(snapshot.state.inquiries.length,1);assert.equal(snapshot.state.inquiries[0].details.city,'Wrocław');
  assert.equal((await admin('state','PUT',{state:snapshot.state,revision:initialRevision},token)).status,409);
  async function save(){const result=await admin('state','PUT',{state:snapshot.state,revision:snapshot.revision},token);assert.equal(result.status,200,result.raw);snapshot=result.data;}
  snapshot.state.inquiries[0].notes='Notatka administratorki';snapshot.state.inquiries[0].status='read';snapshot.state.inquiries[0].message='FORGED MESSAGE';await save();assert.equal(snapshot.state.inquiries[0].message,'Zapytanie testowe — bez wysyłki SMTP.');
  let future=new Date(Date.now()+8*86400000);while(future.getUTCDay()!==1)future=new Date(future.getTime()+86400000);const monday=future.toISOString().slice(0,10),saturday=new Date(future.getTime()+5*86400000).toISOString().slice(0,10);
  assert.deepEqual((await publicClient('slots&date='+saturday)).data.slots,[]);assert((await publicClient('slots&date='+monday)).data.slots.includes('10:00'));
  assert.equal((await publicClient('reserve','POST',{name:'Test',email:'test@example.com',date:saturday,time:'10:00',consent:true},publicToken)).status,409);
  response=await publicClient('reserve','POST',{name:'Test',email:'test@example.com',phone:'123456789',date:monday,time:'10:00',consent:true},publicToken);assert.equal(response.status,201,response.raw);
  assert(!(await publicClient('slots&date='+monday)).data.slots.includes('10:00'));assert(!(await publicClient('slots&date='+monday)).data.slots.includes('10:30'));
  const second=client(base),secondToken=(await second('public')).data.csrf;
  const concurrent=await Promise.all([publicClient('reserve','POST',{name:'Pierwszy',email:'first@example.com',date:monday,time:'14:00',consent:true},publicToken),second('reserve','POST',{name:'Drugi',email:'second@example.com',date:monday,time:'14:00',consent:true},secondToken)]);assert.deepEqual(concurrent.map(r=>r.status).sort(),[201,409]);
  snapshot=(await admin('state')).data;const retainedBooking=snapshot.state.bookings.find(b=>b.phone==='123456789');assert(retainedBooking&&retainedBooking.consentAt);const retainedConsent=retainedBooking.consentAt;snapshot.state.bookings[0].status='confirmed';await save();assert.equal(snapshot.state.bookings.find(b=>b.id===retainedBooking.id).phone,'123456789');assert.equal(snapshot.state.bookings.find(b=>b.id===retainedBooking.id).consentAt,retainedConsent);assert(snapshot.state.bookings[0].startsAt.endsWith('+00:00'));assert(snapshot.state.bookings[0].endsAt.endsWith('+00:00'));
  const png=cp.spawnSync(php,['-r','$i=imagecreatetruecolor(4,4); imagepng($i); imagedestroy($i);']).stdout;
  const upload=new FormData();upload.append('photo',new Blob([png],{type:'image/png'}),'test.png');response=await admin('upload','POST',upload,token);assert.equal(response.status,201,response.raw);const photo=response.data.photo;
  const imageAction=photo.src.split('?action=')[1];assert.equal((await publicClient(imageAction)).status,404);assert.equal((await admin(imageAction)).status,200);
  const badUpload=new FormData();badUpload.append('photo',new Blob(['<?php echo "unsafe";'],{type:'image/png'}),'bad.png');assert.equal((await admin('upload','POST',badUpload,token)).status,422);
  snapshot=(await admin('state')).data;snapshot.state.projects.push({id:'integration-project',slug:'projekt-test-integracyjny',title:'Projekt testowy $5 & detal',description:'Opis realizacji z bazy danych.',status:'draft',featured:true,order:6,photos:[photo]});await save();
  assert.equal((await fetch(base+'/projekt-test-integracyjny.html')).status,404);
  snapshot.state.projects.at(-1).status='published';await save();let html=await fetch(base+'/projekt-test-integracyjny.html').then(r=>r.text());assert(html.includes('Projekt testowy $5 &amp; detal'),html.slice(0,500));assert(html.includes('Opis realizacji z bazy danych.'));assert.equal((await publicClient(imageAction)).status,200);
  html=await fetch(base+'/projekty.html').then(r=>r.text());assert(html.includes('6 projektów'));const schema=JSON.parse(html.match(/<script type="application\/ld\+json">(.*?)<\/script>/s)[1]);assert.equal(schema['@graph'].find(x=>x['@type']==='ItemList').itemListElement.length,6);assert(html.includes('projekt-test-integracyjny.html'));
  assert((await fetch(base+'/').then(r=>r.text())).includes('projekt-test-integracyjny.html'));assert((await fetch(base+'/sitemap.xml').then(r=>r.text())).includes('projekt-test-integracyjny.html'));
  snapshot.state.projects.at(-1).slug='projekt-test-zmieniony';await save();let moved=await fetch(base+'/projekt-test-integracyjny.html',{redirect:'manual'});assert.equal(moved.status,301);assert.equal(moved.headers.get('location'),'/projekt-test-zmieniony.html');assert.equal((await fetch(base+'/projekt-test-zmieniony.html')).status,200);snapshot.state.projects.at(-1).status='draft';await save();assert.equal((await fetch(base+'/projekt-test-integracyjny.html')).status,404);assert.equal((await publicClient(imageAction)).status,404);
  snapshot.state.articles.push({id:'integration-article',slug:'artykul-test-integracyjny',title:'Artykuł testowy',excerpt:'Krótki opis testowy.',body:'## Pierwszy rozdział\n\n<script>alert(1)</script>\n\nDrugi akapit.',status:'published',seoTitle:'SEO test',seoDescription:'Opis SEO testu.'});await save();html=await fetch(base+'/artykul-test-integracyjny.html').then(r=>r.text());assert(html.includes('Pierwszy rozdział'));assert(html.includes('&lt;script&gt;alert(1)&lt;/script&gt;'));assert(!html.includes('<script>alert(1)</script>'));assert(html.includes('id="czytaj-1"'));assert((await fetch(base+'/artykuly.html').then(r=>r.text())).includes('artykul-test-integracyjny.html'));
  html=await fetch(base+'/oferty.html').then(r=>r.text());assert(html.includes('data-native-booking'));assert(!html.includes('calendly.com'));assert(!html.includes('formsubmit.co'));html=await fetch(base+'/kontakt.html').then(r=>r.text());assert(html.includes('data-contact-form'));assert(!html.includes('formsubmit.co'));
  for(const [legacy,target] of Object.entries({'/contact/':'/kontakt.html','/oferta2/':'/oferty.html','/services/':'/projekty.html','/wp-sitemap.xml':'/sitemap.xml'})){const moved=await fetch(base+legacy,{redirect:'manual'});assert.equal(moved.status,301);assert.equal(moved.headers.get('location'),target);}
  for(const blocked of ['/panel-backend/src/bootstrap.php','/panel-backend/config.example.php','/panel-backend/seed.json','/.git/config'])assert.equal((await fetch(base+blocked)).status,404,blocked);
  run('worker.php',['30'],env);const health=(await admin('delivery')).data.delivery;assert.equal(health.pending,0);assert.equal(health.sent,0);assert(health.simulated>=5);assert(fs.readdirSync(path.join(privateDir,'mail')).some(f=>f.endsWith('.eml')));
  snapshot=(await admin('state')).data;const beforeDeletion=structuredClone(snapshot.state),deleteId=snapshot.state.inquiries[0].id,deleteRevision=snapshot.revision;
  assert.equal((await publicClient('delete-inquiry','POST',{id:deleteId,revision:deleteRevision},publicToken)).status,401);
  assert.equal((await admin('delete-inquiry','POST',{id:deleteId,revision:deleteRevision})).status,403);
  assert.equal((await admin('delete-inquiry','GET')).status,405);
  assert.equal((await admin('delete-inquiry','POST',{id:deleteId,revision:deleteRevision-1},token)).status,409);
  assert.equal((await admin('delete-inquiry','POST',{id:'missing-inquiry',revision:deleteRevision},token)).status,404);
  response=await admin('delete-inquiry','POST',{id:deleteId,revision:deleteRevision},token);assert.equal(response.status,200,response.raw);assert.equal(response.data.revision,deleteRevision+1);
  beforeDeletion.inquiries=beforeDeletion.inquiries.filter(i=>i.id!==deleteId);assert.deepEqual(response.data.state,beforeDeletion);assert.deepEqual((await admin('state')).data.state,beforeDeletion);
  assert.equal((await admin('delete-inquiry','POST',{id:deleteId,revision:deleteRevision},token)).status,409);
  const otherDevice=client(base);let otherSession=(await otherDevice('session')).data;assert.equal((await otherDevice('login','POST',{email:'admin@elephant.local',password},otherSession.csrf)).status,200);
  const changedPassword='Changed-local-test-password-2026';
  assert.equal((await publicClient('change-password','POST',{currentPassword:password,newPassword:changedPassword,confirmPassword:changedPassword},publicToken)).status,401);
  assert.equal((await admin('change-password','POST',{currentPassword:password,newPassword:changedPassword,confirmPassword:changedPassword})).status,403);
  assert.equal((await admin('change-password','POST',{currentPassword:'incorrect',newPassword:changedPassword,confirmPassword:changedPassword},token)).status,403);
  assert.equal((await admin('change-password','POST',{currentPassword:password,newPassword:'short',confirmPassword:'short'},token)).status,422);
  assert.equal((await admin('change-password','POST',{currentPassword:password,newPassword:changedPassword,confirmPassword:'does-not-match'},token)).status,422);
  assert.equal((await admin('change-password','POST',{currentPassword:password,newPassword:changedPassword,confirmPassword:changedPassword},token)).status,200);
  assert.equal((await admin('state')).status,401);assert.equal((await otherDevice('state')).status,401);
  token=(await admin('session')).data.csrf;
  assert.equal((await admin('login','POST',{email:'admin@elephant.local',password},token)).status,401);
  signed=await admin('login','POST',{email:'admin@elephant.local',password:changedPassword},token);assert.equal(signed.status,200);token=signed.data.csrf;
  assert.equal((await admin('logout','POST',{},token)).status,200);assert.equal((await admin('state')).status,401);
  let badToken=(await attacker('session')).data.csrf;let limit;for(let n=0;n<9;n++)limit=await attacker('login','POST',{email:'admin@elephant.local',password:'wrong'},badToken);assert.equal(limit.status,429);
  assert(!/PHP (?:Warning|Fatal|Parse)|Uncaught/.test(serverLog),serverLog);
  console.log('PASS PHP/PDO: sessions, CSRF/origin, login throttling, private data, revision conflicts, forms, immutable messages, slots/weekends/buffers, concurrent reservations, notifications, image validation/privacy, publication/drafts/sitemap, article escaping, logout.');
  console.log('Mail transport: log only. '+health.simulated+' messages written privately, 0 real emails.');
  console.log('Test data: '+privateDir);
 }finally{server.kill();}
})().catch(error=>{console.error(error);process.exitCode=1;});
