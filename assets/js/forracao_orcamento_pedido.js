/* Cor ilustrativa por linha: não cria capa, não altera produto_id nem estoque. */
(() => {
  'use strict';
  const raiz = window.MOBEL_FORRACAO_BASE;
  if (!raiz) return;
  const url = `${raiz}/views/produtos/`;
  const tokenUrl = () => `token=${encodeURIComponent(String(window.MOBEL_COLOR_CATALOG_TOKEN || ''))}`;
  const cache = new Map();
  const detalhes = new Map();
  const normalizar = texto => String(texto || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').replace(/[^a-z0-9]+/g, ' ').trim();
  const foto = caminho => {
    if (!cache.has(caminho)) {
      const carregamento = new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Foto do mestre ou referência indisponível.'));
      img.src = caminho;
      });
      cache.set(caminho, carregamento);
      carregamento.catch(() => { if (cache.get(caminho) === carregamento) cache.delete(caminho); });
    }
    return cache.get(caminho);
  };
  function tomDaAmostra(img, cor) {
    const canvas = document.createElement('canvas');
    canvas.width = img.naturalWidth; canvas.height = img.naturalHeight;
    const ctx = canvas.getContext('2d', {willReadFrequently:true});
    ctx.drawImage(img, 0, 0);
    const a = cor.area_referencia;
    try {
      if (Array.isArray(a) && a.length === 4) {
        const x = Math.max(0, Math.floor(Number(a[0]) * canvas.width));
        const y = Math.max(0, Math.floor(Number(a[1]) * canvas.height));
        const w = Math.max(1, Math.min(canvas.width - x, Math.floor(Number(a[2]) * canvas.width)));
        const h = Math.max(1, Math.min(canvas.height - y, Math.floor(Number(a[3]) * canvas.height)));
        return window.MOBEL_RECOLORACAO.extrairTom(ctx.getImageData(x,y,w,h).data,w,h);
      }
      return window.MOBEL_RECOLORACAO.extrairTom(ctx.getImageData(0,0,canvas.width,canvas.height).data,canvas.width,canvas.height);
    } catch (erro) {
      if (/^#[0-9a-f]{6}$/i.test(cor.hex || '')) return [1,3,5].map(i => parseInt(cor.hex.slice(i,i+2),16));
      throw erro;
    }
  }
  async function montar(modeloId, cor) {
    if (!window.MOBEL_RECOLORACAO) throw new Error('Motor de cores indisponível; recarregue a página.');
    const chave = `${modeloId}:${cor.id}:${cor.amostra_id}`;
    if (cache.has(`resultado:${chave}`)) return cache.get(`resultado:${chave}`);
    const trabalho = (async () => {
      const [base, mascara, amostra] = await Promise.all([
        foto(`${url}modelo_base.php?id=${modeloId}&${tokenUrl()}`),
        foto(`${url}modelo_base.php?id=${modeloId}&parte=mascara&${tokenUrl()}`),
        foto(`${url}amostra_imagem.php?id=${cor.amostra_id}&${tokenUrl()}`),
      ]);
      const canvas = document.createElement('canvas');
      canvas.width = 800; canvas.height = 800;
      const ctx = canvas.getContext('2d', {willReadFrequently:true});
      ctx.drawImage(base,0,0,800,800);
      const pixels = ctx.getImageData(0,0,800,800);
      const maskCanvas = document.createElement('canvas');
      maskCanvas.width=800; maskCanvas.height=800;
      const maskCtx = maskCanvas.getContext('2d', {willReadFrequently:true});
      maskCtx.drawImage(mascara,0,0,800,800);
      const mask = maskCtx.getImageData(0,0,800,800);
      const tom = tomDaAmostra(amostra, cor);
      window.MOBEL_RECOLORACAO.aplicar(pixels.data,mask.data,tom);
      ctx.putImageData(pixels,0,0);
      return canvas.toDataURL('image/png');
    })();
    cache.set(`resultado:${chave}`, trabalho);
    trabalho.catch(() => { if (cache.get(`resultado:${chave}`) === trabalho) cache.delete(`resultado:${chave}`); });
    return trabalho;
  }
  function obterDetalhes(produtoId) {
    if (!detalhes.has(produtoId)) {
      const consulta = fetch(`${url}forracao_visual.php?produto_id=${produtoId}`, {
        credentials:'same-origin',
        headers:{'X-Mobel-Color-Token': String(window.MOBEL_COLOR_CATALOG_TOKEN || '')}
      })
        .then(resposta => {
          if (!resposta.ok) throw new Error(`Consulta de cores indisponível (${resposta.status}).`);
          return resposta.json();
        })
        .then(dados => {
          if (!Array.isArray(dados.cores)) throw new Error('Resposta de cores incompleta.');
          return dados;
        });
      detalhes.set(produtoId, consulta);
      consulta.catch(() => { if (detalhes.get(produtoId) === consulta) detalhes.delete(produtoId); });
    }
    return detalhes.get(produtoId);
  }
  function corDaObservacao(texto, cores) {
    const observacao = String(texto || '').trim();
    const marcada = observacao.match(/(?:^|\|\s*)cor:\s*([^|]+)/i);
    const candidata = normalizar(marcada ? marcada[1] : observacao.replace(/^cor\s+/i,''));
    if (!candidata) return null;
    return cores.find(c => [c.nome,...c.aliases].some(nome => normalizar(nome) === candidata)) || null;
  }
  function inserirObservacao(atual, cor, cores) {
    let texto = String(atual || '').replace(/(?:^|\|\s*)cor:\s*[^|]+/i,'').replace(/^\s*\|\s*|\s*\|\s*$/g,'').trim();
    if (corDaObservacao(texto, cores)) texto = '';
    return cor ? (texto ? `${texto} | Cor: ${cor.nome}` : `Cor: ${cor.nome}`) : texto;
  }
  function imagemDaLinha(linha) {
    let img = linha.querySelector('.foto-produto-linha');
    if (!img) {
      img = document.createElement('img');
      img.className = 'foto-produto-linha';
      img.alt = 'Prévia ilustrativa';
      img.style.cssText = 'width:50px;height:50px;object-fit:cover;margin-right:10px;border:1px solid #ddd;border-radius:4px;vertical-align:middle;cursor:zoom-in';
      const celula = linha.querySelector('td');
      if (celula) celula.prepend(img);
    }
    if (!img.dataset.originalSrc) img.dataset.originalSrc = img.getAttribute('src') || '';
    return img;
  }
  function preservarImagemOriginal(linha) {
    const img = linha.querySelector('.foto-produto-linha');
    if (!img || img.dataset.originalSrc) return;
    const atual = img.getAttribute('src') || '';
    // A miniatura carregada pelo PHP é a referência do serviço. Preserve-a
    // antes de qualquer prévia gerada para uma cor; sem isso, a primeira
    // inicialização em “Cor a definir” removia a imagem original.
    if (atual && !atual.startsWith('data:image/')) img.dataset.originalSrc = atual;
  }
  function nomeContextual(linha, cor) {
    const nome = linha.querySelector('.nome_produto_display')?.value || 'Produto';
    if (!cor) return nome;
    const comCor = nome.replace(/cor\s+a\s+definir/iu, `Cor ${cor.nome}`);
    return comCor === nome ? `${nome} — ${cor.nome}` : comCor;
  }
  function restaurarImagem(linha) {
    const img = linha.querySelector('.foto-produto-linha');
    if (!img) return;
    preservarImagemOriginal(linha);
    const original = img.dataset.originalSrc || '';
    if (original) {
      img.src=original;
      img.dataset.fotoCompleta=original;
      img.dataset.nomeProduto=nomeContextual(linha,null);
    }
    else img.remove();
  }
  async function prepararLinha(linha, produtoId) {
    const dados = await obterDetalhes(produtoId);
    if (!linha.isConnected || Number(linha.querySelector('input[name="produto_id[]"]')?.value) !== produtoId || !dados.cores) return;
    preservarImagemOriginal(linha);
    const obs = linha.querySelector('.observacoes_item_input');
    if (!obs) return;
    let bloco = linha.querySelector('.mobel-forracao-cor');
    if (!bloco) {
      bloco = document.createElement('div');
      bloco.className = 'mobel-forracao-cor mt-2 p-2 border rounded bg-light';
      // O observador pode varrer a linha assim que o bloco entrar no DOM.
      bloco.dataset.produtoId = String(produtoId);
      const titulo = document.createElement('label');
      titulo.className = 'small font-weight-bold mb-1 d-block';
      titulo.textContent = 'Cor ilustrativa da forração (sem capa em estoque)';
      const select = document.createElement('select');
      select.className = 'form-control form-control-sm';
      select.append(new Option('Cor a definir — manter foto de serviço',''));
      for (const cor of dados.cores) select.append(new Option(cor.nome,String(cor.id)));
      const aviso = document.createElement('small');
      aviso.className = 'form-text text-muted mobel-forracao-aviso';
      aviso.textContent = dados.modelo ? 'Escolha uma cor para ver a simulação; o estoque continua sendo o da estrutura.' : 'A cor pode ser anotada, mas a foto aguarda o mestre aprovado deste produto.';
      bloco.append(titulo,select,aviso);
      obs.insertAdjacentElement('afterend',bloco);
      const eventos = new AbortController();
      bloco._desligar = () => eventos.abort();
      let versao = 0;
      const atualizar = async () => {
        const minhaVersao = ++versao;
        const cor = dados.cores.find(c => String(c.id) === select.value);
        if (!cor) { restaurarImagem(linha); aviso.textContent='Cor a definir: foto de serviço e estoque da estrutura.'; return; }
        if (!dados.modelo) {
          restaurarImagem(linha);
          const img = linha.querySelector('.foto-produto-linha');
          if (img) img.dataset.nomeProduto=`${nomeContextual(linha,cor)} (foto de referência; prévia indisponível)`;
          aviso.textContent='Cor anotada; falta aprovar o mestre para gerar a foto ilustrativa.';
          return;
        }
        aviso.textContent=`Montando prévia de ${cor.nome}…`;
        try {
          const imagem = await montar(Number(dados.modelo.id),cor);
          if (minhaVersao !== versao || !linha.isConnected
              || Number(linha.querySelector('input[name="produto_id[]"]')?.value) !== produtoId) return;
          const img = imagemDaLinha(linha);
          img.src=imagem;
          img.dataset.fotoCompleta=imagem;
          img.dataset.nomeProduto=nomeContextual(linha,cor);
          aviso.textContent=`Prévia ilustrativa de ${cor.nome}. Tecido sob demanda; a disponibilidade é da estrutura.`;
        } catch (erro) {
          if (minhaVersao !== versao) return;
          restaurarImagem(linha);
          aviso.textContent=`Não foi possível montar a prévia: ${erro.message}`;
        }
      };
      select.addEventListener('change',() => {
        const cor = dados.cores.find(c => String(c.id) === select.value) || null;
        obs.value = inserirObservacao(obs.value,cor,dados.cores);
        obs.style.display = obs.value ? '' : 'none';
        const rotulo = linha.querySelector('.observacoes_item_label');
        if (rotulo) rotulo.style.display = obs.value ? '' : 'none';
        obs.dispatchEvent(new Event('change',{bubbles:true}));
        atualizar();
      });
      obs.addEventListener('input',() => {
        const cor = corDaObservacao(obs.value,dados.cores);
        const valor = cor ? String(cor.id) : '';
        if (select.value !== valor) { select.value=valor; atualizar(); }
      }, {signal:eventos.signal});
      bloco._atualizar = atualizar;
    }
    const achada = corDaObservacao(obs.value,dados.cores);
    const select = bloco.querySelector('select');
    select.value = achada ? String(achada.id) : '';
    bloco._atualizar();
  }
  async function prepararImpressao(linha, produtoId) {
    linha.dataset.mobelForracaoEstado='carregando';
    try {
      const dados = await obterDetalhes(produtoId);
      const cor = corDaObservacao(linha.dataset.observacao || '',dados.cores || []);
      if (!cor || !dados.modelo) return;
      const imagem = await montar(Number(dados.modelo.id),cor);
      if (!linha.isConnected) return;
      let img = linha.querySelector('.produto-foto-impressao');
      if (!img) {
        img=document.createElement('img');
        img.className='produto-foto-impressao';
        const celula=linha.querySelector('.descricao-item');
        if (celula) celula.prepend(img);
      }
      img.src=imagem;
      img.alt=`Simulação ilustrativa de ${cor.nome}`;
      const aviso=document.createElement('small');
      aviso.className='mobel-forracao-legenda';
      aviso.textContent='Imagem ilustrativa da cor; tecido sob demanda.';
      linha.querySelector('.texto-produto-impressao')?.append(aviso);
    } catch (erro) {
      console.warn('Prévia de forração indisponível:',erro);
    } finally {
      linha.dataset.mobelForracaoEstado='pronto';
    }
  }
  async function iniciar() {
    let catalogo = null;
    let consultaCatalogo = null;
    let pendente = false;
    const varrer = () => {
      pendente=false;
      if (!catalogo) return;
      document.querySelectorAll('.item-pedido-row,.item-orcamento-row').forEach(linha => {
        const produtoId=Number(linha.querySelector('input[name="produto_id[]"]')?.value || 0);
        const bloco=linha.querySelector('.mobel-forracao-cor');
        if (bloco && Number(bloco.dataset.produtoId) !== produtoId) {
          bloco._desligar?.();
          bloco.remove();
          const img=linha.querySelector('.foto-produto-linha');
          if (img?.src.startsWith('data:image/png')) img.remove();
        }
        if (!catalogo.has(produtoId) || linha.querySelector('.mobel-forracao-cor')) return;
        prepararLinha(linha,produtoId).then(() => {
          const b=linha.querySelector('.mobel-forracao-cor');
          if (b) b.dataset.produtoId=String(produtoId);
        }).catch(erro => console.warn('Cores da forração: nova tentativa em instantes.', erro));
      });
      document.querySelectorAll('.mobel-forracao-impressao').forEach(linha => {
        const produtoId=Number(linha.dataset.produtoId || 0);
        if (catalogo.has(produtoId) && !linha.dataset.mobelForracaoEstado) prepararImpressao(linha,produtoId);
      });
    };
    const agendar = () => { if (!pendente) { pendente=true; window.setTimeout(varrer, 0); } };
    const carregarCatalogo = () => {
      if (catalogo || consultaCatalogo) return;
      consultaCatalogo = fetch(`${url}forracao_visual.php?catalogo=1`, {
        credentials:'same-origin',
        headers:{'X-Mobel-Color-Token': String(window.MOBEL_COLOR_CATALOG_TOKEN || '')}
      })
        .then(resposta => {
          if (!resposta.ok) throw new Error(`Catálogo de forração indisponível (${resposta.status}).`);
          return resposta.json();
        })
        .then(dados => {
          if (!Array.isArray(dados.produtos)) throw new Error('Catálogo de forração incompleto.');
          catalogo = new Set(dados.produtos.map(p => Number(p.produto_id)));
          agendar();
        })
        .catch(erro => console.warn('Catálogo de forração: nova tentativa em instantes.', erro))
        .finally(() => { consultaCatalogo = null; });
    };
    document.addEventListener('change', e => { if (e.target.matches('input[name="produto_id[]"]')) agendar(); });
    const seletorEstrutural='.item-pedido-row,.item-orcamento-row,.produto_id,.foto-produto-linha';
    const observador=new MutationObserver(registros=>{
      if(registros.some(r=>r.type==='attributes'
        ? r.target.matches('.produto_id')
        : [...r.addedNodes].some(n=>n.nodeType===1 &&
          (n.matches(seletorEstrutural)||n.querySelector('.produto_id'))))) agendar();
    });
    observador.observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['value']});
    document.addEventListener('focusin',e=>{
      if(e.target.matches('.observacoes_item_input')) agendar();
    });
    carregarCatalogo();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',iniciar);
  else iniciar();
  window.setTimeout(() => {
    if (typeof window.revalidarTodasAsLinhasDisponibilidade === 'function') window.revalidarTodasAsLinhasDisponibilidade();
  }, 950);
})();
