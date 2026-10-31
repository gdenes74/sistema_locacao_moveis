/* Explica, na própria linha, qual componente motivou o alerta de estoque. */
(() => {
  'use strict';
  const $ = window.jQuery;
  if (!$) return;

  function numero(valor, padrao = 0) {
    const n = parseInt(valor, 10);
    return Number.isFinite(n) ? n : padrao;
  }

  function tipoComponente(nome) {
    const texto = String(nome || 'Componente');
    if (/\bcapa\b/i.test(texto)) return 'Capa';
    if (/estrutura|recheio/i.test(texto)) return 'Estrutura';
    if (/forra[çc][ãa]o|servi[çc]o/i.test(texto)) return 'Forração';
    if (/colch[aã]o/i.test(texto)) return 'Colchão';
    if (/base|madeira|arma[çc][ãa]o/i.test(texto)) return 'Base';
    return 'Componente';
  }

  function resumo(response) {
    if (!response || !Array.isArray(response.componentes) || !response.componentes.length) return null;
    const grupos = {};
    response.componentes.forEach(comp => {
      const tipo = tipoComponente(comp.nome_produto || comp.produto_nome || comp.nome);
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
        ? `faltam ${grupo.faltante}`
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
    if (!response || !Array.isArray(response.componentes)) return [];
    const grupos = {};
    response.componentes.forEach((componente) => {
      const faltante = numero(componente.faltante_pedido ?? componente.faltante_orcamento);
      if (faltante <= 0) return;
      const tipo = tipoComponente(componente.nome_produto || componente.produto_nome || componente.nome);
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
        text: `${falta.tipo}: faltam ${falta.quantidade}`
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
      const tipo = tipoComponente(componente.nome_produto || componente.produto_nome || componente.nome);
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

  function atualizarTudo() {
    document.querySelectorAll('.item-pedido-row, .item-orcamento-row').forEach(atualizarLinha);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => { incluirEstilo(); atualizarTudo(); });
  else { incluirEstilo(); atualizarTudo(); }
  $(document).on('click', '.disponibilidade-contexto', function () {
    const response = $(this).closest('.item-pedido-row, .item-orcamento-row').data('disponibilidade-response');
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
  $(document).on('change input', '.produto_id, .quantidade_item, .item-qtd, .observacoes_item_input, .mobel-capa-cor select, .forracao-cor-select', agendarAtualizacao);
  document.addEventListener('mobel:variante-capa-alterada', agendarAtualizacao);
})();
