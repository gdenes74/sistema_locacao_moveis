/* Formulários administrativos da aba Montagem por cor. Não altera pedidos/orçamentos. */
(() => {
  'use strict';
  const campo = (form, nome) => form && form.elements[nome];
  const preencher = (form, dados, titulo, idCampo) => {
    if (!form || !dados) return;
    form.reset();
    Object.keys(dados).forEach((nome) => {
      const el = campo(form, nome);
      if (!el) return;
      if (el.type === 'checkbox') el.checked = Number(dados[nome]) === 1;
      else el.value = dados[nome] ?? '';
    });
    campo(form, idCampo).value = dados.id || '';
    const heading = document.getElementById(titulo);
    if (heading) heading.textContent = `Editar #${dados.id}`;
    form.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };
  const limpar = (form, titulo, texto) => {
    if (!form) return;
    form.reset();
    const id = form.querySelector('input[type="hidden"][name$="_id"]');
    if (id) id.value = '';
    const heading = document.getElementById(titulo);
    if (heading) heading.textContent = texto;
  };
  document.addEventListener('click', (event) => {
    const familia = event.target.closest('.editar-familia');
    if (familia) {
      try { preencher(document.getElementById('form-familia'), JSON.parse(familia.dataset.familia), 'titulo-form-familia', 'familia_id'); } catch (_) {}
      return;
    }
    const vinculo = event.target.closest('.editar-vinculo');
    if (vinculo) {
      try { preencher(document.getElementById('form-vinculo'), JSON.parse(vinculo.dataset.vinculo), 'titulo-form-vinculo', 'vinculo_id'); } catch (_) {}
      return;
    }
    if (event.target.closest('#nova-familia')) limpar(document.getElementById('form-familia'), 'titulo-form-familia', 'Nova família');
    if (event.target.closest('#novo-vinculo')) limpar(document.getElementById('form-vinculo'), 'titulo-form-vinculo', 'Novo vínculo');
  });
})();
