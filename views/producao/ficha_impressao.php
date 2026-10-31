<?php
// Conteúdo de impressão isolado da navegação, formulários e histórico da tela.
require_once __DIR__.'/../../models/PlanoCorteTecido.php';
require_once __DIR__.'/../../models/ReferenciaCorteProducao.php';
if (empty($item['foto_mestre_url']) && isset($conn) && $conn instanceof PDO) {
    $mestreFicha = ReferenciaCorteProducao::mestreAprovado($conn, $item);
    if ($mestreFicha) $item['foto_mestre_url'] = BASE_URL.'/views/produtos/modelo_base.php?id='.(int)$mestreFicha['id'];
}
if(!isset($q)) {
    $q=$item['quantidade_faltante'];$largura=152;$borda=1;$avisos=[];$pecas=[];$material=$item['componente_material']??'';
    $fontes=ReferenciaCorteProducao::planos($conn,$item,150);
    foreach($fontes as $fonte){
        if(!$material)$material=$fonte['material'];$offset=count($pecas);
        foreach($fonte['pecas'] as $p){$p['por_unidade']*=$fonte['quantidade'];if(isset($p['sobra_de']))$p['sobra_de']+=$offset;$pecas[]=$p;}
    }
    if($pecas && max(array_column($pecas,'largura'))>150 && max(array_column($pecas,'largura'))<=152){$borda=0;$avisos[]='O corte cadastrado utiliza a largura total de 152 cm. Conferir as bordas do tecido.';}
    $resultado=null;
    try{if($pecas)$resultado=PlanoCorteTecido::calcular((int)$q,$pecas,$largura,$borda);}catch(InvalidArgumentException $e){$avisos[]=$e->getMessage();}
}
// Instruções resumidas para impressão: remover marcadores técnicos e repetições
// geradas quando a mesma ficha é encontrada no produto, estrutura e serviço.
$notas=[];
foreach ($fontes??[] as $fonte) {
    $textoFonte = preg_replace('/\[PLANO_VISUAL:[^\]]+\]/iu', '', (string)($fonte['texto']??''));
    $textoFonte = str_replace('\\n', "\n", $textoFonte);
    foreach (preg_split('/(?<=[.!?])\s+(?=\p{Lu})|\R+/u', $textoFonte) as $frase) {
        $frase=trim(preg_replace('/\s+/u',' ', $frase));
        if ($frase==='' || strlen($frase)>420) continue;
        if (preg_match('/^(?:PLANO DE CORTE|REFERÊNCIA DE PRODUÇÃO|REFERÊNCIA TÉCNICA|CORTE DE REFERÊNCIA|Produto comercial|Estrutura física|Material cadastrado|Referência de corte|Consumo:|O cálculo deve)/iu',$frase)) continue;
        if (!preg_match('/cort|costur|zíper|ziper|molde|grampe|dobr|emenda|folga|fech|margem|tampo|saia|tecido/iu',$frase)) continue;
        $chave=strtolower($frase);
        if (!isset($notas[$chave])) $notas[$chave]=$frase;
    }
}
$notasImpressao=implode("\n",array_slice(array_values($notas),0,5));
function hFichaPrint($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function nFichaPrint($v):string{return number_format((float)$v,2,',','.');}
function nomeFichaPrint($v):string{
    if(preg_match('/(?:^| — )Tampo:/u',$v))return 'Tampo';
    if(preg_match('/(?:^| — )Saia\/faixa:/u',$v))return 'Saia/faixa';
    return (string)preg_replace('/ — Peça de corte cadastrada$/u','',$v);
}
$tituloPrint=$item['componente_nome']?:($item['produto_nome']?:$item['descricao_item']);
$planoVisual=$planoVisual??PlanoCorteTecido::planoVisualTecnico($item);
?>
<style>
#producao-impressao{display:none}
@media print<?=!empty($visualizarImpressao)?', screen':''?>{
 @page{size:A4 portrait;margin:12mm}
 html,body,.wrapper,.content-wrapper{height:auto!important;min-height:0!important;margin:0!important;padding:0!important;overflow:visible!important;background:white!important}
 .main-header,.main-sidebar,.main-footer,.control-sidebar,.preloader,.content-wrapper>*{display:none!important}
 .content-wrapper>#producao-impressao{display:block!important}
 #producao-impressao{font:10pt/1.3 Arial,sans-serif;color:#111}
 #producao-impressao h1{font-size:16pt;margin:0 0 3mm}
 #producao-impressao h2{font-size:12pt;margin:4mm 0 2mm}
 #producao-impressao p{margin:1mm 0 2mm}
 #producao-impressao table{border-collapse:collapse;width:100%;margin:3mm 0}
 #producao-impressao th,#producao-impressao td{border-bottom:1px solid #aaa;padding:2mm 1mm;text-align:left;vertical-align:top}
 #producao-impressao .print-desenhos{display:flex;flex-wrap:wrap;gap:4mm;align-items:flex-start}
 #producao-impressao .print-desenho{width:calc(50% - 2mm);break-inside:avoid}
 #producao-impressao .print-desenho p{font-size:9pt;min-height:9mm}
 #producao-impressao svg{width:100%;height:auto;max-height:70mm;display:block}
 #producao-impressao svg text{font:13px Arial,sans-serif;fill:#111}
 #producao-impressao .print-notas{white-space:pre-wrap;font-size:9pt}
 #producao-impressao .print-aviso{font-size:9pt;border-left:2px solid #333;padding-left:2mm}
 #producao-impressao .print-plano-tecnico{break-inside:avoid;margin:3mm 0}
 #producao-impressao .print-plano-tecnico img{display:block;width:100%;height:auto;max-height:245mm;object-fit:contain}
 #producao-impressao .plano-tecnico-visual{border:1px solid #b8c9dc;padding:3mm;margin:3mm 0;break-inside:avoid}
 #producao-impressao .plano-tecnico-foto{display:block;max-width:45mm;max-height:32mm;object-fit:contain;margin:2mm auto;border:1px solid #d6e0ea}
 #producao-impressao .plano-tecnico-resumo{display:flex;gap:3mm;margin:3mm 0}
 #producao-impressao .plano-tecnico-resumo div,#producao-impressao .plano-tecnico-peca{border:1px solid #cbd9e6;padding:2mm;background:#fff}
 #producao-impressao .plano-tecnico-resumo div{flex:1}.plano-tecnico-resumo b,.plano-tecnico-resumo span,.plano-tecnico-peca span{display:block}
 #producao-impressao .plano-tecnico-pecas{display:grid;grid-template-columns:repeat(2,1fr);gap:2mm}
 #producao-impressao .plano-tecnico-diagrama{overflow:hidden;border:1px solid #cbd9e6;padding:1mm;margin-top:3mm}.plano-tecnico-diagrama svg{display:block;width:100%;height:auto;min-width:0}.plano-tecnico-diagrama .svg-titulo{font:bold 24px Arial;fill:#243b53}.plano-tecnico-diagrama .svg-subtitulo,.plano-tecnico-diagrama .svg-nota,.plano-tecnico-diagrama .svg-legenda{font:15px Arial;fill:#52677f}.plano-tecnico-diagrama .svg-medida{font:bold 17px Arial;fill:#243b53}.plano-tecnico-diagrama .svg-label{font:bold 17px Arial;fill:#243b53}.plano-tecnico-diagrama .svg-label-small{font:bold 13px Arial;fill:#243b53}.plano-tecnico-diagrama .svg-fundo{fill:#eef3f7;stroke:#718096}.plano-tecnico-diagrama .svg-tampo{fill:#cfe4f7;stroke:#3f6f9f;stroke-width:2}.plano-tecnico-diagrama .svg-saia{fill:#dcebd8;stroke:#607d63;stroke-width:2}
}
</style>
<section id="producao-impressao">
<h1>Ficha de produção #<?=(int)$item['id']?></h1>
<p><strong><?=hFichaPrint($tituloPrint)?></strong> · Cor: <?=hFichaPrint($item['cor']?:'a definir')?> · Quantidade: <?=nFichaPrint($q)?></p>
<p>Cliente: <?=hFichaPrint($item['cliente_nome']?:'não informado')?> · Pedido: <?=hFichaPrint($item['pedido_id']?:'manual')?> · Entrega: <?=hFichaPrint($item['data_entrega']?:'não informada')?></p>
<p>Material: <?=hFichaPrint($material?:'não informado')?></p>
<?php if($resultado):?>
<h2>Tecido: <?=nFichaPrint($resultado['metros'])?> m lineares</h2>
<p>Largura total <?=nFichaPrint($largura)?> cm · útil <?=nFichaPrint($resultado['largura_util'])?> cm · descarte lateral <?=nFichaPrint($borda)?> cm por lado.</p>
<?php require __DIR__.'/plano_corte_profissional.php'; ?>
<table><thead><tr><th>Peça / corte (cm)</th><th>Quantidade</th><th>Faixas</th><th>Tecido</th></tr></thead><tbody>
<?php foreach($resultado['linhas'] as $p):$nomePrint=nomeFichaPrint((string)$p['nome']);?>
<tr><td><?=hFichaPrint($nomePrint)?> — <?=nFichaPrint($p['largura'])?> × <?=nFichaPrint($p['comprimento'])?></td><td><?=(int)$p['total_pecas']?></td><td><?=!empty($p['reuso'])?'Na sobra':(int)$p['fileiras']?></td><td><?=nFichaPrint($p['metros'])?> m<?=!empty($p['reuso'])?' adicionais':''?></td></tr>
<?php endforeach;?></tbody></table>
<?php endif;?>
<?php foreach(array_unique($avisos??[]) as $aviso):?><p class="print-aviso"><?=hFichaPrint($aviso)?></p><?php endforeach;?>
<?php if(!empty($notasImpressao)):?><h2>Instruções de corte e montagem</h2><div class="print-notas"><?=hFichaPrint($notasImpressao)?></div><?php endif;?>
<p style="font-size:8pt;margin-top:4mm">Conferir tecido e molde antes de cortar. A entrada da produção no estoque é registrada manualmente.</p>
</section>
