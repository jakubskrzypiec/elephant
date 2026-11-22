/* Produce a clean upload folder; never includes local credentials or customer data. */
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),cp=require('node:child_process'),crypto=require('node:crypto');
const repo=path.resolve(__dirname,'..');
const output=fs.mkdtempSync(path.join(os.tmpdir(),'elephant-hosting-'));
const tracked=cp.execFileSync('git',['ls-files','-z'],{cwd:repo,encoding:'utf8'}).split('\0').filter(Boolean);
const files=tracked.filter(p=>/^(?:assets\/|[^/]+\.(?:html|css|js|png|svg|ico|webp|txt)$)/.test(p));
for(const p of ['api.php','site.php','panel/index.php','panel/login.php','panel/login.js','admin-preview/index.html','admin-preview/panel.css','admin-preview/panel.js','panel-backend/migrate.php','panel-backend/create-admin.php','panel-backend/worker.php','panel-backend/seed.json','panel-backend/.htaccess'])files.push(p);
function walk(dir){for(const e of fs.readdirSync(path.join(repo,dir),{withFileTypes:true})){const p=dir+'/'+e.name;if(e.isDirectory())walk(p);else files.push(p);}}
for(const dir of ['panel-backend/src','panel-backend/public','panel-backend/vendor'])walk(dir);
const manifest=[];
for(const p of [...new Set(files)].sort()){
 if(/(?:^|\/)(?:\.env(?:\.|$)|config\.local|runtime\/|node_modules\/)|\.(?:sqlite|eml)$/.test(p))throw Error('Private file in manifest: '+p);
 const target=path.join(output,p);fs.mkdirSync(path.dirname(target),{recursive:true});fs.copyFileSync(path.join(repo,p),target);manifest.push(crypto.createHash('sha256').update(fs.readFileSync(target)).digest('hex')+'  '+p);
}
fs.copyFileSync(path.join(repo,'panel-backend/deploy/apache.htaccess'),path.join(output,'.htaccess'));
fs.writeFileSync(path.join(output,'panel-backend/manifest.txt'),manifest.join('\n')+'\n');
console.log(JSON.stringify({directory:output,files:manifest.length,bytes:manifest.reduce((n,l)=>n+fs.statSync(path.join(output,l.slice(66))).size,0)}));
