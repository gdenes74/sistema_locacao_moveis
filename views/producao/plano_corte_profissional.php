<?php
/** Apresentação única na calculadora e na ficha A4. A metragem vem de PlanoCorteTecido. */
if (empty($resultado['linhas'])) return;
$hPlan = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$nPlan = static fn($v) => number_format((float)$v, 2, ',', '.');
$productPlan = trim((string)($item['produto_nome'] ?? $item['descricao_item'] ?? 'Produto'));
$colorPlan = trim((string)($item['cor'] ?? '')) ?: 'a definir';
$photoPlan = trim((string)($item['foto_mestre_url'] ?? ''));
$usablePlan = (float)$resultado['largura_util'];
$linesPlan = $resultado['linhas'];
$detailedPlan = !array_filter($linesPlan, static fn($p) => stripos((string)$p['nome'], 'sem molde detalhado') !== false);
$labelPlan = static fn($name) => (string)preg_replace('/^.*? — /u', '', (string)$name);
?>
<style>
.plan-sheet{font-family:Arial,sans-serif;color:#21354a;background:#f5f8fb;border:1px solid #ccdae6;border-radius:12px;padding:20px;margin:16px 0}
.plan-sheet *{box-sizing:border-box}.plan-sheet h2{font-size:25px;margin:0 0 5px}.plan-sheet h3{font-size:17px;margin:0 0 10px}.plan-sheet p{color:#52677c;margin:6px 0;line-height:1.35}
.plan-head{display:flex;justify-content:space-between;gap:15px;margin-bottom:15px}.plan-tag{background:#dfecf6;padding:9px;border-radius:8px;color:#3b709b;font-weight:bold;white-space:nowrap;height:max-content}.plan-kicker{font-size:12px;color:#46749a;font-weight:bold;letter-spacing:.05em}
.plan-main{display:grid;grid-template-columns:minmax(180px,28%) 1fr;gap:14px}.plan-card{background:#fff;border:1px solid #c9d7e5;border-radius:10px;padding:14px;min-width:0}.plan-photo{width:100%;height:230px;object-fit:contain}.plan-no-photo{height:230px;display:grid;place-items:center;background:#f0f3f6;color:#687b89;text-align:center}.plan-caption{border-top:1px solid #d7e1e8;padding-top:10px;margin-top:10px;font-weight:bold}
.plan-cut{margin:0 0 13px;border:1px solid #cbd8e3;border-radius:8px;padding:11px;break-inside:avoid;background:#fafdff}.plan-cut-title{font-weight:bold;color:#254b6c;font-size:14px}.plan-cut-sub{font-size:12px;margin:4px 0 8px}.plan-roll-head{display:flex;justify-content:space-between;font-size:12px;color:#385f80;font-weight:bold;border-bottom:1px solid #a9bfd0;margin:7px 0;padding-bottom:4px}
.plan-roll{border:2px solid #7490aa;background:#e9eef2;display:flex;min-height:90px;overflow:hidden;width:100%}.plan-piece{display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;background:#d2e5f5;border-right:2px solid #5983a6;min-width:0;padding:5px 2px;font-size:12px;font-weight:bold;overflow-wrap:anywhere}.plan-piece:nth-child(even){background:#dfeada;border-color:#6e9770}.plan-piece small{font-weight:normal;font-size:11px}.plan-circle{width:56px;max-width:75%;aspect-ratio:1;border:2px solid #4d7ba2;border-radius:50%;margin-bottom:4px}.plan-scrap{display:grid;place-items:center;flex:1;color:#536574;font-size:11px;min-width:0;padding:4px;text-align:center}
.plan-roll-caption{display:flex;justify-content:space-between;gap:8px;color:#385f80;font-weight:bold;font-size:12px;margin-top:4px}.plan-cut-note{font-size:12px;color:#40596e;margin-top:7px}.plan-cut-note strong{color:#183b5b}.plan-next{font-size:12px;padding:7px;background:#ecf3f8;border-left:3px solid #4b7ea8;margin-top:8px}.plan-callout{background:#e9f3fa;padding:10px;border-radius:8px;color:#335d7f;font-size:12px;margin-top:10px}
.plan-table{width:100%;border-collapse:collapse;margin-top:13px;background:#fff;font-size:12px}.plan-table th,.plan-table td{border:1px solid #c9d7e5;padding:7px;text-align:left}.plan-table th{background:#e4eef6}.plan-steps{background:#fff;border:1px solid #c9d7e5;border-radius:9px;padding:12px;margin-top:12px}.plan-steps h3{margin-bottom:6px}.plan-steps ol{margin:0;padding-left:21px}.plan-steps li{padding:3px 0;font-size:12px;line-height:1.4}
.plan-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:12px}.plan-summary>div{background:#fff;border:1px solid #c9d7e5;border-radius:8px;padding:11px}.plan-summary b{display:block;color:#32658e}.plan-summary span{font-size:12px}.plan-foot{font-size:11px;margin-top:10px}
.plan-ribbon{display:flex;min-width:520px;gap:5px;align-items:stretch;margin-top:10px}.plan-ribbon-segment{min-width:95px;display:flex;flex-direction:column}.plan-ribbon-length{text-align:center;font-size:12px;font-weight:bold;color:#315c7d;border-bottom:1px solid #315c7d;margin-bottom:6px;padding-bottom:3px}.plan-ribbon-body{display:flex;flex-direction:column;border:2px solid #7291aa;height:175px;background:#e9eef2}.plan-ribbon-piece{display:flex;flex-direction:column;justify-content:center;align-items:center;text-align:center;background:#d2e5f5;border-bottom:2px solid #5983a6;padding:3px;min-height:0;overflow:hidden;font-size:12px;font-weight:bold;overflow-wrap:anywhere}.plan-ribbon-piece:nth-child(even){background:#dfeada;border-color:#6e9770}.plan-ribbon-piece small{font-size:10px;font-weight:normal}.plan-ribbon-scrap{display:grid;place-items:center;flex:1;font-size:10px;color:#536574;text-align:center;padding:2px}.plan-ribbon-name{text-align:center;font-size:11px;font-weight:bold;color:#254b6c;margin-top:5px}.plan-ribbon-overflow{overflow-x:auto}.plan-ribbon-note{font-size:11px!important;margin-top:6px!important}
@media(max-width:700px){.plan-main,.plan-summary{grid-template-columns:1fr}.plan-sheet{padding:11px}.plan-head{display:block}}
@media print{.plan-sheet{background:#fff;border:0;padding:2mm;margin:2mm 0}.plan-sheet h2{font-size:16pt}.plan-main{grid-template-columns:29% 1fr;gap:3mm}.plan-card{padding:3mm}.plan-photo,.plan-no-photo{height:43mm}.plan-cut{padding:2mm;margin-bottom:2mm}.plan-ribbon{min-width:0}.plan-ribbon-body{height:35mm}.plan-table{margin-top:2mm}.plan-table th,.plan-table td{padding:1mm}.plan-steps{padding:2mm;margin-top:2mm}.plan-summary{gap:2mm;margin-top:2mm}.plan-summary>div{padding:2mm}.plan-head{margin-bottom:2mm}}
</style>
<div class="plan-sheet">
 <div class="plan-head"><div><div class="plan-kicker">PLANO DE CORTE | <?=$hPlan($productPlan)?></div><h2>Como aproveitar o rolo de tecido</h2><p><?=$hPlan($material ?: 'Material a conferir')?> · cor <?=$hPlan($colorPlan)?> · largura útil <?=$nPlan($usablePlan)?> cm</p></div><div class="plan-tag">MOBEL | PRODUÇÃO</div></div>
 <div class="plan-main">
  <div class="plan-card"><h3>Referência do produto</h3><?php if($photoPlan): ?><img class="plan-photo" src="<?=$hPlan($photoPlan)?>" alt="Foto mestre de <?=$hPlan($productPlan)?>"><?php else: ?><div class="plan-no-photo">Foto mestre ainda não disponível</div><?php endif; ?><div class="plan-caption"><?=$hPlan($productPlan)?><p>Cor: <?=$hPlan($colorPlan)?> · <?=$nPlan($q)?> unidade(s)</p></div></div>
  <div class="plan-card"><h3>Sequência de cortes no rolo</h3><p>Da esquerda para a direita, cada bloco avança no comprimento do rolo. De cima para baixo está a largura útil de <?=$nPlan($usablePlan)?> cm.</p>
   <?php $ribbonPlan=array_values(array_filter($linesPlan,static fn($line)=>empty($line['reuso']))); ?>
   <div class="plan-ribbon-overflow"><div class="plan-ribbon" aria-label="Esquema dos cortes sequenciais no rolo">
   <?php foreach($ribbonPlan as $ribbonIndexPlan=>$ribbonLinePlan):
    $ribbonWidthPlan=(float)$ribbonLinePlan['largura']; $ribbonLengthPlan=(float)$ribbonLinePlan['comprimento'];
    $ribbonCountPlan=min((int)$ribbonLinePlan['por_fileira'],(int)$ribbonLinePlan['total_pecas'],20);
    $ribbonScrapPlan=max(0,$usablePlan-$ribbonCountPlan*$ribbonWidthPlan);
    $ribbonNamePlan=$labelPlan($ribbonLinePlan['nome']);
    $ribbonRoundPlan=preg_match('/redondo|circular/iu',$productPlan.' '.$ribbonNamePlan) && preg_match('/tampo/iu',$ribbonNamePlan);
    $ribbonLinearPlan=stripos($ribbonNamePlan,'sem molde detalhado')!==false;
   ?><div class="plan-ribbon-segment" style="flex:<?=max(1,$ribbonLengthPlan)?> 1 0">
     <div class="plan-ribbon-length"><?=($ribbonIndexPlan+1)?>º corte · <?=$nPlan($ribbonLengthPlan/100)?> m →</div>
     <div class="plan-ribbon-body">
      <?php if($ribbonLinearPlan): ?><div class="plan-ribbon-piece" style="height:100%">Consumo linear<small>molde ainda não detalhado</small></div>
      <?php else: for($ribbonPiecePlan=0;$ribbonPiecePlan<$ribbonCountPlan;$ribbonPiecePlan++): ?><div class="plan-ribbon-piece" style="height:<?=min(100,100*$ribbonWidthPlan/$usablePlan)?>%">
       <?php if($ribbonRoundPlan): ?><span class="plan-circle"></span><?php endif; ?><?=$hPlan($ribbonNamePlan)?><small><?=$nPlan($ribbonWidthPlan)?> × <?=$nPlan($ribbonLengthPlan)?> cm</small></div><?php endfor; ?>
       <?php if($ribbonScrapPlan>0.001): ?><div class="plan-ribbon-scrap">Sobra <?=$nPlan($ribbonScrapPlan)?> cm</div><?php endif; endif; ?>
     </div><div class="plan-ribbon-name"><?=$hPlan($ribbonNamePlan)?></div>
   </div><?php endforeach; ?>
   </div></div><p class="plan-ribbon-note">Esquema de um trecho por tipo de peça. Os trechos se repetem conforme a quantidade indicada abaixo; proporção aproximada no comprimento.</p>
   <?php foreach($linesPlan as $indexPlan=>$linePlan):
    $widthPlan=(float)$linePlan['largura'];$lengthPlan=(float)$linePlan['comprimento'];$perPlan=max(1,(int)$linePlan['por_fileira']);$totalPlan=(int)$linePlan['total_pecas'];$rowsPlan=(int)$linePlan['fileiras'];
    $showPlan=min($perPlan,$totalPlan,20);$scrapPlan=max(0,$usablePlan-$showPlan*$widthPlan);
    $namePlan=$labelPlan($linePlan['nome']);$linearOnlyPlan=stripos($namePlan,'sem molde detalhado')!==false;
    $lastPlan=$rowsPlan>0?$totalPlan-($rowsPlan-1)*$perPlan:0;
    $nextPlan=$linesPlan[$indexPlan+1]??null;
   ?>
   <div class="plan-cut">
    <div class="plan-cut-title"><?=($indexPlan+1)?>º corte · <?=$hPlan($namePlan)?></div>
    <p class="plan-cut-sub">Peça: <?=$nPlan($widthPlan)?> cm na largura × <?=$nPlan($lengthPlan)?> cm no comprimento. <?=$totalPlan?> peça(s) para este lote.</p>
    <?php if(!empty($linePlan['reuso'])): ?>
     <div class="plan-next">Retirar da sobra lateral do corte anterior. A ficha calcula <?=$totalPlan?> peça(s) sem metros adicionais; conferir o encaixe físico antes do corte.</div>
    <?php else: ?>
     <div class="plan-cut-note"><strong>Trecho:</strong> <?=$nPlan($lengthPlan/100)?> m no comprimento × <?=$nPlan($usablePlan)?> cm de largura útil.</div>
     <?php if($linearOnlyPlan): ?><div class="plan-callout">Este trecho mostra apenas a metragem de referência para compra. O molde da peça ainda precisa de medidas técnicas cadastradas; a forma do corte não foi presumida.</div>
     <?php else: ?>
       <div class="plan-cut-note"><strong>Aproveitamento:</strong> <?=$perPlan?> peça(s) cabem na largura útil. <?php if($scrapPlan>0.001): ?>Neste trecho, a faixa restante de <?=$nPlan($scrapPlan)?> cm é sobra de corte.<?php else: ?>Neste trecho, a largura útil fica ocupada.<?php endif; ?></div>
      <?php if($nextPlan && empty($nextPlan['reuso']) && $scrapPlan>0.001 && $scrapPlan+0.0001<(float)$nextPlan['largura']): ?><div class="plan-next">A próxima peça exige <?=$nPlan($nextPlan['largura'])?> cm na largura. Ela não cabe na sobra de <?=$nPlan($scrapPlan)?> cm; precisa de outro trecho do rolo.</div><?php endif; ?>
     <?php endif; ?>
     <div class="plan-cut-note"><strong>Lote:</strong> <?=$rowsPlan?> trecho(s) de <?=$nPlan($lengthPlan/100)?> m = <?=$nPlan($linePlan['metros'])?> m lineares.<?php if($rowsPlan>1 && $lastPlan<$perPlan): ?> No último trecho, somente <?=$lastPlan?> de <?=$perPlan?> posição(ões) são usadas.<?php endif; ?></div>
    <?php endif; ?>
   </div>
   <?php endforeach; ?>
  </div>
 </div>
 <?php if($detailedPlan): ?>
 <table class="plan-table"><thead><tr><th>Peça de corte</th><th>Qtd.</th><th>Medida na largura × no comprimento</th><th>Trechos no rolo</th><th>Consumo</th></tr></thead><tbody>
 <?php foreach($linesPlan as $linePlan): ?><tr><td><?=$hPlan($labelPlan($linePlan['nome']))?></td><td><?=(int)$linePlan['total_pecas']?></td><td><?=$nPlan($linePlan['largura'])?> × <?=$nPlan($linePlan['comprimento'])?> cm</td><td><?=!empty($linePlan['reuso'])?'Da sobra':(int)$linePlan['fileiras']?></td><td><?=$nPlan($linePlan['metros'])?> m</td></tr><?php endforeach; ?>
 </tbody></table>
 <div class="plan-steps"><h3>Como executar o corte</h3><ol>
 <?php foreach($linesPlan as $linePlan): ?><li><?php if(!empty($linePlan['reuso'])): ?>Retire <?=$linePlan['total_pecas']?> peça(s) de <?=$hPlan($labelPlan($linePlan['nome']))?> da sobra indicada no corte anterior, sem avançar o rolo.<?php else: ?>Corte <?=(int)$linePlan['fileiras']?> trecho(s) de <?=$nPlan($linePlan['comprimento'])?> cm no comprimento do rolo; distribua até <?=(int)$linePlan['por_fileira']?> peça(s) de <?=$nPlan($linePlan['largura'])?> cm lado a lado na largura. Assim obtém <?=$linePlan['total_pecas']?> peça(s) de <?=$hPlan($labelPlan($linePlan['nome']))?>.<?php endif; ?></li><?php endforeach; ?>
 <li>Confira o sentido do tecido, emendas, margens de costura e grampeamento indicadas na ficha técnica antes de cortar.</li></ol></div>
 <?php endif; ?>
 <div class="plan-summary"><div><b><?=$nPlan($q)?> unidade(s)</b><span>Quantidade simulada</span></div><div><b><?=$nPlan($resultado['metros'])?> m lineares</b><span>Consumo calculado pelos trechos</span></div><div><b><?=$nPlan($usablePlan)?> cm</b><span>Largura útil informada</span></div></div>
 <p class="plan-foot">A foto mestre identifica o produto. As medidas de corte vêm da ficha técnica e dos ajustes feitos na calculadora. A simulação não movimenta estoque.</p>
</div>
