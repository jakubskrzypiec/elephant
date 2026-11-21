// Local preview server. Never expose this development server on a public interface.
const http=require('node:http'),fs=require('node:fs'),path=require('node:path');
const root=path.resolve(__dirname,'..');
const mime={'.html':'text/html; charset=utf-8','.js':'text/javascript; charset=utf-8','.css':'text/css; charset=utf-8','.webp':'image/webp','.png':'image/png','.jpg':'image/jpeg','.svg':'image/svg+xml'};
const server=http.createServer((req,res)=>{
  try {
    let relative=decodeURIComponent(new URL(req.url,'http://localhost').pathname);
    if(relative==='/')relative='/admin-preview/';
    if(relative.endsWith('/'))relative+='index.html';
    const filename=path.resolve(root,'.'+relative),extension=path.extname(filename);
    if(!filename.startsWith(root+path.sep)||!mime[extension])return res.writeHead(404).end('Not found');
    fs.readFile(filename,(error,data)=>{if(error)return res.writeHead(404).end('Not found');res.setHeader('Content-Type',mime[extension]);res.setHeader('X-Content-Type-Options','nosniff');res.end(data);});
  }catch{res.writeHead(400).end('Bad request');}
});
server.listen(Number(process.env.PORT||4180),'127.0.0.1',()=>console.log('Panel preview: http://127.0.0.1:'+server.address().port+'/admin-preview/'));
