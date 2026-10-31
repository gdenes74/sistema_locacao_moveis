(() => {
  'use strict';
  const carregar = src => new Promise((resolve, reject) => {
    const imagem = new Image();
    imagem.onload = () => resolve(imagem);
    imagem.onerror = () => reject(new Error('Não foi possível carregar uma das imagens da revisão.'));
    imagem.src = src;
  });

  document.querySelectorAll('.modelo-revisao').forEach(bloco => {
    const base = bloco.querySelector('.modelo-revisao-base');
    const mascara = bloco.querySelector('.modelo-revisao-mascara');
    const amostra = bloco.querySelector('.modelo-revisao-amostra');
    const botao = bloco.querySelector('.modelo-revisao-gerar');
    const canvas = bloco.querySelector('.modelo-revisao-canvas');
    const estado = bloco.querySelector('.modelo-revisao-estado');
    if (!base || !mascara || !amostra || !botao || !canvas || !estado) return;

    botao.addEventListener('click', async () => {
      if (!amostra.value) {
        estado.textContent = 'Escolha primeiro uma cor aprovada.';
        return;
      }
      botao.disabled = true;
      canvas.style.display = 'none';
      estado.textContent = 'Montando a prévia localmente…';
      try {
        const [fotoBase, fotoMascara, fotoCor] = await Promise.all([
          carregar(base.src), carregar(mascara.src), carregar(`amostra_imagem.php?id=${encodeURIComponent(amostra.value)}`)
        ]);
        const apoio = document.createElement('canvas');
        apoio.width = fotoCor.naturalWidth;
        apoio.height = fotoCor.naturalHeight;
        const apoioCtx = apoio.getContext('2d', {willReadFrequently:true});
        apoioCtx.drawImage(fotoCor, 0, 0);
        const alvo = window.MOBEL_RECOLORACAO.extrairTom(apoioCtx.getImageData(0, 0, apoio.width, apoio.height).data, apoio.width, apoio.height);

        const ctx = canvas.getContext('2d', {willReadFrequently:true});
        ctx.clearRect(0, 0, 800, 800);
        ctx.drawImage(fotoBase, 0, 0, 800, 800);
        const dados = ctx.getImageData(0, 0, 800, 800);
        apoio.width = apoio.height = 800;
        apoioCtx.clearRect(0, 0, 800, 800);
        apoioCtx.drawImage(fotoMascara, 0, 0, 800, 800);
        const dadosMascara = apoioCtx.getImageData(0, 0, 800, 800);
        window.MOBEL_RECOLORACAO.aplicar(dados.data, dadosMascara.data, alvo);
        ctx.putImageData(dados, 0, 0);
        canvas.style.display = 'block';
        estado.textContent = `Resultado com ${amostra.selectedOptions[0].textContent}. Isto é apenas uma prévia; ainda não foi aprovado nem publicado.`;
      } catch (erro) {
        estado.textContent = erro.message;
      } finally {
        botao.disabled = false;
      }
    });
  });
})();
