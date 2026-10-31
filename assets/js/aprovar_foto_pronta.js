(() => {
  'use strict';
  const {produtos = [], mestres = []} = window.MOBEL_FOTO_PRONTA || {};
  const secao = document.getElementById('foto-secao');
  const categoria = document.getElementById('foto-categoria');
  const sub = document.getElementById('foto-subcategoria');
  const produto = document.getElementById('foto-produto');
  const modelo = document.getElementById('foto-modelo');
  const botao = document.getElementById('foto-aprovar');
  const estado = document.getElementById('foto-aprovar-estado');
  const unico = (lista, id, nome) => [...new Map(lista.filter(p => p[id]).map(p => [String(p[id]), String(p[nome] || '')])).entries()];
  function preencher(el, valores, vazio) {
    const atual = el.value;
    el.replaceChildren(new Option(vazio, ''));
    valores.sort((a,b) => a[1].localeCompare(b[1], 'pt-BR')).forEach(([id,nome]) => el.add(new Option(nome,id)));
    if ([...el.options].some(o => o.value === atual)) el.value = atual;
  }
  function atualizar() {
    preencher(secao, unico(produtos,'secao_id','secao_nome'), 'Todas');
    const ps = produtos.filter(p => !secao.value || String(p.secao_id) === secao.value);
    preencher(categoria, unico(ps,'categoria_id','categoria_nome'), 'Todas');
    const pc = ps.filter(p => !categoria.value || String(p.categoria_id) === categoria.value);
    preencher(sub, unico(pc,'subcategoria_id','subcategoria_nome'), 'Todas');
    const pf = pc.filter(p => !sub.value || String(p.subcategoria_id) === sub.value);
    preencher(produto, pf.map(p => [String(p.produto_id), `${p.nome_produto}${p.cor ? ' — ' + p.cor : ''}`]), 'Escolha');
    atualizarProduto();
  }
  function atualizarProduto() {
    const p = produtos.find(item => String(item.produto_id) === produto.value);
    const foto = p?.foto_path || '';
    document.getElementById('foto-anterior').value = foto;
    document.getElementById('foto-atual').src = foto ? new URL('../../' + foto, location.href).href : '';
    document.getElementById('foto-atual-caminho').textContent = foto || 'Sem foto cadastrada';
    preencher(modelo, mestres.filter(m => String(m.produto_origem_id) === produto.value).map(m => [String(m.id), `${m.nome} (#${m.id})`]), 'Escolha a foto pronta');
    atualizarModelo();
  }
  function atualizarModelo() {
    document.getElementById('foto-candidata').src = modelo.value ? `modelo_base.php?id=${encodeURIComponent(modelo.value)}&revisao=1` : '';
    document.getElementById('foto-modelo-nome').textContent = modelo.value ? (modelo.selectedOptions[0]?.textContent || '') : 'Nenhuma foto candidata selecionada';
    botao.disabled = !produto.value || !modelo.value;
    if (!produto.value) {
      estado.textContent = 'Escolha primeiro o produto montado.';
      estado.className = 'mr-3 mb-2 mb-md-0 text-muted';
    } else if (!modelo.value) {
      estado.textContent = modelo.options.length > 1
        ? 'Agora escolha uma das fotos candidatas disponíveis.'
        : 'Este produto ainda não possui foto candidata preparada.';
      estado.className = 'mr-3 mb-2 mb-md-0 text-warning';
    } else {
      estado.textContent = 'Foto candidata selecionada. Compare as duas imagens antes de aprovar.';
      estado.className = 'mr-3 mb-2 mb-md-0 text-success font-weight-bold';
    }
  }
  secao.addEventListener('change', () => { categoria.value=''; sub.value=''; produto.value=''; atualizar(); });
  categoria.addEventListener('change', () => { sub.value=''; produto.value=''; atualizar(); });
  sub.addEventListener('change', () => { produto.value=''; atualizar(); });
  produto.addEventListener('change', atualizarProduto);
  modelo.addEventListener('change', atualizarModelo);
  document.getElementById('form-foto-pronta').addEventListener('submit', e => {
    if (!produto.value || !modelo.value || !confirm('Confirma a substituição da foto somente deste produto e de suas apresentações contextuais?')) e.preventDefault();
  });
  atualizar();
})();
