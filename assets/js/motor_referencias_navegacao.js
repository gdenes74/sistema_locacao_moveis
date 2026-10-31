(() => {
  'use strict';
  const botao = document.getElementById('abrir-cores-referencias');
  const painel = document.getElementById('amostras');
  if (!botao || !painel) return;

  function abrirPainel(painel) {
    const detalhes = painel.closest('details');
    if (detalhes) detalhes.open = true;
    const cartao = painel.closest('.card');
    if (!cartao) return;
    // Ativa a aba diretamente, inclusive sem o JavaScript do Bootstrap.
    cartao.querySelectorAll('.nav-tabs .nav-link').forEach(aba => {
      const ativa = aba.getAttribute('href') === '#' + painel.id;
      aba.classList.toggle('active', ativa);
      aba.setAttribute('aria-selected', String(ativa));
    });
    cartao.querySelectorAll('.tab-content > .tab-pane').forEach(aba => {
      const ativa = aba === painel;
      aba.classList.toggle('active', ativa);
      aba.classList.toggle('show', ativa);
    });
    painel.setAttribute('tabindex', '-1');
    painel.focus({preventScroll: true});
    painel.scrollIntoView({behavior: 'smooth', block: 'start'});
  }

  botao.addEventListener('click', evento => {
    evento.preventDefault();
    abrirPainel(painel);
  });
  document.getElementById('abrir-cadastro-cor')?.addEventListener('click', evento => {
    evento.preventDefault();
    const cores = document.getElementById('cores');
    if (cores) abrirPainel(cores);
  });
  document.querySelectorAll('a[href="#modelos"],a[href="#familias"],a[href="#amostras"]').forEach(link=>link.addEventListener('click',event=>{const alvo=document.querySelector(link.getAttribute('href'));if(alvo){event.preventDefault();abrirPainel(alvo);}}));
  if (['#amostras','#modelos','#familias'].includes(location.hash)) {
    const alvo=document.querySelector(location.hash);if(alvo)abrirPainel(alvo);
  }
})();
