(() => {
  'use strict';

  const corSelect = document.getElementById('motor-cor');
  const modeloSelect = document.getElementById('motor-modelo');
  const produtoSelect = document.getElementById('motor-produto');
  const buscaProduto = document.getElementById('motor-busca');
  const produtoInfo = document.getElementById('motor-produto-info');
  const criarMestre = document.getElementById('criar-mestre-produto');
  const filtros = ['motor-secao','motor-categoria','motor-subcategoria'].map(id => document.getElementById(id));
  const gerarBotao = document.getElementById('motor-gerar-lote');
  const resultados = document.getElementById('motor-resultados');
  const estadoGeral = document.getElementById('motor-estado');
  const salvarLote = document.getElementById('motor-salvar-lote');
  if (!corSelect || !gerarBotao || !resultados || !estadoGeral || !salvarLote || !produtoSelect) return;

  const catalogo = window.MOBEL_PREVIAS || [];
  const catalogoReal = window.MOBEL_CATALOGO || [];
  const cache = new Map();
  const opcoesProdutosOriginais = [...produtoSelect.options].slice(1).map(o => ({value:o.value,text:o.text,secaoId:o.dataset.secaoId||'',categoriaId:o.dataset.categoriaId||'',subcategoriaId:o.dataset.subcategoriaId||'',secao:o.dataset.secao||'',categoria:o.dataset.categoria||'',subcategoria:o.dataset.subcategoria||''}));
  let cartoesAtuais = [];

  function atualizarCores() {
    resultados.replaceChildren();
    cartoesAtuais = [];
    salvarLote.hidden = true;
    salvarLote.disabled = true;
    corSelect.replaceChildren();
    const modeloId = Number(modeloSelect.value || 0);
    const produtoId = Number(produtoSelect.value || 0);
    corSelect.add(new Option(modeloId ? 'Escolha uma cor deste modelo' : 'Escolha primeiro um modelo', ''));
    const cores = new Map();
    for (const item of catalogo) {
      if (Number(item.modelo_id) === modeloId && (!produtoId || Number(item.produto_id) === produtoId)) cores.set(Number(item.cor_id), item.cor_nome);
    }
    for (const [id, nome] of [...cores].sort((a, b) => a[1].localeCompare(b[1], 'pt-BR'))) {
      corSelect.add(new Option(nome, String(id)));
    }
    corSelect.disabled = !modeloId || cores.size === 0;
    gerarBotao.disabled = !modeloId || cores.size === 0;
    estadoGeral.textContent = !modeloId ? 'Escolha um modelo mestre para ver suas cores.'
      : cores.size ? `${cores.size} ${cores.size === 1 ? 'cor pronta' : 'cores prontas'} para este modelo. Escolha uma cor.`
      : 'Este modelo ainda não tem cor com capa física e referência digital pronta. Veja “Preparar cores e produtos”.';
  }

  function opcoesUnicas(campo, filtrosAtuais) {
    const valores = new Set();
    for (const item of opcoesProdutosOriginais) {
      if (filtrosAtuais.secao && item.secaoId !== filtrosAtuais.secao) continue;
      if (filtrosAtuais.categoria && item.categoriaId !== filtrosAtuais.categoria) continue;
      if (filtrosAtuais.subcategoria && item.subcategoriaId !== filtrosAtuais.subcategoria) continue;
      const id = item[campo + 'Id'];
      const nome = item[campo];
      if (id && nome) valores.add(`${id}||${nome}`);
    }
    return [...valores].sort((a,b) => a.split('||')[1].localeCompare(b.split('||')[1], 'pt-BR'));
  }

  function preencherFiltro(el, placeholder, opcoes, valorAtual) {
    el.replaceChildren(new Option(placeholder, ''));
    for (const entrada of opcoes) { const [id,nome] = entrada.split('||'); el.add(new Option(nome, id)); }
    if ([...el.options].some(o => o.value === valorAtual)) el.value = valorAtual;
    else el.value = '';
  }

  function atualizarFiltrosECatalogo() {
    const secao = filtros[0] ? filtros[0].value : '';
    const categoriaAtual = filtros[1] ? filtros[1].value : '';
    const subAtual = filtros[2] ? filtros[2].value : '';
    const base = {secao, categoria:'', subcategoria:''};
    if (filtros[1]) preencherFiltro(filtros[1], 'Todas as categorias', opcoesUnicas('categoria', base), categoriaAtual);
    const categoria = filtros[1] ? filtros[1].value : '';
    if (filtros[2]) preencherFiltro(filtros[2], 'Todas as subcategorias', opcoesUnicas('subcategoria', {secao, categoria, subcategoria:''}), subAtual);
    const subcategoria = filtros[2] ? filtros[2].value : '';
    const atual = produtoSelect.value;
    produtoSelect.replaceChildren(new Option('Escolha um produto', ''));
    for (const item of opcoesProdutosOriginais) {
      if (secao && item.secaoId !== secao) continue;
      if (categoria && item.categoriaId !== categoria) continue;
      if (subcategoria && item.subcategoriaId !== subcategoria) continue;
      if (buscaProduto && buscaProduto.value.trim()) { const termo = buscaProduto.value.trim().toLocaleLowerCase('pt-BR'); if (!item.text.toLocaleLowerCase('pt-BR').includes(termo) && !item.value.includes(termo)) continue; }
      const option = new Option(item.text, item.value);
      option.dataset.secaoId=item.secaoId; option.dataset.categoriaId=item.categoriaId; option.dataset.subcategoriaId=item.subcategoriaId;
      option.dataset.secao=item.secao; option.dataset.categoria=item.categoria; option.dataset.subcategoria=item.subcategoria;
      produtoSelect.add(option);
    }
    if ([...produtoSelect.options].some(o => o.value === atual)) produtoSelect.value = atual;
  }

  function carregar(src) {
    if (!cache.has(src)) cache.set(src, new Promise((resolve, reject) => {
      const img = new Image();
      img.onload = () => resolve(img);
      img.onerror = () => reject(new Error('Arquivo de imagem indisponível.'));
      img.src = src;
    }));
    return cache.get(src);
  }

  function dentroDoAssento(x, y, w, h) {
    // Recorte conferido para o mestre Thai v2; a madeira não é recolorida.
    const px = x / w, py = y / h;
    const vertices = [[.115,.317],[.505,.213],[.881,.309],[.895,.372],[.514,.557],[.105,.391]];
    let dentro = false;
    for (let i = 0, j = vertices.length - 1; i < vertices.length; j = i++) {
      const [xi, yi] = vertices[i], [xj, yj] = vertices[j];
      if ((yi > py) !== (yj > py) && px < (xj - xi) * (py - yi) / (yj - yi) + xi) dentro = !dentro;
    }
    return dentro;
  }

  function tecido(r, g, b, a, x, y, w, h, tipo, mascara, indice) {
    if (a < 180) return false;
    if (mascara) {
      const d = mascara.data;
      return d[indice + 3] > 127 && (.2126*d[indice] + .7152*d[indice + 1] + .0722*d[indice + 2]) > 127;
    }
    if (tipo === 'thai') {
      const maior = Math.max(r, g, b), menor = Math.min(r, g, b);
      return dentroDoAssento(x, y, w, h) && maior < 145 && maior - menor < 42 && b >= r * .84;
    }
    return tipo === 'pequeno' && b > r * 1.07 && b > g * .94 && b > 85;
  }

  function corDaAmostra(img) {
    const canvas = document.createElement('canvas');
    canvas.width = img.naturalWidth;
    canvas.height = img.naturalHeight;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0);
    const d = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    if (window.MOBEL_RECOLORACAO) return window.MOBEL_RECOLORACAO.extrairTom(d, canvas.width, canvas.height);
    const soma = [0, 0, 0];
    let n = 0;
    for (let y = Math.floor(canvas.height * .42); y < canvas.height * .68; y += 7) {
      for (let x = Math.floor(canvas.width * .36); x < canvas.width * .64; x += 7) {
        const i = (y * canvas.width + x) * 4;
        if (d[i + 3] > 180 && Math.min(d[i], d[i + 1], d[i + 2]) < 225) {
          for (let k = 0; k < 3; k++) soma[k] += d[i + k];
          n++;
        }
      }
    }
    if (n < 20) throw new Error('A referência não oferece área suficiente para extrair a cor.');
    return soma.map(v => v / n);
  }

  function corDaAreaMarcada(img, area) {
    if (!Array.isArray(area) || area.length !== 4) return corDaAmostra(img);
    const canvas = document.createElement('canvas');
    canvas.width = img.naturalWidth;
    canvas.height = img.naturalHeight;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(img, 0, 0);
    const [nx, ny, nw, nh] = area.map(Number);
    const x = Math.max(0, Math.min(canvas.width - 1, Math.floor(nx * canvas.width)));
    const y = Math.max(0, Math.min(canvas.height - 1, Math.floor(ny * canvas.height)));
    const w = Math.max(1, Math.min(canvas.width - x, Math.round(nw * canvas.width)));
    const h = Math.max(1, Math.min(canvas.height - y, Math.round(nh * canvas.height)));
    const dados = ctx.getImageData(x, y, w, h).data;
    if (window.MOBEL_RECOLORACAO) return window.MOBEL_RECOLORACAO.extrairTom(dados, w, h);
    const soma = [0, 0, 0]; let n = 0;
    for (let i = 0; i < dados.length; i += 4) {
      if (dados[i + 3] < 180) continue;
      soma[0] += dados[i]; soma[1] += dados[i + 1]; soma[2] += dados[i + 2]; n++;
    }
    if (!n) throw new Error('A área marcada não contém pixels utilizáveis. Marque somente o tecido.');
    return soma.map(v => v / n);
  }

  async function montar(item, canvas) {
    const fontes = [
      carregar(`modelo_base.php?id=${encodeURIComponent(item.modelo_id)}`),
      carregar(`amostra_imagem.php?id=${encodeURIComponent(item.amostra_id)}`)
    ];
    if (item.modelo === 'mascara') {
      fontes.push(carregar(`modelo_base.php?id=${encodeURIComponent(item.modelo_id)}&parte=mascara`));
    }
    const [base, amostra, imagemMascara] = await Promise.all(fontes);
    canvas.width = base.naturalWidth;
    canvas.height = base.naturalHeight;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    ctx.drawImage(base, 0, 0);
    const imagem = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const d = imagem.data;
    let mascara = null;
    if (imagemMascara) {
      if (imagemMascara.naturalWidth !== canvas.width || imagemMascara.naturalHeight !== canvas.height) {
        throw new Error('A máscara não tem o tamanho do modelo mestre.');
      }
      const c = document.createElement('canvas');
      c.width = canvas.width;
      c.height = canvas.height;
      const g = c.getContext('2d', { willReadFrequently: true });
      g.drawImage(imagemMascara, 0, 0);
      mascara = g.getImageData(0, 0, c.width, c.height);
    }
    let alvo;
    if (item.area_referencia) {
      alvo = corDaAreaMarcada(amostra, item.area_referencia);
    } else if (mascara && item.amostra_mesmo_mestre && window.MOBEL_RECOLORACAO?.extrairTomComMascara) {
      const cAmostra = document.createElement('canvas');
      cAmostra.width = canvas.width; cAmostra.height = canvas.height;
      const gAmostra = cAmostra.getContext('2d', { willReadFrequently: true });
      // A referência foi declarada como o próprio produto que originou este
      // mestre. A base já está no enquadramento exato da máscara; usá-la evita
      // deformar uma foto retangular e contaminar o tom com madeira/fundo.
      gAmostra.drawImage(base, 0, 0, canvas.width, canvas.height);
      alvo = window.MOBEL_RECOLORACAO.extrairTomComMascara(gAmostra.getImageData(0, 0, canvas.width, canvas.height).data, mascara.data);
    } else {
      alvo = corDaAmostra(amostra);
    }
    if (mascara && window.MOBEL_RECOLORACAO) {
      const alterados = window.MOBEL_RECOLORACAO.aplicar(d, mascara.data, alvo);
      ctx.putImageData(imagem, 0, 0);
      return alterados;
    }
    let brilhoBase = 0, n = 0;
    for (let y = 0; y < canvas.height; y += 6) {
      for (let x = 0; x < canvas.width; x += 6) {
        const i = (y * canvas.width + x) * 4;
        if (tecido(d[i], d[i+1], d[i+2], d[i+3], x, y, canvas.width, canvas.height, item.modelo, mascara, i)) {
          brilhoBase += .2126*d[i] + .7152*d[i+1] + .0722*d[i+2];
          n++;
        }
      }
    }
    if (n < 100) throw new Error('A área de tecido do mestre não foi reconhecida.');
    brilhoBase /= n;
    let alterados = 0;
    for (let y = 0; y < canvas.height; y++) {
      for (let x = 0; x < canvas.width; x++) {
        const i = (y * canvas.width + x) * 4;
        if (!tecido(d[i], d[i+1], d[i+2], d[i+3], x, y, canvas.width, canvas.height, item.modelo, mascara, i)) continue;
        const brilho = .2126*d[i] + .7152*d[i+1] + .0722*d[i+2];
        const fator = Math.max(.38, Math.min(1.65, brilho / brilhoBase));
        for (let k = 0; k < 3; k++) d[i+k] = Math.min(255, Math.round(alvo[k] * fator));
        alterados++;
      }
    }
    ctx.putImageData(imagem, 0, 0);
    return alterados;
  }

  function criarCartao(item) {
    const semFoto = !item.foto_path || item.foto_path === 'assets/uploads/produtos/logo.jpg';
    const coluna = document.createElement('div');
    coluna.className = 'col-md-6 col-xl-4 mb-3';
    const card = document.createElement('div');
    card.className = 'card h-100';
    const corpo = document.createElement('div');
    corpo.className = 'card-body';
    const titulo = document.createElement('h5');
    titulo.textContent = item.nome_produto;
    const subtitulo = document.createElement('p');
    subtitulo.className = 'small text-muted';
    subtitulo.textContent = `${item.modelo_nome} · composição (produto composto sem estoque próprio): ${item.composicao_resumo || 'não informada'}`;
    if (item.somente_visual) {
      subtitulo.textContent += ' · FOTO VISUAL: estoque zero até todas as capas terem quantidade física.';
      subtitulo.classList.remove('text-muted');
      subtitulo.classList.add('text-warning');
    }
    const canvas = document.createElement('canvas');
    canvas.className = 'img-fluid border rounded';
    canvas.style.background = '#fff';
    const estado = document.createElement('p');
    estado.className = 'small mt-2';
    estado.textContent = 'Montando prévia…';
    const produto = document.createElement('a');
    produto.href = `show.php?id=${encodeURIComponent(item.produto_id)}`;
    produto.target = '_blank';
    produto.rel = 'noopener';
    produto.textContent = 'Ver produto';
    const aprovar = document.createElement('button');
    aprovar.type = 'button';
    aprovar.className = 'btn btn-outline-primary btn-sm ml-2';
    aprovar.textContent = 'Aprovar esta prévia';
    aprovar.disabled = true;
    const publicarBotao = document.createElement('button');
    publicarBotao.type = 'button';
    publicarBotao.className = 'btn btn-success btn-sm ml-2';
    publicarBotao.textContent = semFoto ? 'Publicar no produto' : 'Substituir foto no produto';
    publicarBotao.disabled = true;
    corpo.append(titulo, subtitulo, canvas, estado, produto, aprovar, publicarBotao);
    card.append(corpo);
    coluna.append(card);
    resultados.append(coluna);
    return { item, canvas, estado, aprovar, publicarBotao, pronto: false, aprovado: false, salvo: false, lote: semFoto };
  }

  async function aprovarPrevia(cartao) {
    const { item, canvas, estado, aprovar: botao } = cartao;
    if (!cartao.pronto || cartao.aprovado) return;
    botao.disabled = true;
    estado.textContent = 'Registrando aprovação da prévia…';
    try {
      const exportar = document.createElement('canvas');
      exportar.width = 800;
      exportar.height = 800;
      exportar.getContext('2d').drawImage(canvas, 0, 0, 800, 800);
      const resposta = await fetch('aprovar_previa_motor.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.MOBEL_MOTOR_CSRF || '' },
        body: JSON.stringify({
          variante_imagem_id: item.variante_imagem_id,
          modelo_id: item.modelo_id,
          produto_id: item.produto_id,
          cor_id: item.cor_id,
          amostra_id: item.amostra_id,
          imagem_data: exportar.toDataURL('image/png')
        })
      });
      const dados = await resposta.json();
      if (!resposta.ok || !dados.ok) throw new Error(dados.erro || 'Não foi possível aprovar a prévia.');
      item.variante_imagem_id = dados.variante_imagem_id;
      cartao.aprovado = true;
      cartao.publicarBotao.disabled = false;
      botao.textContent = 'Prévia aprovada';
      estado.textContent = 'Prévia aprovada e registrada. O produto ainda mantém a foto atual.';
      salvarLote.hidden = !cartoesAtuais.some(c => c.aprovado && !c.salvo && c.lote);
      salvarLote.disabled = salvarLote.hidden;
    } catch (erro) {
      estado.textContent = erro.message;
      botao.disabled = false;
    }
  }

  async function publicar(cartao) {
    const { item, estado, publicarBotao: botao } = cartao;
    if (!cartao.aprovado || cartao.salvo) return cartao.salvo;
    botao.disabled = true;
    estado.textContent = 'Publicando foto aprovada no produto…';
    try {
      const resposta = await fetch('publicar_foto_motor.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.MOBEL_MOTOR_CSRF || '' },
        body: JSON.stringify({ variante_imagem_id: item.variante_imagem_id })
      });
      const dados = await resposta.json();
      if (!resposta.ok || !dados.ok) throw new Error(dados.erro || 'Não foi possível publicar a foto.');
      cartao.salvo = true;
      estado.textContent = 'Foto publicada no produto, com histórico da troca. A versão anterior foi preservada ou arquivada.';
      botao.textContent = 'Foto publicada';
      return true;
    } catch (erro) {
      estado.textContent = erro.message;
      botao.disabled = false;
      return false;
    }
  }

  async function gerarLote() {
    const corId = Number(corSelect.value);
    resultados.replaceChildren();
    cartoesAtuais = [];
    salvarLote.hidden = true;
    salvarLote.disabled = true;
    const modeloId = Number(modeloSelect.value || 0);
    const produtoId = Number(produtoSelect.value || 0);
    const itens = catalogo.filter(item => Number(item.cor_id) === corId && Number(item.modelo_id) === modeloId && (!produtoId || Number(item.produto_id) === produtoId));
    if (!itens.length) {
      estadoGeral.textContent = 'Não há foto pronta para esta combinação de modelo e cor. Confira as cores disponíveis acima.';
      return;
    }
    gerarBotao.disabled = true;
    corSelect.disabled = true;
    if (modeloSelect) modeloSelect.disabled = true;
    estadoGeral.textContent = `Montando ${itens.length} ${itens.length === 1 ? 'foto' : 'fotos'}…`;
    let prontas = 0;
    for (const item of itens) {
      const cartao = criarCartao(item);
      cartoesAtuais.push(cartao);
      try {
        await montar(item, cartao.canvas);
        const jaAprovada = item.variante_imagem_id && item.status_publicacao === 'APROVADA';
        cartao.estado.textContent = jaAprovada
          ? (item.foto_atual_publicada
              ? 'O produto já tem foto publicada. Há uma nova prévia aprovada; substitua a foto apenas se preferir esta versão.'
              : 'Esta prévia já foi aprovada e está aguardando somente a publicação no produto.')
          : 'Prévia pronta. Confira forma e tom; aprovar não substitui a foto do produto.';
        cartao.pronto = true;
        cartao.aprovado = Boolean(jaAprovada);
        cartao.aprovar.disabled = Boolean(jaAprovada);
        if (jaAprovada) cartao.aprovar.textContent = 'Prévia já aprovada';
        cartao.publicarBotao.disabled = !jaAprovada;
        cartao.aprovar.addEventListener('click', () => aprovarPrevia(cartao));
        cartao.publicarBotao.addEventListener('click', async () => {
          await publicar(cartao);
          salvarLote.disabled = !cartoesAtuais.some(c => c.aprovado && !c.salvo && c.lote);
        });
        prontas++;
      } catch (erro) {
        // A foto-base já foi desenhada antes da extração do tom. Se esta falhar,
        // ocultá-la para ninguém confundir o tom do mestre com uma prévia válida.
        cartao.canvas.width = 0;
        cartao.canvas.height = 0;
        cartao.canvas.hidden = true;
        cartao.estado.textContent = `Prévia não gerada: ${erro.message}`;
      }
    }
    estadoGeral.textContent = `${prontas} ${prontas === 1 ? 'prévia pronta' : 'prévias prontas'} para ${corSelect.options[corSelect.selectedIndex].text}. Nenhuma foto foi salva automaticamente.`;
    salvarLote.hidden = true;
    salvarLote.disabled = true;
    gerarBotao.disabled = false;
    corSelect.disabled = false;
    if (modeloSelect) modeloSelect.disabled = false;
  }

  async function salvarTodas() {
    const pendentes = cartoesAtuais.filter(c => c.aprovado && !c.salvo && c.lote);
    if (!pendentes.length) return;
    salvarLote.disabled = true;
    gerarBotao.disabled = true;
    corSelect.disabled = true;
    if (modeloSelect) modeloSelect.disabled = true;
    estadoGeral.textContent = `Salvando ${pendentes.length} ${pendentes.length === 1 ? 'foto' : 'fotos'} nos produtos…`;
    let salvas = 0;
    for (const cartao of pendentes) if (await publicar(cartao)) salvas++;
    estadoGeral.textContent = `${salvas} ${salvas === 1 ? 'foto salva' : 'fotos salvas'} de ${pendentes.length}. Confira o resultado em cada produto.`;
    salvarLote.disabled = !cartoesAtuais.some(c => c.aprovado && !c.salvo && c.lote);
    gerarBotao.disabled = false;
    corSelect.disabled = false;
    if (modeloSelect) modeloSelect.disabled = false;
  }

  gerarBotao.addEventListener('click', gerarLote);
  salvarLote.addEventListener('click', salvarTodas);
  corSelect.addEventListener('change', () => {
    resultados.replaceChildren();
    cartoesAtuais = [];
    salvarLote.hidden = true;
    salvarLote.disabled = true;
    estadoGeral.textContent = corSelect.value ? 'Clique em “Ver fotos para montar” para criar as prévias.' : 'Escolha uma cor deste modelo.';
  });
  modeloSelect.addEventListener('change', atualizarCores);
  produtoSelect.addEventListener('change', () => {
    const real = catalogoReal.find(p => Number(p.produto_id) === Number(produtoSelect.value || 0));
    const opcoesDoProduto = catalogo.filter(p => Number(p.produto_id) === Number(produtoSelect.value || 0));
    const modelosDoProduto = [...new Set(opcoesDoProduto.map(p => Number(p.modelo_id)))];
    const configurar=document.getElementById('abrir-configuracao-motor');
    if(configurar)configurar.href='configurar_motor.php'+(real?'?produto='+encodeURIComponent(real.produto_id):'');
    const familiaForracao=(window.MOBEL_FORRACOES_CONFIG||[]).find(f=>Number(f.produto_generico_id)===Number(real?.produto_id));
    if(criarMestre)criarMestre.href=real?'novo_mestre.php?produto='+encodeURIComponent(real.produto_id)+(familiaForracao?'&familia='+encodeURIComponent(familiaForracao.id):''):'novo_mestre.php';
    if (produtoInfo) produtoInfo.innerHTML = real ? `<strong>#${real.produto_id} ${real.nome_produto}</strong><br>Tipo: ${real.tipo_produto || '—'} · físico: ${real.quantidade_total || 0}<br>Composição: ${real.composicao_resumo || 'não cadastrada'}<br>${opcoesDoProduto.length ? `<span class="text-success">Cores físicas prontas: ${[...new Set(opcoesDoProduto.map(p => p.cor_nome))].join(', ')}.</span>` : '<span class="text-warning">Nenhuma cor pronta para este produto: falta mestre compatível ou as capas desta composição estão sem estoque.</span>'}` : 'Selecione um produto para consultar a composição e suas cores físicas.';
    modeloSelect.value = modelosDoProduto.length === 1 ? String(modelosDoProduto[0]) : '';
    atualizarCores();
    const forracao=familiaForracao;
    if(forracao && produtoInfo){
      produtoInfo.textContent=`${real.nome_produto}: forração sem capa em estoque. As cores são ilustradas com amostras aprovadas depois de criar e aprovar o mestre. Use “Criar modelo mestre” ou “Configurar novo produto”.`;
      estadoGeral.textContent='A seleção de cor desta forração funciona na linha do pedido/orçamento. A montagem em lote desta área é destinada às capas físicas.';
      return;
    }
    if (opcoesDoProduto.length) {
      estadoGeral.textContent = `${real?.nome_produto || 'Produto'}: escolha uma das cores físicas acima para gerar a prévia.`;
    } else if (real) {
      estadoGeral.textContent = `${real.nome_produto}: não há combinação liberada. Este produto não será confundido com outro sofá de composição diferente.`;
    }
  });
  filtros.filter(Boolean).forEach(el => el.addEventListener('change', () => { atualizarFiltrosECatalogo(); produtoSelect.dispatchEvent(new Event('change')); }));
  buscaProduto?.addEventListener('input', () => { atualizarFiltrosECatalogo(); produtoSelect.dispatchEvent(new Event('change')); });
  atualizarFiltrosECatalogo();
  atualizarCores();

  const preselecionar = window.MOBEL_PRESELECAO || {};
  if (Number(preselecionar.produto) && [...produtoSelect.options].some(o => Number(o.value) === Number(preselecionar.produto))) {
    produtoSelect.value = String(preselecionar.produto);
    produtoSelect.dispatchEvent(new Event('change'));
  }
  if (Number(preselecionar.modelo) && [...modeloSelect.options].some(o => Number(o.value) === Number(preselecionar.modelo))) {
    modeloSelect.value = String(preselecionar.modelo);
    atualizarCores();
  }
  if (Number(preselecionar.cor) && [...corSelect.options].some(o => Number(o.value) === Number(preselecionar.cor))) {
    corSelect.value = String(preselecionar.cor);
    estadoGeral.textContent = 'Combinação pronta. Clique em “Ver fotos para montar”.';
  }
})();
