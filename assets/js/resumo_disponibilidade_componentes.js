/* Explica, na própria linha, qual componente motivou o alerta de estoque. */
(() => {
  'use strict';
  const $ = window.jQuery;
  if (!$) return;

  function numero(valor, padrao = 0) {
    const n = parseInt(valor, 10);
    return Number.isFinite(n) ? n : padrao;
  }

  function tipoComponente(componente) {
    const texto = String(componente?.nome_produto || componente?.produto_nome || componente?.nome || 'Componente');
    // SIMPLES representa uma peça física inteira, mesmo quando o nome cita o tampo.
    // Em conjuntos fixos, o nome do grupo identifica a peça sem alongar o selo.
    if (componente?.item_inteiro || componente?.tipo_produto === 'SIMPLES') {
      if (/^mesa\b/i.test(texto) && /\btampo\b/i.test(texto)) return 'Mesa completa (base + tampo)';
      return String(componente.rotulo_grupo || texto);
    }
    if (/\bcapa\b/i.test(texto)) return 'Capa';
    if (/estrutura|recheio/i.test(texto)) return 'Estrutura';
    if (/forra[çc][ãa]o|servi[çc]o/i.test(texto)) return 'Forração';
    if (/colch[aã]o/i.test(texto)) return 'Colchão';
    if (/tampo/i.test(texto)) return 'Tampo';
    if (/base|madeira|arma[çc][ãa]o/i.test(texto)) return 'Base';
    return 'Componente';
  }

  function componentesParaResumo(response) {
    if (!response) return [];
    if (Array.isArray(response.componentes) && response.componentes.length) return response.componentes;
    // Uma peça SIMPLES sem componentes internos também precisa nomear a falta.
    const faltante = numero(response.faltante_pedido ?? response.faltante_orcamento);
    if (response.tipo_produto === 'SIMPLES' && faltante > 0 && response.produto_nome) {
      return [{ ...response, item_inteiro: true }];
    }
    return [];
  }

  function resumo(response) {
    const componentes = componentesParaResumo(response);
    if (!componentes.length) return null;
    const grupos = {};
    componentes.forEach(comp => {
      if (comp.tipo_produto === 'SERVICO' || comp.controla_estoque === false || numero(comp.estoque_total) >= 999999) return;
      const tipo = tipoComponente(comp);
      const faltante = numero(comp.faltante_pedido ?? comp.faltante_orcamento);
      const livre = numero(comp.livre_apos_pedido ?? comp.livre_apos_orcamento ?? comp.livre_periodo ?? comp.estoque_disponivel);
      // Forração é serviço/tecido sob demanda. Não há rolo físico sendo
      // reservado, portanto o saldo sentinela (ex.: 999999) não é útil ao atendente.
      if (tipo === 'Forração') return;

      if (!grupos[tipo]) grupos[tipo] = { livre, faltante };
      else {
        grupos[tipo].livre = Math.min(grupos[tipo].livre, livre);
        grupos[tipo].faltante += faltante;
      }
    });
    const partes = Object.keys(grupos).map(tipo => {
      const grupo = grupos[tipo];
      const texto = grupo.faltante > 0
        ? `${grupo.faltante === 1 ? 'falta' : 'faltam'} ${grupo.faltante}`
        : `${grupo.livre} livre${grupo.livre === 1 ? '' : 's'}`;
      return { tipo, texto, faltante: grupo.faltante > 0 };
    });
    return partes.length ? partes : null;
  }

  function atualizarLinha(linha) {
    const $linha = $(linha);
    const response = $linha.data('disponibilidade-response');
    const partes = resumo(response);
    const $contexto = $linha.find('.disponibilidade-contexto');
    const $detalhe = $contexto.find('.status-detalhe');
    if (!partes || !$detalhe.length) return;
    const assinatura = partes.map(parte => `${parte.tipo}:${parte.texto}`).join('|');
    if ($detalhe.attr('data-resumo-componentes') !== assinatura) {
      $detalhe
        .empty()
        .attr('data-resumo-componentes', assinatura)
        .append(partes.map(parte => {
          const classe = parte.faltante ? ' disponibilidade-flag--falta' : '';
          return $('<span/>', {
            class: `disponibilidade-flag${classe}`,
            text: `${parte.tipo}: ${parte.texto}`
          });
        }));
    }
    $contexto.attr('title', `Disponibilidade por componente: ${partes.map(parte => `${parte.tipo}: ${parte.texto}`).join('; ')}. Clique para o detalhamento completo.`);
  }

  function faltasCriticas(response) {
    const componentes = componentesParaResumo(response);
    const grupos = {};
    componentes.forEach((componente) => {
      const faltante = numero(componente.faltante_pedido ?? componente.faltante_orcamento);
      if (faltante <= 0) return;
      const tipo = tipoComponente(componente);
      if (tipo === 'Forração') return;
      grupos[tipo] = (grupos[tipo] || 0) + faltante;
    });
    return Object.keys(grupos).map((tipo) => ({ tipo, quantidade: grupos[tipo] }));
  }

  function destacarNoPainel(response) {
    const faltas = faltasCriticas(response);
    const $conteudo = $('#conteudo_consulta_disponibilidade');
    if (!$conteudo.length) return;
    $conteudo.find('.resumo-faltas-criticas').remove();
    if (!faltas.length) return;

    const $aviso = $('<section/>', { class: 'resumo-faltas-criticas', role: 'alert' })
      .append($('<div/>', { class: 'resumo-faltas-criticas__titulo', text: 'AÇÃO NECESSÁRIA — PRODUZIR OU REGULARIZAR' }))
      .append($('<div/>', { class: 'resumo-faltas-criticas__texto', text: 'Falta antes da entrega:' }));
    const $lista = $('<div/>', { class: 'resumo-faltas-criticas__itens' });
    faltas.forEach((falta) => {
      $lista.append($('<span/>', {
        class: 'resumo-faltas-criticas__item',
        text: `${falta.tipo}: ${falta.quantidade === 1 ? 'falta' : 'faltam'} ${falta.quantidade}`
      }));
    });
    $aviso.append($lista).append($('<small/>', {
      class: 'resumo-faltas-criticas__ajuda',
      text: 'A quantidade continua indisponível até a produção ser registrada no estoque.'
    }));
    $conteudo.prepend($aviso);
  }

  function ocultarSaldosSobDemanda(response) {
    if (!response) return;
    const $conteudo = $('#conteudo_consulta_disponibilidade');
    if (!$conteudo.length) return;
    const componentes = Array.isArray(response.componentes) ? response.componentes : [];
    componentes.forEach((componente, indice) => {
      const estoque = numero(componente.estoque_total);
      const tipo = tipoComponente(componente);
      if (estoque < 999999 && tipo !== 'Forração') return;
      const nome = String(componente.nome_produto || componente.produto_nome || componente.nome || 'Serviço sob demanda');
      const qtd = componente.quantidade_por_unidade || componente.quantidade || 1;
      const $linha = $conteudo.find('ul li').eq(indice);
      if ($linha.length) {
        $linha.empty().append($('<strong/>', { text: nome })).append(document.createTextNode(` — usa ${qtd} por unidade · serviço/tecido sob demanda; não possui estoque físico.`));
      }
    });
    if (numero(response.estoque_total) >= 999999) {
      const $primeiraCaixa = $conteudo.find('.painel-disponibilidade-grid .painel-box').first();
      $primeiraCaixa.find('.painel-valor-principal').text('Sob demanda');
      $primeiraCaixa.find('.painel-subtexto').text('Não representa estoque físico');
    }
  }

  function incluirEstilo() {
    if (document.getElementById('estilo-resumo-disponibilidade')) return;
    $('<style/>', {
      id: 'estilo-resumo-disponibilidade',
      text: `
        .disponibilidade-contexto .status-detalhe { display: inline-flex; flex-wrap: wrap; gap: 3px; vertical-align: middle; }
        .disponibilidade-flag { display: inline-block; padding: 1px 5px; border-radius: 9px; background: #e7f1ff; border: 1px solid #bad8f5; color: #285c8e; font-size: 9px; font-weight: 700; line-height: 15px; white-space: nowrap; }
        .disponibilidade-flag--falta { background: #fff0f0; border-color: #efb4b4; color: #a42a2a; }
        .resumo-faltas-criticas { margin: 0 0 12px; padding: 12px 14px; border: 2px solid #fff; border-left-width: 7px; border-radius: 10px; background: #fff7f8; color: #8b102a; box-shadow: 0 2px 7px rgba(80,0,10,.2); }
        .resumo-faltas-criticas__titulo { font-size: 13px; font-weight: 900; letter-spacing: .03em; text-decoration: underline; }
        .resumo-faltas-criticas__texto { margin-top: 3px; font-weight: 700; }
        .resumo-faltas-criticas__itens { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 7px; }
        .resumo-faltas-criticas__item { padding: 4px 8px; border: 1px solid #d81845; border-radius: 999px; background: #fff; color: #a10f2b; font-size: 12px; font-weight: 900; }
        .resumo-faltas-criticas__ajuda { display: block; margin-top: 7px; color: #6f1025; font-weight: 600; }
      `
    }).appendTo('head');
  }

  function respostaDoConjuntoFixo($pai) {
    const regras = $pai.data('regras-conjunto');
    if (!Array.isArray(regras) || !regras.length || regras.some(regra => numero(regra.produto_id) <= 0)) return null;

    const indice = numero($pai.data('index'));
    const quantidade = numero($pai.find('.quantidade_item, .item-qtd').first().val());
    const $filhos = $pai.closest('table').find('tbody tr.item-conjunto-filho-row').filter(function () {
      return numero($(this).data('conjunto-pai-index')) === indice;
    });
    const componentes = new Map();
    let estoqueTotal = Infinity;
    let livrePeriodo = Infinity;
    let livreApos = Infinity;

    for (const regra of regras) {
      const produtoId = numero(regra.produto_id);
      const porConjunto = Number(regra.quantidade_por_conjunto) || 0;
      const $filho = $filhos.filter(function () {
        return numero($(this).find('.produto_id').val()) === produtoId;
      }).first();
      if (!$filho.length || porConjunto <= 0 || numero($filho.find('.quantidade_item, .item-qtd').first().val()) !== Math.round(quantidade * porConjunto)) return null;

      const response = $filho.data('disponibilidade-response');
      if (!response || response.success === false) return null;
      estoqueTotal = Math.min(estoqueTotal, Math.floor(numero(response.estoque_total) / porConjunto));
      livrePeriodo = Math.min(livrePeriodo, Math.floor(numero(response.livre_periodo) / porConjunto));
      livreApos = Math.min(livreApos, Math.floor(numero(response.livre_apos_orcamento) / porConjunto));

      const partes = Array.isArray(response.componentes) && response.componentes.length
        ? response.componentes
        : [{ ...response, produto_id: produtoId, produto_nome: $filho.find('.nome_produto_display').val(), rotulo_grupo: regra.nome_grupo }];
      partes.forEach(comp => {
        if (comp.controla_estoque === false || numero(comp.estoque_total) >= 999999) return;
        const chave = numero(comp.produto_id) || String(comp.produto_nome || comp.nome_produto || '');
        const anterior = componentes.get(chave);
        // A resposta de cada filho já considera o contexto inteiro do pedido.
        // O mesmo tampo aparece nos dois filhos; repetir ou somar duplicaria seu consumo.
        if (!anterior || numero(comp.reservado_orcamento_atual ?? comp.quantidade_necessaria) > numero(anterior.reservado_orcamento_atual ?? anterior.quantidade_necessaria)) {
          componentes.set(chave, { ...comp });
        }
      });
    }

    const nome = $pai.find('.nome_produto_display').val() || 'Conjunto';
    return {
      produto_nome: nome,
      produto_composto: true,
      quantidade_solicitada: quantidade,
      reservado_orcamento_atual: quantidade,
      estoque_total: estoqueTotal,
      comprometido_periodo: Math.max(0, estoqueTotal - livrePeriodo),
      livre_periodo: livrePeriodo,
      livre_apos_orcamento: livreApos,
      faltante_orcamento: Math.max(0, -livreApos),
      disponivel: livreApos >= 0,
      nivel_alerta: livreApos < 0 ? 'indisponivel' : 'ok',
      componentes: Array.from(componentes.values()),
      resumo_conjunto_fixo: true
    };
  }

  function atualizarConjuntosFixos() {
    $('.item-conjunto-row').each(function () {
      const $pai = $(this);
      const response = respostaDoConjuntoFixo($pai);
      if (!response) return;
      const $contexto = $pai.find('.disponibilidade-contexto');
      const indisponivel = !response.disponivel;
      $pai.data('disponibilidade-response', response);
      $contexto
        .removeClass('status-neutro status-ok status-atencao status-indisponivel')
        .addClass(indisponivel ? 'status-indisponivel' : 'status-ok')
        .html(`<span class="status-principal">${indisponivel ? 'INDISPONÍVEL' : 'DISPONÍVEL'}</span><span class="status-detalhe"></span>`)
        .show();
      atualizarLinha(this);
    });
  }

  function atualizarTudo() {
    document.querySelectorAll('.item-pedido-row, .item-orcamento-row').forEach(atualizarLinha);
    atualizarConjuntosFixos();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => { incluirEstilo(); atualizarTudo(); });
  else { incluirEstilo(); atualizarTudo(); }
  $(document).on('click', '.disponibilidade-contexto', function () {
    const $linha = $(this).closest('.item-pedido-row, .item-orcamento-row');
    let response = $linha.data('disponibilidade-response');
    if ($linha.hasClass('item-conjunto-filho-row')) {
      const indice = numero($linha.data('conjunto-pai-index'));
      const $pai = $linha.closest('table').find('tbody tr.item-conjunto-row').filter(function () {
        return numero($(this).data('index')) === indice;
      }).first();
      const respostaConjunto = $pai.data('disponibilidade-response');
      if (respostaConjunto && respostaConjunto.resumo_conjunto_fixo) response = respostaConjunto;
    }
    window.setTimeout(() => { destacarNoPainel(response); ocultarSaldosSobDemanda(response); }, 60);
  });
  // Não observar o DOM inteiro: as telas de pedido/orçamento já se redesenham
  // ao consultar disponibilidade e um observador global pode entrar em ciclo.
  // Atualizamos somente após ações que realmente podem mudar a disponibilidade.
  let timerAtualizacao = null;
  function agendarAtualizacao() {
    window.clearTimeout(timerAtualizacao);
    timerAtualizacao = window.setTimeout(atualizarTudo, 120);
  }
  $(document).on('input change', '.item-conjunto-row .quantidade_item', function () {
    const $pai = $(this).closest('.item-conjunto-row');
    const regras = $pai.data('regras-conjunto');
    if (!Array.isArray(regras) || !regras.length || regras.some(regra => numero(regra.produto_id) <= 0)) return;

    const indice = numero($pai.data('index'));
    $pai.closest('table').find('tbody tr.item-conjunto-filho-row').filter(function () {
      return numero($(this).data('conjunto-pai-index')) === indice;
    }).add($pai).each(function () {
      const $linha = $(this);
      $linha.removeData('disponibilidade-response');
      $linha.find('.disponibilidade-contexto')
        .removeClass('status-ok status-atencao status-indisponivel')
        .addClass('status-neutro')
        .html('<span class="status-principal">ATUALIZANDO</span>')
        .show();
    });
  });
  $(document).on('change input', '.produto_id, .quantidade_item, .item-qtd, .observacoes_item_input, .mobel-capa-cor select, .forracao-cor-select', agendarAtualizacao);
  $(document).ajaxComplete((_event, _xhr, settings) => {
    if (String(settings.url || '').includes('verificar_estoque') || String(settings.data || '').includes('verificar_estoque')) {
      agendarAtualizacao();
    }
  });
  document.addEventListener('mobel:variante-capa-alterada', agendarAtualizacao);
})();
