/* Variantes de capa física: a troca exige confirmação e consulta o estoque real. */
(() => {
  'use strict';
  const base = window.MOBEL_FORRACAO_BASE;
  if (!base) return;
  const normalizar = valor => String(valor || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, ' ').trim();
  const indice = new Map();
  const fotoUrl = path => path ? `${base}/${String(path).replace(/^\/+/, '')}` : '';

  const rotulo = produto => `${produto.nome}${produto.codigo ? ` — ${String(produto.codigo).toUpperCase()}` : ''}`;
  function buscarVariantes(texto, familia) {
    const bruto = String(texto || '').trim();
    const trecho = bruto.match(/(?:^|\|\s*)cor:\s*([^|]+)/i);
    const procurado = normalizar(trecho ? trecho[1] : bruto.replace(/^cor\s+/i, ''));
    if (!procurado) return [];
    const variantes = familia.variantes;
    const porCodigo = variantes.filter(v => v.codigo && normalizar(v.codigo) === procurado);
    if (porCodigo.length) return porCodigo;
    const porNome = variantes.filter(v => normalizar(v.nome) === procurado);
    if (porNome.length) return porNome;
    const porCor = variantes.filter(v => (v.aliases || []).some(a => normalizar(a) === procurado));
    if (porCor.length) return porCor;
    // Uma descrição parcial, como "floral rosa graúdo", identifica a estampa.
    // Cores repetidas só são escolhidas quando o nome/código distingue a capa.
    if (procurado.length < 6 || !procurado.includes(' ')) return [];
    return variantes.filter(v => (` ${normalizar(v.nome)} `).includes(` ${procurado} `));
  }
  function identificar(texto, familia) {
    const achados = buscarVariantes(texto, familia);
    return achados.length === 1 ? achados[0] : null;
  }
  function correspondenciaExata(texto, produto) {
    const bruto = String(texto || '').trim();
    const trecho = bruto.match(/(?:^|\|\s*)cor:\s*([^|]+)/i);
    const procurado = normalizar(trecho ? trecho[1] : bruto.replace(/^cor\s+/i, ''));
    return procurado !== '' && [produto.codigo, produto.nome, ...(produto.aliases || [])]
      .some(valor => valor && normalizar(valor) === procurado);
  }
  function corInformada(texto) {
    const bruto = String(texto || '').trim();
    const trecho = bruto.match(/(?:^|\|\s*)cor:\s*([^|]+)/i);
    const candidato = String(trecho ? trecho[1] : bruto).trim();
    // Uma observação longa pode não ser cor. Consideramos somente "Cor: ..."
    // ou um nome curto digitado sozinho, como "marsala".
    return trecho || (/^[\p{L}][\p{L}\s-]{0,32}$/u.test(candidato) && candidato.split(/\s+/).length <= 3)
      ? candidato : '';
  }
  function removerCorHerdada(texto, familia, produtoAnteriorId) {
    const bruto = String(texto || '').trim();
    if (!bruto) return '';
    const pertenceAoAnterior = valor => buscarVariantes(valor, familia)
      .some(v => v.id === produtoAnteriorId);
    const marcada = bruto.match(/(^|\|\s*)cor:\s*([^|]+)/i);
    if (marcada && pertenceAoAnterior(`Cor: ${marcada[2]}`)) {
      return bruto.replace(/(^|\|\s*)cor:\s*[^|]+/i, '$1')
        .replace(/^\s*\|\s*|\s*\|\s*$/g, '').trim();
    }
    // Linhas antigas podem ter somente o nome da cor salvo na observação.
    return pertenceAoAnterior(bruto) ? '' : bruto;
  }
  async function trocar(linha, familia, produto) {
    const atual = Number(linha.querySelector('.produto_id')?.value || 0);
    if (!produto || atual === produto.id) return;
    const recurso = familia.generico.tipo === 'SIMPLES' ? 'guardanapo' : 'capa';
    const confirmar = window.Swal ? await window.Swal.fire({
      title: 'Confirmar troca do produto?',
      text: `Usar ${produto.nome}? A disponibilidade do ${recurso} será consultada novamente. O preço e a observação serão mantidos.`,
      icon: 'question', showCancelButton: true, confirmButtonText: 'Sim, trocar', cancelButtonText: 'Cancelar'
    }).then(r => r.isConfirmed) : window.confirm(`Usar ${produto.nome}? O preço e a observação serão mantidos.`);
    if (!confirmar || !linha.isConnected || Number(linha.querySelector('.produto_id')?.value || 0) !== atual) return;
    const campo = linha.querySelector('.produto_id');
    const nome = linha.querySelector('.nome_produto_display');
    if (!campo || !nome) return;
    campo.value = String(produto.id);
    nome.value = produto.nome;
    nome.readOnly = true;
    let img = linha.querySelector('.foto-produto-linha');
    const caminho = fotoUrl(produto.foto);
    if (caminho) {
      if (!img) {
        img=document.createElement('img'); img.className='foto-produto-linha';
        img.style.cssText='width:50px;height:50px;object-fit:cover;margin-right:10px;border:1px solid #ddd;border-radius:4px;vertical-align:middle;cursor:zoom-in';
        linha.querySelector('td')?.prepend(img);
      }
      img.src=caminho; img.dataset.fotoCompleta=caminho; img.dataset.nomeProduto=produto.nome;
      delete img.dataset.originalSrc;
    } else if (img) img.remove();
    campo.dispatchEvent(new Event('change',{bubbles:true}));
    document.dispatchEvent(new CustomEvent('mobel:variante-capa-alterada',{detail:{linha}}));
    // A tela de pedido limpa o status durante a substituição. Reenvia o
    // evento após a linha terminar de receber o novo produto para garantir
    // que a consulta de disponibilidade repinte a célula.
    window.setTimeout(() => {
      if (linha.isConnected) document.dispatchEvent(new CustomEvent('mobel:variante-capa-alterada',{detail:{linha}}));
    }, 120);
  }
  function preparar(linha, familia) {
    const jaPreparado = linha.querySelector('.mobel-capa-cor');
    if (jaPreparado) {
      if (!jaPreparado.dataset.produtoId) jaPreparado.dataset.produtoId=String(linha.querySelector('.produto_id')?.value || '');
      return;
    }
    const obs=linha.querySelector('.observacoes_item_input');
    if (!obs) return;
    const bloco=document.createElement('div');
    bloco.className='mobel-capa-cor mt-2 p-2 border rounded bg-light';
    bloco.dataset.familia=String(familia.id);
    bloco.dataset.produtoId=String(linha.querySelector('.produto_id')?.value || '');
    const titulo=document.createElement('label'); titulo.className='small font-weight-bold mb-1 d-block'; titulo.textContent='Cor com capa cadastrada';
    const simples=familia.generico.tipo==='SIMPLES';
    titulo.textContent=simples ? 'Guardanapo com cor cadastrada' : 'Cor com capa cadastrada';
    const select=document.createElement('select'); select.className='form-control form-control-sm';
    select.add(new Option('Cor a definir',String(familia.generico.id)));
    for (const v of familia.variantes) select.add(new Option(rotulo(v),String(v.id)));
    const aviso=document.createElement('small'); aviso.className='form-text text-muted';
    aviso.textContent='Escolha a cor ou escreva na observação. A troca consulta a disponibilidade da capa.';
    const corNova=document.createElement('div'); corNova.className='alert alert-danger py-1 px-2 mt-2 mb-0 small'; corNova.hidden=true;
    const sugerir=document.createElement('button'); sugerir.type='button'; sugerir.className='btn btn-sm btn-outline-primary mt-1'; sugerir.hidden=true;
    bloco.append(titulo,select,aviso,sugerir,corNova); obs.insertAdjacentElement('afterend',bloco);
    const atualizar=() => {
      select.value=String(linha.querySelector('.produto_id')?.value || familia.generico.id);
      const candidatas=buscarVariantes(obs.value,familia);
      const sugestao=candidatas.length===1 ? candidatas[0] : null;
      const escrita=corInformada(obs.value);
      const produtoAtual=Number(linha.querySelector('.produto_id')?.value || 0);
      const nova = Boolean(escrita) && candidatas.length===0 && produtoAtual === familia.generico.id;
      aviso.textContent=candidatas.length>1
        ? `Há ${candidatas.length} ${simples ? 'guardanapos' : 'capas'} correspondentes. Escolha pelo nome completo e código no menu: ${candidatas.slice(0,4).map(rotulo).join('; ')}${candidatas.length>4 ? '; ...' : ''}.`
        : `Escolha ${simples ? 'o guardanapo' : 'a capa'} pelo nome e código ou escreva a cor na observação. A troca consulta a disponibilidade.`;
      corNova.hidden=!nova;
      const nomePrevisto=(familia.generico.nome || '').replace(/cor\s+a\s+definir/iu,`Cor ${escrita}`);
      const mensagemCorNova=nova ? `No documento: ${nomePrevisto}. Não foi ${simples ? 'encontrado guardanapo cadastrado' : 'encontrada capa cadastrada'} para “${escrita}”; ao confirmar o pedido, ${simples ? 'o guardanapo' : 'a capa'} entra na produção pendente. A ficha não aumenta o estoque automaticamente.` : '';
      if (corNova.textContent !== mensagemCorNova) corNova.textContent=mensagemCorNova;
      linha.dataset.mobelCorNova = nova ? escrita : '';
      sugerir.hidden=!sugestao || sugestao.id===Number(linha.querySelector('.produto_id')?.value || 0);
      if (!sugerir.hidden) { const texto=`Usar ${rotulo(sugestao)}`; if (sugerir.textContent!==texto) sugerir.textContent=texto; sugerir._produto=sugestao; }
    };
    select.addEventListener('change',async () => {
      const escolhido=Number(select.value)===familia.generico.id ? familia.generico : familia.variantes.find(v=>v.id===Number(select.value));
      await trocar(linha,familia,escolhido); atualizar();
    });
    sugerir.addEventListener('click',async () => { await trocar(linha,familia,sugerir._produto); atualizar(); });
    let ultimaTrocaAutomatica = 0;
    obs.addEventListener('input',() => {
      atualizar();
      const sugestao = identificar(obs.value, familia);
      const atual = Number(linha.querySelector('.produto_id')?.value || 0);
      if (sugestao && correspondenciaExata(obs.value, sugestao) && atual === Number(familia.generico.id) && ultimaTrocaAutomatica !== sugestao.id) {
        ultimaTrocaAutomatica = sugestao.id;
        trocar(linha, familia, sugestao).then(atualizar).catch(() => { ultimaTrocaAutomatica = 0; });
      }
      if (!sugestao) ultimaTrocaAutomatica = 0;
    });
    bloco._atualizar=atualizar; atualizar();
  }
  function varrer() {
    document.querySelectorAll('.item-pedido-row,.item-orcamento-row').forEach(linha => {
      const tipo=linha.getAttribute('data-tipo-linha') || 'PRODUTO';
      const id=Number(linha.querySelector('.produto_id')?.value || 0);
      const familia=['PRODUTO','ITEM_CONJUNTO'].includes(tipo) ? indice.get(id) : null;
      const bloco=linha.querySelector('.mobel-capa-cor');
      if (bloco && (!familia || Number(bloco.dataset.familia)!==familia.id)) bloco.remove();
      if (familia) { preparar(linha,familia); linha.querySelector('.mobel-capa-cor')?._atualizar(); }
    });
  }
  function iniciar() {
    fetch(`${base}/views/produtos/variantes_capa.php`,{
      credentials:'same-origin',
      headers:{'X-Mobel-Color-Token': String(window.MOBEL_COLOR_CATALOG_TOKEN || '')}
    })
      .then(r=>{if(!r.ok) throw new Error(`HTTP ${r.status}`); return r.json();})
      .then(dados=>{
        for (const f of dados.familias || []) {
          indice.set(f.generico.id,f);
          for (const v of f.variantes) {
            if (indice.has(v.id) && indice.get(v.id)!==f) indice.delete(v.id);
            else indice.set(v.id,f);
          }
        }
        varrer();
        let agendado=false;
        const agendar=()=>{
          if(agendado) return;
          agendado=true;
          window.setTimeout(()=>{agendado=false;varrer();},0);
        };
        // Só mudanças da linha/produto acionam a preparação. Mensagens e
        // controles que este script insere não podem realimentar o observador.
        const seletorEstrutural='.item-pedido-row,.item-orcamento-row,.produto_id,.foto-produto-linha';
        const observador=new MutationObserver(registros=>{
          if(registros.some(r=>r.type==='attributes'
            ? r.target.matches('.produto_id')
            : [...r.addedNodes].some(n=>n.nodeType===1 &&
              (n.matches(seletorEstrutural)||n.querySelector('.produto_id'))))) agendar();
        });
        observador.observe(document.body,{childList:true,subtree:true,attributes:true,attributeFilter:['value']});
        document.addEventListener('focusin',e=>{
          if(e.target.matches('.observacoes_item_input')) varrer();
        });
        // Não faça varredura periódica da tabela: além de custosa, ela pode
        // concorrer com a digitação da observação em formulários grandes.
        // A linha é preparada na carga inicial e em cada troca real de produto.
        document.addEventListener('change',e=>{
          if (!e.target.matches('.produto_id')) return;
          const linha=e.target.closest('.item-pedido-row,.item-orcamento-row');
          const bloco=linha?.querySelector('.mobel-capa-cor');
          const familia=bloco ? (dados.familias || []).find(f=>Number(f.id)===Number(bloco.dataset.familia)) : null;
          const anterior=Number(bloco?.dataset.produtoId || 0);
          const atual=Number(e.target.value || 0);
          // Ao substituir uma variante por "Cor a definir", a cor pertence ao
          // item anterior. Mantemos outra observação escrita pelo atendente,
          // mas removemos apenas essa cor herdada.
          const alvoDaFamilia = familia && (atual===Number(familia.generico.id) || familia.variantes.some(v=>Number(v.id)===atual));
          const origemDaFamilia = familia && (anterior===Number(familia.generico.id) || familia.variantes.some(v=>Number(v.id)===anterior));
          if (familia && anterior && anterior!==atual && origemDaFamilia && alvoDaFamilia) {
            const obs=linha.querySelector('.observacoes_item_input');
            if (obs) obs.value=atual===Number(familia.generico.id) ? removerCorHerdada(obs.value,familia,anterior) : '';
          }
          if (bloco) bloco.dataset.produtoId=String(atual || '');
          varrer();
        });
      }).catch(e=>console.error('Não foi possível carregar as variantes com capa:',e));
  }
  window.setTimeout(() => {
    if (typeof window.revalidarTodasAsLinhasDisponibilidade === 'function') window.revalidarTodasAsLinhasDisponibilidade();
  }, 900);
  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded',iniciar); else iniciar();
})();
