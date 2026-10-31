(() => {
  const canvas=document.getElementById('area-referencia-canvas');
  const campo=document.getElementById('area-json'); const estado=document.getElementById('area-referencia-estado'); const salvar=document.getElementById('salvar-area');
  if(!canvas||!campo) return;
  const ctx=canvas.getContext('2d'); const img=new Image(); let area=null, inicio=null;
  try { const salvo=JSON.parse(campo.value); if(Array.isArray(salvo)&&salvo.length===4) area=salvo.map(Number); } catch(_) {}
  function ponto(ev){const r=canvas.getBoundingClientRect();return [(ev.clientX-r.left)*canvas.width/r.width,(ev.clientY-r.top)*canvas.height/r.height];}
  function desenhar(){ctx.clearRect(0,0,canvas.width,canvas.height);ctx.drawImage(img,0,0,canvas.width,canvas.height);if(area){const [x,y,w,h]=area;ctx.fillStyle='rgba(0,123,255,.18)';ctx.strokeStyle='#006dff';ctx.lineWidth=Math.max(2,canvas.width/400);ctx.fillRect(x*canvas.width,y*canvas.height,w*canvas.width,h*canvas.height);ctx.strokeRect(x*canvas.width,y*canvas.height,w*canvas.width,h*canvas.height);}}
  function salvarCampo(){campo.value=area?JSON.stringify(area.map(v=>Math.round(v*10000)/10000)):''; estado.textContent=area?'Área marcada. Salve para usar este trecho nas próximas montagens.':'Sem área marcada: ao salvar, o motor usará leitura automática da amostra.'; if(salvar) salvar.textContent=area?'Salvar área de referência':'Salvar leitura automática';}
  img.onload=()=>{canvas.width=img.naturalWidth;canvas.height=img.naturalHeight;desenhar();salvarCampo();};img.src=canvas.dataset.imagem;
  canvas.addEventListener('pointerdown',ev=>{inicio=ponto(ev);canvas.setPointerCapture(ev.pointerId);});
  canvas.addEventListener('pointermove',ev=>{if(!inicio)return;const [x,y]=ponto(ev);area=[Math.min(inicio[0],x)/canvas.width,Math.min(inicio[1],y)/canvas.height,Math.abs(x-inicio[0])/canvas.width,Math.abs(y-inicio[1])/canvas.height];desenhar();});
  canvas.addEventListener('pointerup',()=>{inicio=null;salvarCampo();});
  document.getElementById('limpar-area')?.addEventListener('click',()=>{area=null;desenhar();salvarCampo();});
})();
