/* Separate slow illumination from surface detail: move light, not plaster or floor. */
(() => {
  const preference = matchMedia('(prefers-reduced-motion: reduce)');
  const scenes = [];
  const vertex = `attribute vec2 position; varying vec2 uv;
    void main(){uv=position*.5+.5;gl_Position=vec4(position,0.,1.);}`;
  const fragment = `precision mediump float;
    varying vec2 uv; uniform sampler2D photo; uniform sampler2D light;
    uniform float time; uniform float aspect; uniform float imageAspect; uniform float shadowEnd; uniform float shadowStrength; uniform float shadowSpeed;
    float luma(vec3 c){return dot(c,vec3(.2126,.7152,.0722));}
    void main(){
      vec2 cover=aspect<imageAspect?vec2(aspect/imageAspect,1.):vec2(1.,imageAspect/aspect);
      vec2 p=(uv-.5)*cover+.5;
      float breeze=sin(time*.68*shadowSpeed); float second=sin(time*.49*shadowSpeed);
      // Move the illumination already present in the photograph, never its geometry.
      vec2 drift=vec2(.032*breeze,.014*second);
      float wall=(1.-smoothstep(shadowEnd-.14,shadowEnd,p.x))*smoothstep(.06,.20,p.y);
      float originalLight=luma(texture2D(light,p).rgb);
      float movedLight=luma(texture2D(light,p+drift).rgb);
      float ratio=clamp(1.+(movedLight/max(originalLight,.08)-1.)*shadowStrength,.60,1.55);
      vec3 detail=texture2D(photo,p).rgb;
      gl_FragColor=vec4(detail*mix(1.,ratio,wall),1.);
    }`;
  const compile = (gl, type, source) => {
    const shader = gl.createShader(type); gl.shaderSource(shader, source); gl.compileShader(shader);
    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) throw new Error('Scene shader unavailable');
    return shader;
  };
  const create = async svg => {
    const host=svg.parentElement, img=new Image();
    img.src=svg.querySelector('image').getAttribute('href');
    try { await img.decode(); } catch { return; }
    const canvas=document.createElement('canvas'); canvas.className='living-light-canvas'; canvas.setAttribute('aria-hidden','true');
    const gl=canvas.getContext('webgl',{alpha:false,antialias:false,powerPreference:'low-power'});
    if(!gl)return;
    try {
      const program=gl.createProgram();gl.attachShader(program,compile(gl,gl.VERTEX_SHADER,vertex));gl.attachShader(program,compile(gl,gl.FRAGMENT_SHADER,fragment));gl.linkProgram(program);
      if(!gl.getProgramParameter(program,gl.LINK_STATUS))return;
      gl.useProgram(program);
      const buffer=gl.createBuffer();gl.bindBuffer(gl.ARRAY_BUFFER,buffer);gl.bufferData(gl.ARRAY_BUFFER,new Float32Array([-1,-1,1,-1,-1,1,-1,1,1,-1,1,1]),gl.STATIC_DRAW);
      const position=gl.getAttribLocation(program,'position');gl.enableVertexAttribArray(position);gl.vertexAttribPointer(position,2,gl.FLOAT,false,0,0);
      // Blur only for illumination analysis. The displayed material retains full detail.
      const illumination=document.createElement('canvas');illumination.width=1200;illumination.height=Math.round(1200*img.height/img.width);
      const context=illumination.getContext('2d');context.filter='blur(9px)';context.drawImage(img,-18,-18,illumination.width+36,illumination.height+36);
      [img,illumination].forEach((source,i)=>{gl.activeTexture(gl.TEXTURE0+i);gl.bindTexture(gl.TEXTURE_2D,gl.createTexture());gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL,true);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MIN_FILTER,gl.LINEAR);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_MAG_FILTER,gl.LINEAR);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_S,gl.CLAMP_TO_EDGE);gl.texParameteri(gl.TEXTURE_2D,gl.TEXTURE_WRAP_T,gl.CLAMP_TO_EDGE);gl.texImage2D(gl.TEXTURE_2D,0,gl.RGB,gl.RGB,gl.UNSIGNED_BYTE,source);gl.uniform1i(gl.getUniformLocation(program,i?'light':'photo'),i);});
      const time=gl.getUniformLocation(program,'time'),aspect=gl.getUniformLocation(program,'aspect');
      gl.uniform1f(gl.getUniformLocation(program,'imageAspect'),img.width/img.height);
      gl.uniform1f(gl.getUniformLocation(program,'shadowEnd'),Number(svg.dataset.shadowEnd||.70));
      gl.uniform1f(gl.getUniformLocation(program,'shadowStrength'),Number(svg.dataset.shadowStrength||1));
      gl.uniform1f(gl.getUniformLocation(program,'shadowSpeed'),Number(svg.dataset.shadowSpeed||1));
      let seconds=0,last=0,frame=0,visible=false;
      const render=()=>{const rect=host.getBoundingClientRect();const dpr=Math.min(devicePixelRatio,1.5);const width=Math.round(rect.width*dpr),height=Math.round(rect.height*dpr);if(!width||!height)return;if(canvas.width!==width||canvas.height!==height){canvas.width=width;canvas.height=height;gl.viewport(0,0,width,height);}gl.uniform1f(aspect,rect.width/rect.height);gl.uniform1f(time,seconds);gl.drawArrays(gl.TRIANGLES,0,6);};
      const tick=now=>{frame=0;if(last)seconds+=(now-last)/1000;last=now;render();frame=requestAnimationFrame(tick);};
      const update=()=>{cancelAnimationFrame(frame);frame=0;last=0;canvas.hidden=preference.matches;if(visible&&!document.hidden&&!preference.matches){render();frame=requestAnimationFrame(tick);}};
      const observer=new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;update();},{threshold:.01});
      host.append(canvas);host.classList.add('living-light-ready');observer.observe(host);scenes.push(update);
      canvas.addEventListener('webglcontextlost',event=>{event.preventDefault();cancelAnimationFrame(frame);canvas.remove();host.classList.remove('living-light-ready');observer.disconnect();});
      canvas.addEventListener('webglcontextrestored',()=>location.reload(),{once:true});
      new ResizeObserver(()=>{if(visible&&!preference.matches)render();}).observe(host);
    } catch { canvas.remove(); }
  };
  document.querySelectorAll('[data-living-surface]').forEach(create);
  preference.addEventListener('change',()=>scenes.forEach(update=>update()));
  document.addEventListener('visibilitychange',()=>scenes.forEach(update=>update()));
})();
