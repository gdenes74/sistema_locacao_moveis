(() => {
  'use strict';
  const form = document.getElementById('form-mestre');
  const input = document.getElementById('foto-mestre');
  const canvas = document.getElementById('canvas-mestre');
  if (!form || !input || !canvas) return;
  const contexto = canvas.getContext('2d');
  const estado = document.getElementById('estado-mestre');
  const salvar = document.getElementById('salvar-mestre');
  const pontos = [];
  const regioes = [];
  const fechar = document.getElementById('fechar-area');
  const desfazer = document.getElementById('desfazer-ponto');
  const limpar = document.getElementById('limpar-pontos');
  const nome = document.getElementById('nome');
  const familia = document.getElementById('familia');
  const amostra = document.getElementById('mestre-amostra');
  const verPrevia = document.getElementById('mestre-ver-previa');
  const previa = document.getElementById('canvas-mestre-previa');
  const previaEstado = document.getElementById('mestre-previa-estado');
  const referencia = document.getElementById('mestre-referencia');
  const baixar = document.getElementById('mestre-baixar-previa');
  let versao = 0;
  let imagem = null;

  const filtros = ['mestre-secao','mestre-categoria','mestre-subcategoria'].map(id => document.getElementById(id));
  const produto = document.getElementById('mestre-produto');
  const infoProduto = document.getElementById('mestre-produto-info');
  const catalogo = window.MOBEL_MESTRE_CATALOGO || [];
  const taxonomia = window.MOBEL_MESTRE_TAXONOMIA || catalogo;
  const chave = valor => String(valor || '').trim().toLocaleLowerCase('pt-BR');
  const corresponde = (produtoCatalogo, campo, valor) => !valor || chave(produtoCatalogo[campo + '_nome']) === valor;
  function preencherNomes(el, vazio, lista, campo, atual) {
    if (!el) return;
    const vistos = new Map();
    for (const item of lista) {
      const rotulo = String(item[campo + '_nome'] || '').trim();
      if (rotulo) vistos.set(chave(rotulo), rotulo);
    }
    el.replaceChildren(new Option(vazio, ''));
    [...vistos.entries()].sort((a,b) => a[1].localeCompare(b[1], 'pt-BR')).forEach(([valor,rotulo]) => el.add(new Option(rotulo,valor)));
    el.value = [...el.options].some(o => o.value === atual) ? atual : '';
  }
  function atualizarCascata() {
    const secao = filtros[0]?.value || '', categoriaAtual = filtros[1]?.value || '', subAtual = filtros[2]?.value || '';
    preencherNomes(filtros[0], 'Todas as seções', taxonomia, 'secao', secao);
    const taxonomiaSecao = taxonomia.filter(p => corresponde(p, 'secao', filtros[0]?.value || ''));
    preencherNomes(filtros[1], 'Todas as categorias', taxonomiaSecao, 'categoria', categoriaAtual);
    const categoria = filtros[1]?.value || '';
    const taxonomiaCategoria = taxonomiaSecao.filter(p => corresponde(p, 'categoria', categoria));
    preencherNomes(filtros[2], 'Todas as subcategorias', taxonomiaCategoria, 'subcategoria', subAtual);
    const subcategoria = filtros[2]?.value || '';
    const produtosFiltrados = catalogo.filter(p => corresponde(p, 'secao', filtros[0]?.value || '') && corresponde(p, 'categoria', categoria) && corresponde(p, 'subcategoria', subcategoria));
    if (produto) {
      const atual = produto.value; produto.replaceChildren(new Option('Escolha o produto',''));
      for (const p of produtosFiltrados) {
        produto.add(new Option(`${p.nome_produto}${p.cor ? ' — ' + p.cor : ''}`, String(p.produto_id)));
      }
      if ([...produto.options].some(o => o.value === atual)) produto.value = atual;
      if (infoProduto && !produto.value) infoProduto.textContent = produtosFiltrados.length
        ? `${produtosFiltrados.length} produto(s) montado(s) encontrado(s). Escolha um deles.`
        : 'Não há produto montado nesta classificação. Escolha outra categoria/subcategoria ou cadastre primeiro o produto montado.';
    }
    desenhar();
  }
  filtros.filter(Boolean).forEach(el => el.addEventListener('change', atualizarCascata));
  function nomeEstrutural(p) {
    let resultado = String(p?.nome_produto || '').trim();
    const cor = String(p?.cor || '').trim();
    if (cor && cor.toLocaleLowerCase('pt-BR') !== 'a definir') {
      const escapada = cor.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
      resultado = resultado.replace(new RegExp(`\\s+(?:cor\\s+)?${escapada}$`, 'i'), '');
    }
    return resultado.replace(/\s+Cor\s+A\s+Definir$/i, '').trim();
  }
  function selecionarFamilia(p) {
    if (!familia || !p) return;
    const candidata = [...familia.options].find(o => {
      if(o.dataset.produtoGenerico === String(p.produto_id))return true;
      try{return JSON.parse(o.dataset.produtos || '[]').map(String).includes(String(p.produto_id));}catch(_){return false;}
    });
    // A família é uma consequência da composição do produto. Quando ainda
    // não existe uma configuração persistida, mostre isso explicitamente na
    // tela em vez de deixar um campo vazio que pareça erro de cadastro.
    const automatica = familia.querySelector('option[data-automatica="1"]');
    if (candidata) {
      if (automatica) automatica.remove();
      familia.value = candidata.value;
    } else {
      if (automatica) automatica.remove();
      const temComposicao = String(p.composicao_resumo || '').trim() !== '';
      const opcao = new Option(
        temComposicao
          ? 'Automática — será criada a partir da composição'
          : 'Automática — aguardando composição do produto',
        '__AUTO__'
      );
      opcao.dataset.automatica = '1';
      familia.add(opcao, 0);
      familia.value = '__AUTO__';
    }
    atualizarReferenciaFamilia();
    const link=document.getElementById('configurar-mestre-produto');
    if(link)link.href='configurar_motor.php?produto='+encodeURIComponent(p.produto_id);
  }
  function atualizarReferenciaFamilia() {
    const id = familia?.value || '';
    for (const opcao of [...amostra.options].slice(1)) {
      let porFamilia = {};
      try { porFamilia = JSON.parse(opcao.dataset.familias || '{}'); } catch (_) { /* referência global/fallback */ }
      opcao.value = String(porFamilia[id] || opcao.dataset.global || opcao.dataset.fallback || '');
    }
    amostra.dispatchEvent(new Event('change'));
  }
  familia?.addEventListener('change', atualizarReferenciaFamilia);
  produto?.addEventListener('change', () => {
    const p = catalogo.find(x => String(x.produto_id) === produto.value);
    if (nome) nome.value = p ? nomeEstrutural(p) : '';
    selecionarFamilia(p);
    if (infoProduto) infoProduto.textContent = p ? `Produto #${p.produto_id}: ${p.nome_produto}. Agora envie a foto preparada.` : 'Os filtros mostram somente produtos montados que podem receber uma foto mestre.';
    desenhar();
  });
  atualizarCascata();
  if(window.MOBEL_MESTRE_PRESELECAO?.produto && produto){
    produto.value=String(window.MOBEL_MESTRE_PRESELECAO.produto);
    produto.dispatchEvent(new Event('change'));
    if(window.MOBEL_MESTRE_PRESELECAO.familia && familia){familia.value=String(window.MOBEL_MESTRE_PRESELECAO.familia);atualizarReferenciaFamilia();}
  }

  function desenhar() {
    versao++;
    previa.hidden = true;
    baixar.hidden = true;
    verPrevia.disabled = !imagem || !amostra.value || (pontos.length > 0 && pontos.length < 3) || (!regioes.length && pontos.length < 3);
    previaEstado.textContent = !imagem ? 'Envie uma foto preparada do computador.'
      : (pontos.length > 0 && pontos.length < 3) ? 'Complete ou desfaça a área que está marcando.'
      : !regioes.length && pontos.length < 3 ? 'Marque o tecido que poderá mudar de cor.'
      : !amostra.value ? 'Escolha uma referência de cor aprovada.'
      : 'Marcação pronta. Clique em “Ver prévia com esta cor”.';
    fechar.disabled = !imagem || pontos.length < 3 || regioes.length >= 19;
    desfazer.disabled = !imagem || (!pontos.length && !regioes.length);
    limpar.disabled = !imagem || (!pontos.length && !regioes.length);
    contexto.clearRect(0, 0, 800, 800);
    if (!imagem) { salvar.disabled = true; return; }
    contexto.drawImage(imagem, 0, 0, 800, 800);
    for (const regiao of regioes) {
      contexto.beginPath();
      contexto.moveTo(...regiao[0]);
      for (const ponto of regiao.slice(1)) contexto.lineTo(...ponto);
      contexto.closePath();
      contexto.fillStyle = 'rgba(0, 160, 255, .25)';
      contexto.fill();
      contexto.strokeStyle = '#0056b3';
      contexto.lineWidth = 3;
      contexto.stroke();
    }
    if (pontos.length) {
      contexto.beginPath();
      contexto.moveTo(...pontos[0]);
      for (const ponto of pontos.slice(1)) contexto.lineTo(...ponto);
      if (pontos.length > 2) contexto.closePath();
      contexto.fillStyle = 'rgba(0, 160, 255, .25)';
      if (pontos.length > 2) contexto.fill();
      contexto.strokeStyle = '#0056b3';
      contexto.lineWidth = 3;
      contexto.stroke();
      for (const [x, y] of pontos) {
        contexto.beginPath();
        contexto.arc(x, y, 7, 0, Math.PI * 2);
        contexto.fillStyle = '#e3342f';
        contexto.fill();
        contexto.strokeStyle = '#fff';
        contexto.lineWidth = 2;
        contexto.stroke();
      }
    }
    // A família pode ainda não existir: o servidor a deriva da composição no
    // primeiro salvamento. O usuário não deve ficar bloqueado por esse detalhe.
    salvar.disabled = !imagem || !produto?.value || !nome?.value || (pontos.length > 0 && pontos.length < 3);
    estado.textContent = !produto?.value
      ? 'Selecione o produto ao qual esta foto pertence.'
      : !familia?.value
        ? 'Produto selecionado. A família/regra visual será criada automaticamente ao salvar, desde que a composição esteja cadastrada.'
      : pontos.length === 0
        ? regioes.length ? `${regioes.length} área(s) de tecido fechada(s). Escolha uma cor para testar.` : 'Foto carregada. Marque o tecido para testar uma cor ou guarde a foto para revisão.'
        : pontos.length < 3
          ? `Complete o contorno do tecido (${pontos.length}/3 pontos mínimos) ou limpe os pontos.`
          : `${pontos.length} pontos marcados. Confira se só o tecido ficou selecionado.`;
  }

  function prepararImagem(nova) {
    if (Math.max(nova.naturalWidth, nova.naturalHeight) < 800) {
      estado.textContent = 'Escolha uma foto com pelo menos 800 pixels no lado maior.';
      input.value = '';
      return;
    }
    imagem = document.createElement('canvas');
    imagem.width = imagem.height = 800;
    const g = imagem.getContext('2d');
    g.fillStyle = '#fff';
    g.fillRect(0, 0, 800, 800);
    const escala = Math.min(800 / nova.naturalWidth, 800 / nova.naturalHeight);
    const largura = nova.naturalWidth * escala, altura = nova.naturalHeight * escala;
    g.drawImage(nova, (800 - largura) / 2, (800 - altura) / 2, largura, altura);
    desenhar();
  }

  input.addEventListener('change', () => {
    imagem = null;
    pontos.length = 0;
    regioes.length = 0;
    desenhar();
    salvar.disabled = true;
    const arquivo = input.files?.[0];
    if (!arquivo) return;
    if (!['image/png', 'image/jpeg'].includes(arquivo.type) || arquivo.size > 8 * 1024 * 1024) {
      estado.textContent = 'Escolha uma foto PNG ou JPG de até 8 MB.';
      input.value = '';
      return;
    }
    const endereco = URL.createObjectURL(arquivo);
    const nova = new Image();
    nova.onload = () => {
      URL.revokeObjectURL(endereco);
      prepararImagem(nova);
    };
    nova.onerror = () => { URL.revokeObjectURL(endereco); estado.textContent = 'Não foi possível abrir a foto.'; };
    nova.src = endereco;
  });

  const existente = window.MOBEL_MESTRE_EXISTENTE;
  if (existente?.baseUrl) {
    const fotoExistente = new Image();
    fotoExistente.onload = () => {
      prepararImagem(fotoExistente);
      regioes.length = 0;
      for (const regiao of (existente.regioes || [])) {
        if (Array.isArray(regiao) && regiao.length >= 3) regioes.push(regiao);
      }
      if (produto) {
        produto.value = String(existente.produtoId || '');
        produto.dispatchEvent(new Event('change'));
      }
      if (familia && existente.familiaId) familia.value = String(existente.familiaId);
      if (nome && existente.nome) nome.value = existente.nome;
      desenhar();
      estado.textContent = 'Marcação atual carregada. Use “Recomeçar marcação” e contorne assento e encosto separadamente para corrigir o recorte.';
    };
    fotoExistente.onerror = () => { estado.textContent = 'Não foi possível carregar a foto-base deste mestre.'; };
    fotoExistente.src = existente.baseUrl;
  }

  /* pointerdown funciona tanto no mouse quanto em telas sensíveis ao toque.
     Alguns navegadores deixam de emitir click no canvas após selecionar um
     arquivo local; por isso a marcação é feita já no primeiro toque. */
  canvas.style.touchAction = 'none';
  canvas.addEventListener('pointerdown', evento => {
    evento.preventDefault();
    if (!imagem || pontos.length >= 100) return;
    const retangulo = canvas.getBoundingClientRect();
    const x = Math.max(0, Math.min(800, Math.round((evento.clientX - retangulo.left) * 800 / retangulo.width)));
    const y = Math.max(0, Math.min(800, Math.round((evento.clientY - retangulo.top) * 800 / retangulo.height)));
    pontos.push([x, y]);
    desenhar();
  });
  desfazer.addEventListener('click', () => {
    if (pontos.length) pontos.pop();
    else if (regioes.length) pontos.push(...regioes.pop());
    desenhar();
  });
  limpar.addEventListener('click', () => { pontos.length = 0; regioes.length = 0; desenhar(); });
  fechar.addEventListener('click', () => {
    if (pontos.length < 3 || regioes.length >= 19) return;
    regioes.push(pontos.splice(0));
    desenhar();
  });

  function areasAtuais() { return pontos.length >= 3 ? [...regioes, pontos] : regioes; }
  function criarMascara() {
    const mascara = document.createElement('canvas');
    mascara.width = mascara.height = 800;
    const g = mascara.getContext('2d');
    g.fillStyle = '#000';
    g.fillRect(0, 0, 800, 800);
    g.fillStyle = '#fff';
    for (const regiao of areasAtuais()) {
      g.beginPath();
      g.moveTo(...regiao[0]);
      for (const ponto of regiao.slice(1)) g.lineTo(...ponto);
      g.closePath();
      g.fill();
    }
    return mascara;
  }
  amostra.addEventListener('change', () => {
    referencia.hidden = !amostra.value;
    if (amostra.value) referencia.src = `amostra_imagem.php?id=${encodeURIComponent(amostra.value)}`;
    desenhar();
  });
  verPrevia.addEventListener('click', async () => {
    if (verPrevia.disabled) return;
    const tentativa = ++versao;
    verPrevia.disabled = true;
    previaEstado.textContent = 'Aplicando a referência somente no tecido marcado…';
    try {
      const fotoCor = new Image();
      const carregou = new Promise((resolve, reject) => {
        fotoCor.onload = resolve;
        fotoCor.onerror = () => reject(new Error('Não foi possível carregar a referência aprovada.'));
      });
      fotoCor.src = `amostra_imagem.php?id=${encodeURIComponent(amostra.value)}`;
      await carregou;
      if (tentativa !== versao) return;
      const c = document.createElement('canvas');
      c.width = fotoCor.naturalWidth;
      c.height = fotoCor.naturalHeight;
      const g = c.getContext('2d', {willReadFrequently:true});
      g.drawImage(fotoCor, 0, 0);
      const alvo = window.MOBEL_RECOLORACAO.extrairTom(g.getImageData(0, 0, c.width, c.height).data, c.width, c.height);
      const ctx = previa.getContext('2d', {willReadFrequently:true});
      ctx.drawImage(imagem, 0, 0);
      const dados = ctx.getImageData(0, 0, 800, 800);
      const mascara = criarMascara().getContext('2d').getImageData(0, 0, 800, 800);
      window.MOBEL_RECOLORACAO.aplicar(dados.data, mascara.data, alvo);
      ctx.putImageData(dados, 0, 0);
      previa.hidden = false;
      baixar.href = previa.toDataURL('image/png');
      baixar.hidden = false;
      previaEstado.textContent = `Prévia de ${amostra.selectedOptions[0].textContent}. Confira o tecido e os limites da marcação. Nenhuma foto foi publicada.`;
    } catch (erro) { previaEstado.textContent = erro.message; }
    finally { if (tentativa === versao) verPrevia.disabled = false; }
  });

  form.addEventListener('submit', evento => {
    if (!imagem || !produto?.value || (pontos.length > 0 && pontos.length < 3)) { evento.preventDefault(); return; }
    const base = document.createElement('canvas');
    base.width = base.height = 800;
    base.getContext('2d').drawImage(imagem, 0, 0, 800, 800);
    document.getElementById('base-png').value = base.toDataURL('image/png');
    if (areasAtuais().length) {
      document.getElementById('mascara-png').value = criarMascara().toDataURL('image/png');
    } else {
      document.getElementById('mascara-png').value = '';
    }
    document.getElementById('pontos').value = JSON.stringify(areasAtuais()[0] || []);
    document.getElementById('regioes').value = JSON.stringify(areasAtuais());
    salvar.disabled = true;
    estado.textContent = 'Salvando o modelo mestre…';
  });
})();
