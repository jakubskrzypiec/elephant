/* Directional motion blur across every hero, without blurring the copy. */
(() => {
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  const updates = [];
  const vertex = 'attribute vec2 pos;varying vec2 uv;void main(){uv=pos*.5+.5;gl_Position=vec4(pos,0.,1.);}';
  const fragment = `precision mediump float;
    varying vec2 uv;uniform sampler2D photo;uniform float time,aspect,imageAspect,isBanner;
    void main(){
      vec2 cover=aspect<imageAspect?vec2(aspect/imageAspect,1.):vec2(1.,imageAspect/aspect);
      vec2 p=(uv-.5)*cover+.5;
      if(isBanner>.5 && aspect<2.)p.x+=(1.-cover.x)*.22;
      // Gentle camera drift and a single soft focus plane, with no ripple stripes.
      p=(p-.5)*.92+.5;
      p+=vec2(sin(time*.32)*.025,cos(time*.25)*.004);
      float amount=.014+.013*(.5+.5*sin(time*.4));
      vec3 color=vec3(0.);float weight=0.;
      for(int i=-8;i<=8;i++){
        float f=float(i)/8.;float w=exp(-f*f*2.);
        vec2 offset=vec2(f*amount*cover.x,sin(float(i)*2.4)*amount*.24);
        vec2 sampleAt=clamp(p+offset,vec2(.001),vec2(.999));
        color+=texture2D(photo,sampleAt).rgb*w;weight+=w;
      }
      gl_FragColor=vec4(color/weight,1.);
    }`;
  const compile=(gl,type,source)=>{
    const shader=gl.createShader(type);gl.shaderSource(shader,source);gl.compileShader(shader);
    if(!gl.getShaderParameter(shader,gl.COMPILE_STATUS))throw new Error('Hero shader unavailable');
    return shader;
  };
  const create=async host=>{
    const source=host.querySelector('.hero-frames img,svg image');
    if(!source)return;
    const img=new Image();img.src=source.getAttribute('src')||source.getAttribute('href');
    try{await img.decode();}catch{return;}
    const canvas=document.createElement('canvas');canvas.className='hero-motion-canvas';canvas.setAttribute('aria-hidden','true');
    const gl=canvas.getContext('webgl',{alpha:false,antialias:false,powerPreference:'low-power'});
    if(!gl)return;
    try{
      const program=gl.createProgram();gl.attachShader(program,compile(gl,gl.VERTEX_SHADER,vertex));gl.attachShader(program,compile(gl,gl.FRAGMENT_SHADER,fragment));gl.linkProgram(program);
      if(!gl.getProgramParameter(program,gl.LINK_STATUS))return;
      gl.useProgram(program);
      gl.bindBuffer(gl.ARRAY_BUFFER,gl.createBuffer());gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([-1,-1,1,-1,-1,1,-1,1,1,-1,1,1]),gl.STATIC_DRAW);
      const pos=gl.getAttribLocation(program,'pos');gl.enableVertexAttribArray(pos);gl.vertexAttribPointer(pos,2,gl.FLOAT,false,0,0);
      gl.bindTexture(gl.TEXTURE_2D,gl.createTexture());gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL,true);
      gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.LINEAR);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MAG_FILTER,gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_S,gl.CLAMP_TO_EDGE);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_T,gl.CLAMP_TO_EDGE);
      const softened=document.createElement('canvas');softened.width=img.width;softened.height=img.height;
      const ctx=softened.getContext('2d');ctx.filter='blur(6px)';ctx.drawImage(img,-12,-12,img.width+24,img.height+24);
      gl.texImage2D(gl.TEXTURE_2D,0,gl.RGB,gl.RGB,gl.UNSIGNED_BYTE,softened);
      gl.uniform1i(gl.getUniformLocation(program,'photo'),0);gl.uniform1f(gl.getUniformLocation(program,'imageAspect'),img.width/img.height);
      gl.uniform1f(gl.getUniformLocation(program,'isBanner'),host.classList.contains('page-banner')?1:0);
      const time=gl.getUniformLocation(program,'time'),aspect=gl.getUniformLocation(program,'aspect');
      let seconds=0,last=0,frame=0,visible=false;
      const render=()=>{
        const rect=host.getBoundingClientRect();if(!rect.width||!rect.height)return;
        const dpr=Math.min(devicePixelRatio,1.25),w=Math.round(rect.width*dpr),h=Math.round(rect.height*dpr);
        if(canvas.width!==w||canvas.height!==h){canvas.width=w;canvas.height=h;gl.viewport(0,0,w,h);}
        gl.uniform1f(aspect,rect.width/rect.height);gl.uniform1f(time,seconds);gl.drawArrays(gl.TRIANGLES,0,6);
      };
      const tick=now=>{if(last)seconds+=(now-last)/1000;last=now;render();frame=requestAnimationFrame(tick);};
      const update=()=>{cancelAnimationFrame(frame);last=0;canvas.hidden=preference.matches;if(visible&&!document.hidden&&!preference.matches){render();frame=requestAnimationFrame(tick);}};
      const observer=new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;update();},{threshold:.01});
      host.append(canvas);observer.observe(host);updates.push(update);
      const resize=new ResizeObserver(()=>{if(visible&&!preference.matches)render();});resize.observe(host);
      canvas.addEventListener('webglcontextlost',e=>{e.preventDefault();cancelAnimationFrame(frame);canvas.remove();observer.disconnect();resize.disconnect();});
    }catch{canvas.remove();}
  };
  document.querySelectorAll('.hero,.page-banner').forEach(create);
  preference.addEventListener('change',()=>updates.forEach(fn=>fn()));
  document.addEventListener('visibilitychange',()=>updates.forEach(fn=>fn()));
})();
