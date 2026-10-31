<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../models/ProducaoPendente.php';
require_once __DIR__.'/../../models/PlanoCorteTecido.php';
require_once __DIR__.'/../../models/ReferenciaCorteProducao.php';
$id=(int)($_GET['id']??0);
if(!isLoggedIn()){
    header('Location: '.BASE_URL.'/views/usuarios/login.php?retorno='.rawurlencode('views/producao/calculadora_tecido.php?id='.$id));exit;
}
$db=(new Database())->getConnection();
$item=(new ProducaoPendente($db))->obterPorId($id);
if(!$item){http_response_code(404);exit('Ficha não encontrada.');}
$mestreVisual=ReferenciaCorteProducao::mestreAprovado($db,$item);
if($mestreVisual)$item['foto_mestre_url']=BASE_URL.'/views/produtos/modelo_base.php?id='.(int)$mestreVisual['id'];
$planoVisual=PlanoCorteTecido::planoVisualTecnico($item);
$origem=PlanoCorteTecido::instrucoes($item);
$material=(string)($item['componente_material']??'');
$q=$_POST['quantidade']??$item['quantidade_faltante'];
$fontes=ReferenciaCorteProducao::planos($db,$item,152);
foreach($fontes as $fonte)if(!$material && !empty($fonte['material']))$material=$fonte['material'];
$largura=$_POST['largura_tecido']??(stripos($material,'Oxford')!==false?152:'');
$borda=$_POST['borda']??1;
$pecas=[];$avisos=[];
$fontes=ReferenciaCorteProducao::planos($db,$item,max(1,(float)($largura?:152)-(float)$borda*2));
foreach($fontes as $fonte){
    $origem.="\n\nReferência de corte: ".$fonte['nome']."\n".$fonte['texto'];
    if(!$fonte['pecas'])$avisos[]='Não foi reconhecido um molde completo em: '.$fonte['nome'].'. Confira a ficha e preencha as peças.';
    foreach($fonte['pecas'] as $p)if(str_starts_with($p['nome'],'Consumo linear de referência')){
        $avisos[]='Os metros exibidos são uma referência aproximada por unidade, sem molde detalhado. Para várias unidades da mesma cor, confira o aproveitamento antes da compra.';
        break;
    }
    if(stripos($fonte['texto'],'molde físico')!==false)$avisos[]='As laterais do sofá usam medidas aproximadas. O desenho retangular não substitui os recortes do molde físico.';
    $offset=count($pecas);
    foreach($fonte['pecas'] as $p){
        $p['nome']=$fonte['nome'].' — '.$p['nome'];
        $p['por_unidade']*=$fonte['quantidade'];
        if(isset($p['sobra_de']))$p['sobra_de']+=$offset;
        $pecas[]=$p;
    }
}
if($_SERVER['REQUEST_METHOD']!=='POST' && $pecas && $largura!=='' && max(array_column($pecas,'largura'))>(float)$largura-2*(float)$borda && max(array_column($pecas,'largura'))<=(float)$largura){
    $borda=0;
    $avisos[]='Este plano cadastrado usa a largura total de 152 cm. Bordas definidas como 0 para respeitar o corte da ficha; confirme se o tecido permite esse aproveitamento antes de cortar.';
}
$erro=null;$resultado=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $pecas=[];
    foreach((array)($_POST['pecas']??[]) as $p){
        if(!is_array($p))continue;
        $linha=['nome'=>trim((string)($p['nome']??'')),
            'largura'=>str_replace(',','.',(string)($p['largura']??'')),
            'comprimento'=>str_replace(',','.',(string)($p['comprimento']??'')),
            'por_unidade'=>str_replace(',','.',(string)($p['por_unidade']??''))];
        if(isset($p['sobra_de']) && $p['sobra_de']!=='')$linha['sobra_de']=(int)$p['sobra_de'];
        if(!empty($p['por_fileira_max']))$linha['por_fileira_max']=(int)$p['por_fileira_max'];
        $pecas[]=$linha;
    }
}
try{
    if((float)$q!=(int)$q)throw new InvalidArgumentException('A quantidade de capas deve ser inteira.');
    if($pecas&&$largura!=='')$resultado=PlanoCorteTecido::calcular((int)$q,$pecas,(float)$largura,(float)$borda);
}catch(InvalidArgumentException $e){$erro=$e->getMessage();}
function eCalc($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function nCalc($v):string{return number_format((float)$v,2,',','.');}
$page_title='Cálculo de tecido';$page_group='producao';
if(($_GET['impressao']??'')==='1') {
    $visualizarImpressao=true;
    ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Ficha de produção #<?=$id?> — impressão</title><style>@media screen{.wrapper{width:186mm}.preview-controls{padding:12px;font:14px Arial}}@media print{.preview-controls{display:none}}</style></head><body><div class="preview-controls"><a href="<?= BASE_URL ?>/views/producao/calculadora_tecido.php?id=<?=$id?>">Voltar à calculadora</a> · <button onclick="window.print()">Imprimir / Salvar PDF</button> · Prévia A4</div><div class="wrapper"><div class="content-wrapper"><?php require __DIR__.'/ficha_impressao.php'; ?></div></div></body></html><?php
    exit;
}
require __DIR__.'/../includes/header.php';
require __DIR__.'/ficha_impressao.php';
?>
<style>
.corte-plano{background:#fff;color:#17212b;border:1px solid #ced4da;max-width:100%;height:auto}
.corte-plano text{font:14px Arial,sans-serif;fill:#17212b}
.plano-tecnico-visual{border:1px solid #b8c9dc;border-radius:6px;padding:16px;margin:18px 0;background:#f8fbff;break-inside:avoid}
.plano-tecnico-cabecalho{display:flex;justify-content:space-between;gap:18px;align-items:start}.plano-tecnico-cabecalho h3{margin:0 0 5px}.plano-tecnico-cabecalho p{margin:0;color:#52677f}
.plano-tecnico-imagem{display:block;width:100%;max-width:1120px;height:auto;margin:12px auto 6px;border:1px solid #d6e0ea}.plano-tecnico-legenda{margin:0;color:#52677f;font-size:.9rem}
.plano-tecnico-foto{display:block;max-width:260px;max-height:180px;object-fit:contain;margin:10px auto;border:1px solid #d6e0ea}
.plano-tecnico-diagrama{overflow:auto;margin-top:14px;border:1px solid #cbd9e6;background:#fff;padding:8px}.plano-tecnico-diagrama svg{display:block;width:100%;min-width:700px;height:auto}.plano-tecnico-diagrama .svg-titulo{font:bold 24px Arial;fill:#243b53}.plano-tecnico-diagrama .svg-subtitulo,.plano-tecnico-diagrama .svg-nota,.plano-tecnico-diagrama .svg-legenda{font:15px Arial;fill:#52677f}.plano-tecnico-diagrama .svg-medida{font:bold 17px Arial;fill:#243b53}.plano-tecnico-diagrama .svg-label{font:bold 17px Arial;fill:#243b53}.plano-tecnico-diagrama .svg-label-small{font:bold 13px Arial;fill:#243b53}.plano-tecnico-diagrama .svg-fundo{fill:#eef3f7;stroke:#718096}.plano-tecnico-diagrama .svg-tampo{fill:#cfe4f7;stroke:#3f6f9f;stroke-width:2}.plano-tecnico-diagrama .svg-saia{fill:#dcebd8;stroke:#607d63;stroke-width:2}
.plano-tecnico-resumo{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:12px 0}.plano-tecnico-resumo div,.plano-tecnico-peca{border:1px solid #cbd9e6;border-radius:4px;padding:9px;background:#fff}.plano-tecnico-resumo b,.plano-tecnico-resumo span,.plano-tecnico-peca span{display:block}.plano-tecnico-resumo span,.plano-tecnico-peca span{margin-top:3px;color:#52677f}.plano-tecnico-pecas{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px}
@media print{.main-header,.main-sidebar,.main-footer,.corte-controles,.btn{display:none!important}.content-wrapper{margin:0!important}.card{break-inside:avoid}}
</style>
<section class="content-header"><div class="container-fluid"><h1>Cálculo de tecido — ficha #<?=$id?></h1></div></section>
<section class="content"><div class="container-fluid">
<div class="mb-3"><a class="btn btn-secondary" href="<?= BASE_URL ?>/views/producao/edit.php?id=<?=$id?>">Voltar à ficha</a> <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir plano de corte</button></div>
<div class="card"><div class="card-body"><strong><?=eCalc($item['componente_nome']?:$item['produto_nome'])?></strong>
<p>Material: <?=eCalc($material?:'não informado')?> · Cor: <?=eCalc($item['cor']??'')?> · Quantidade a produzir na ficha: <?=nCalc($item['quantidade_faltante'])?></p>
<p class="text-muted">A simulação começa pela quantidade a produzir da ficha. Alterar aqui permite planejar outro lote.</p>
<?php foreach(array_unique($avisos) as $aviso):?><p class="alert alert-warning"><?=eCalc($aviso)?></p><?php endforeach;?>
<form method="post" class="corte-controles">
<div class="form-row">
<label class="form-group col-md-4">Quantidade para simular<input required class="form-control" type="number" name="quantidade" min="1" max="10000" step="1" value="<?=eCalc($q)?>"></label>
<label class="form-group col-md-4">Largura total do tecido (cm)<input required class="form-control" type="number" name="largura_tecido" min="1" step="0.01" value="<?=eCalc($largura)?>"></label>
<label class="form-group col-md-4">Borda descartada de cada lado (cm)<input required class="form-control" type="number" name="borda" min="0" step="0.01" value="<?=eCalc($borda)?>"></label>
</div>
<p>Oxford: referência de 152 cm; bordas de 1 cm por lado deixam 150 cm úteis. As medidas de corte devem incluir a folga de costura. Nenhuma outra folga é somada automaticamente.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Peça de corte</th><th>Na largura do rolo (cm)</th><th>No comprimento do rolo (cm)</th><th>Peças por unidade</th><th></th></tr></thead><tbody id="pecas-corte">
<?php foreach($pecas?:[['nome'=>'','largura'=>'','comprimento'=>'','por_unidade'=>1]] as $i=>$p):?>
<tr><td><input required class="form-control" name="pecas[<?=$i?>][nome]" value="<?=eCalc($p['nome'])?>"><?php if(isset($p['sobra_de'])):?><input type="hidden" name="pecas[<?=$i?>][sobra_de]" value="<?=(int)$p['sobra_de']?>"><small>Retirada da sobra da peça anterior.</small><?php endif;?><?php if(isset($p['por_fileira_max'])):?><input type="hidden" name="pecas[<?=$i?>][por_fileira_max]" value="<?=(int)$p['por_fileira_max']?>"><?php endif;?></td>
<?php foreach(['largura','comprimento','por_unidade'] as $campo):?><td><input required class="form-control" type="number" step="<?=$campo==='por_unidade'?'1':'0.01'?>" min="<?=$campo==='por_unidade'?'1':'0.01'?>" name="pecas[<?=$i?>][<?=$campo?>]" value="<?=eCalc($p[$campo])?>"></td><?php endforeach;?>
<td><button type="button" class="btn btn-outline-danger" onclick="this.closest('tr').remove()">Remover</button></td></tr>
<?php endforeach;?></tbody></table></div>
<button type="button" id="nova-peca" class="btn btn-outline-secondary">Adicionar peça</button> <button class="btn btn-primary">Calcular encaixe</button> <button class="btn btn-outline-primary" formaction="?id=<?=$id?>&amp;impressao=1">Prévia A4</button>
</form></div></div>
<?php if($erro):?><div role="alert" class="alert alert-danger"><?=eCalc($erro)?></div><?php endif;?>
<?php if(!$resultado&&!$erro):?><div class="alert alert-info">Confira as instruções e preencha as peças de corte e a largura do tecido. Dimensões da estrutura não são usadas como molde.</div><?php endif;?>
<?php if($resultado):?>
<div class="card"><div class="card-body"><h2><?=nCalc($resultado['metros'])?> m lineares para <?=eCalc((int)$q)?> unidades</h2>
<p>Largura útil: <?=nCalc($resultado['largura_util'])?> cm. Corte em fileiras por tipo de peça, na orientação informada. Confira sentido do tecido, estampa e emendas antes de cortar.</p>
<?php require __DIR__.'/plano_corte_profissional.php'; ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Peça</th><th>Total</th><th>Por fileira</th><th>Fileiras</th><th>Comprimento consumido</th></tr></thead><tbody>
<?php foreach($resultado['linhas'] as $p):?><tr><td><?=eCalc($p['nome'])?> (<?=nCalc($p['largura'])?> × <?=nCalc($p['comprimento'])?> cm)</td><td><?=$p['total_pecas']?></td><td><?=$p['por_fileira']?></td><td><?=!empty($p['reuso'])?'Na sobra':$p['fileiras']?></td><td><?php if(!empty($p['reuso'])):?>Aproveitada na sobra da saia: 0 m adicionais<?php else:?><?=$p['fileiras']?> × <?=nCalc($p['comprimento'])?> cm = <?=nCalc($p['metros'])?> m<?php endif;?></td></tr><?php endforeach;?></tbody></table></div>

<p class="mt-3">Área das peças: <?=nCalc($resultado['area_pecas'])?> m². A metragem de compra considera as fileiras inteiras e suas sobras. Simular não altera o pedido, a ficha ou o estoque.</p>
</div></div><?php endif;?>
<details class="card"><summary class="card-header" style="cursor:pointer">Origem dos dados — abrir fichas técnicas completas</summary><div class="card-body" style="white-space:pre-wrap"><?=eCalc($origem)?></div></details>
</div></section>
<script>
document.getElementById('nova-peca').addEventListener('click',function(){
 const body=document.getElementById('pecas-corte'),i=Date.now(),row=document.createElement('tr');
 row.innerHTML='<td><input required class="form-control" name="pecas['+i+'][nome]"></td>'+['largura','comprimento','por_unidade'].map(c=>'<td><input required class="form-control" type="number" min="0.01" step="'+(c==='por_unidade'?'1':'0.01')+'" name="pecas['+i+']['+c+']" value="'+(c==='por_unidade'?'1':'')+'"></td>').join('')+'<td><button type="button" class="btn btn-outline-danger">Remover</button></td>';
 row.querySelector('button').onclick=()=>row.remove();body.appendChild(row);
});
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
