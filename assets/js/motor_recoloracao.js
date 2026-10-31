/* Recoloração local: usa uma máscara explícita e preserva os pixels fora do tecido. */
((raiz) => {
  'use strict';
  function extrairTom(pixels, largura, altura) {
    const candidatos = [];
    // Uma área marcada pode ter menos de 100 px. Ajustar a amostragem evita
    // rejeitar tecido válido apenas porque o passo fixo de 7 px colheu <20 pontos.
    const passoX = Math.max(1, Math.min(7, Math.floor(largura * .28 / 6)));
    const passoY = Math.max(1, Math.min(7, Math.floor(altura * .26 / 6)));
    for (let y = Math.floor(altura * .42); y < altura * .68; y += passoY) {
      for (let x = Math.floor(largura * .36); x < largura * .64; x += passoX) {
        const i = (y * largura + x) * 4;
        if (pixels[i + 3] > 180 && Math.min(pixels[i], pixels[i + 1], pixels[i + 2]) < 225) {
          const r = pixels[i], g = pixels[i + 1], b = pixels[i + 2];
          candidatos.push({r, g, b, brilho: .2126 * r + .7152 * g + .0722 * b});
        }
      }
    }
    if (candidatos.length < 20) throw new Error('A referência não oferece área central suficiente para extrair a cor.');
    /* Dobras centrais costumam ser sombras. Mede a faixa clara do tecido, mas
       descarta o brilho especular mais forte para preservar a cor real. */
    candidatos.sort((a, b) => a.brilho - b.brilho);
    const inicio = Math.floor(candidatos.length * .55);
    const fim = Math.max(inicio + 1, Math.ceil(candidatos.length * .88));
    const faixa = candidatos.slice(inicio, fim);
    const soma = faixa.reduce((total, pixel) => [total[0] + pixel.r, total[1] + pixel.g, total[2] + pixel.b], [0, 0, 0]);
    return soma.map(v => v / faixa.length);
  }

  /* Quando a amostra é a própria foto usada pelo mestre, mede somente os
     pixels brancos da máscara. Assim madeira e fundo não desbotam o tecido. */
  function extrairTomComMascara(pixels, mascara) {
    if (pixels.length !== mascara.length) throw new Error('A amostra e a máscara não coincidem.');
    const soma = [0, 0, 0]; let n = 0;
    for (let i = 0; i < pixels.length; i += 4) {
      if (pixels[i + 3] < 180 || mascara[i + 3] < 180 || mascara[i] < 180) continue;
      for (let k = 0; k < 3; k++) soma[k] += pixels[i + k];
      n++;
    }
    if (n < 100) throw new Error('A máscara não tem área suficiente para medir a referência.');
    return soma.map(v => v / n);
  }

  function aplicar(pixels, mascara, alvo) {
    if (pixels.length !== mascara.length || alvo.length !== 3) throw new Error('Imagem ou marcação incompatível.');
    const tecido = i => pixels[i + 3] >= 180 && mascara[i + 3] > 127 && mascara[i] > 127;
    let brilhoBase = 0, quantidade = 0;
    for (let i = 0; i < pixels.length; i += 4) {
      if (!tecido(i)) continue;
      brilhoBase += .2126 * pixels[i] + .7152 * pixels[i + 1] + .0722 * pixels[i + 2];
      quantidade++;
    }
    if (quantidade < 100) throw new Error('Marque uma área maior de tecido para ver a prévia.');
    brilhoBase /= quantidade;
    /* Calibração fotográfica original usada no mestre do sofá: a nova cor
       acompanha a luminosidade real de cada pixel. Assim trama, costuras,
       pequenas rugas e volume continuam pertencendo à foto-base. */
    for (let i = 0; i < pixels.length; i += 4) {
      if (!tecido(i)) continue;
      const brilho = .2126 * pixels[i] + .7152 * pixels[i + 1] + .0722 * pixels[i + 2];
      const fator = brilhoBase > 0 ? Math.max(.38, Math.min(1.65, brilho / brilhoBase)) : 1;
      for (let k = 0; k < 3; k++) pixels[i + k] = Math.min(255, Math.round(alvo[k] * fator));
    }
    return quantidade;
  }
  const api = {extrairTom, extrairTomComMascara, aplicar};
  if (typeof module === 'object' && module.exports) module.exports = api;
  else raiz.MOBEL_RECOLORACAO = api;
})(typeof window === 'undefined' ? globalThis : window);
