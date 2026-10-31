'use strict';
const assert = require('node:assert/strict');
const {aplicar, extrairTom} = require('../assets/js/motor_recoloracao.js');
const largura = 80, altura = 80;
const base = new Uint8ClampedArray(largura * altura * 4);
const mascara = new Uint8ClampedArray(base.length);
for (let y = 0; y < altura; y++) for (let x = 0; x < largura; x++) {
  const i = (y * largura + x) * 4;
  base.set([140, 80, 40, 255], i); // madeira fora de duas áreas de tecido
  mascara[i + 3] = 255;
  if (x > 5 && x < 30 && y > 10 && y < 60 || x > 40 && x < 70 && y > 10 && y < 60) {
    const tom = y < 30 ? 20 : 40; // tecido preto com sombras
    base.set([tom, tom, tom, 255], i);
    mascara.set([255, 255, 255, 255], i);
  }
}
const antes = base.slice();
const alterados = aplicar(base, mascara, [220, 180, 45]);
assert(alterados > 100);
for (let i = 0; i < base.length; i += 4) {
  if (mascara[i] === 0) assert.deepEqual(base.slice(i, i + 4), antes.slice(i, i + 4));
  assert.equal(base[i + 3], antes[i + 3]);
}
const pixel = (x, y) => (y * largura + x) * 4;
assert(base[pixel(15, 20)] < base[pixel(15, 40)]); // mantém relevo
assert(base[pixel(15, 40)] > base[pixel(15, 40) + 2]); // preto tornou-se amarelo
assert(base[pixel(50, 40)] > base[pixel(50, 40) + 2]); // segunda área também
const mestreClaro = antes.slice();
for (let y = 0; y < altura; y++) for (let x = 0; x < largura; x++) {
  const i = (y * largura + x) * 4;
  if (mascara[i] === 255) {
    const tom = y < 30 ? 205 : 235;
    mestreClaro.set([tom + 3, tom, tom - 2, 255], i);
  }
}
aplicar(mestreClaro, mascara, [246, 198, 207]);
assert(mestreClaro[pixel(15, 20)] < mestreClaro[pixel(15, 40)]); // mantém volume no mestre neutro
assert(mestreClaro[pixel(15, 40)] <= 255);
assert(mestreClaro[pixel(15, 40)] > mestreClaro[pixel(15, 40) + 1]); // conserva o rosa
const referencia = new Uint8ClampedArray(800 * 800 * 4);
for (let i = 0; i < referencia.length; i += 4) referencia.set([220, 180, 45, 255], i);
assert.deepEqual(extrairTom(referencia, 800, 800), [220, 180, 45]);
const areaPequena = new Uint8ClampedArray(91 * 69 * 4);
for (let i = 0; i < areaPequena.length; i += 4) areaPequena.set([210, 150, 165, 255], i);
assert.deepEqual(extrairTom(areaPequena, 91, 69), [210, 150, 165]);
assert.throws(() => aplicar(base, new Uint8ClampedArray(4), [1, 2, 3]));
console.log('[OK] Recoloração e extração de amostra pequena preservam madeira, transparência e sombras.');
