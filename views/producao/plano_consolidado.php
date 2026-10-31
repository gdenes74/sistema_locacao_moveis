<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../config/database.php';
require_once __DIR__.'/../../models/ProducaoPendente.php';
require_once __DIR__.'/../../models/PlanoCorteTecido.php';
require_once __DIR__.'/../../models/ReferenciaCorteProducao.php';
if(!isLoggedIn()) redirect('views/usuarios/login.php');
$db=(new Database())->getConnection();$producao=new ProducaoPendente($db);
$ids=array_values(array_unique(array_filter(array_map('intval',(array)($_POST['ids']??$_GET['ids']??[])),fn($id)=>$id>0)));
if(!$ids){$_SESSION['error_message']='Selecione ao menos uma ficha para montar o plano de corte.';redirect('views/producao/index.php');}
if(count($ids)>100){http_response_code(422);exit('Selecione no máximo 100 fichas por lote.');}
$planos=[];$totais=[];
foreach($ids as $id){
    $item=$producao->obterPorId($id);if(!$item)continue;
    $quantidade=(float)$item['quantidade_faltante'];$fontes=ReferenciaCorteProducao::planos($db,$item,152);
    $pecas=[];$material=(string)($item['componente_material']??'');$textos=[];
    foreach($fontes as $fonte){
        if(!$material&&!empty($fonte['material']))$material=$fonte['material'];
        $textos[]=trim($fonte['nome']."\n".$fonte['texto']);$offset=count($pecas);
        foreach($fonte['pecas'] as $p){$p['nome']=$fonte['nome'].' — '.$p['nome'];$p['por_unidade']*=$fonte['quantidade'];if(isset($p['sobra_de']))$p['sobra_de']+=$offset;$pecas[]=$p;}
    }
    $largura=stripos($material,'Oxford')!==false?152:152;$borda=1.0;$resultado=null;$erro=null;
    if($pecas&&max(array_column($pecas,'largura'))>$largura-2*$borda&&max(array_column($pecas,'largura'))<=$largura)$borda=0;
    try{if($quantidade!=(int)$quantidade)throw new InvalidArgumentException('Quantidade não inteira.');if(!$pecas)throw new InvalidArgumentException('Plano de corte ainda não cadastrado.');$resultado=PlanoCorteTecido::calcular((int)$quantidade,$pecas,$largura,$borda);}catch(InvalidArgumentException $e){$erro=$e->getMessage();}
    $chave=trim($material?:'Material não informado').'|'.trim((string)($item['cor']?:'Cor não informada'));
    if($resultado){$totais[$chave]=($totais[$chave]??0)+(float)$resultado['metros'];}
    $planos[]=compact('id','item','quantidade','material','largura','borda','resultado','erro','textos');
}
function eCon($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function nCon($v):string{return number_format((float)$v,2,',','.');}
$page_title='Plano de corte consolidado';$page_group='producao';require __DIR__.'/../includes/header.php';
?>
<style>@media print{.main-header,.main-sidebar,.main-footer,.nao-imprimir{display:none!important}.content-wrapper{margin:0!important}.card{break-inside:avoid}}</style>
<section class="content-header"><div class="container-fluid"><div class="row"><div class="col"><h1>Plano de corte consolidado</h1><p class="text-muted">Fichas selecionadas do período. Os cálculos continuam separados por molde; os totais são agrupados por material e cor.</p></div><div class="col-auto nao-imprimir"><a class="btn btn-secondary" href="index.php">Voltar à produção</a> <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir</button></div></div></div></section>
<section class="content"><div class="container-fluid">
<div class="card"><div class="card-header"><h2 class="card-title">Resumo para compra</h2></div><div class="card-body"><table class="table table-sm"><thead><tr><th>Material / cor</th><th>Metros calculados</th></tr></thead><tbody><?php foreach($totais as $chave=>$metros):?><tr><td><?=eCon(str_replace('|',' — ',$chave))?></td><td><strong><?=nCon($metros)?> m</strong></td></tr><?php endforeach;?><?php if(!$totais):?><tr><td colspan="2" class="text-muted">Nenhuma ficha selecionada possui medidas completas para calcular.</td></tr><?php endif;?></tbody></table><p class="mb-0 text-muted">O total soma as fileiras inteiras de cada molde. Retalhos entre produtos diferentes ainda não são compensados automaticamente.</p></div></div>
<?php foreach($planos as $plano):$item=$plano['item'];?><div class="card"><div class="card-header"><h2 class="card-title">Ficha #<?=$plano['id']?> — <?=eCon($item['produto_nome']?:$item['descricao_item'])?></h2></div><div class="card-body"><p><strong><?=eCon($item['componente_nome']?:'Serviço/peça não informado')?></strong> · Cor: <?=eCon($item['cor']?:'—')?> · Quantidade: <?=nCon($plano['quantidade'])?> · Prazo: <?=eCon($item['data_entrega']?:$item['data_evento']?:'—')?></p><?php if($plano['erro']):?><div class="alert alert-warning"><?=eCon($plano['erro'])?> Revise a descrição técnica do serviço/produto.</div><?php else:?><h3><?=nCon($plano['resultado']['metros'])?> m lineares</h3><table class="table table-sm"><thead><tr><th>Peça</th><th>Total</th><th>Fileiras</th><th>Consumo</th></tr></thead><tbody><?php foreach($plano['resultado']['linhas'] as $linha):?><tr><td><?=eCon($linha['nome'])?> (<?=nCon($linha['largura'])?> × <?=nCon($linha['comprimento'])?> cm)</td><td><?=$linha['total_pecas']?></td><td><?=!empty($linha['reuso'])?'Na sobra':$linha['fileiras']?></td><td><?=!empty($linha['reuso'])?'0 m adicionais':nCon($linha['metros']).' m'?></td></tr><?php endforeach;?></tbody></table><?php endif;?><details><summary>Ficha técnica usada no cálculo</summary><pre class="mt-2" style="white-space:pre-wrap"><?=eCon(implode("\n\n",array_filter($plano['textos'])))?></pre></details></div></div><?php endforeach;?>
</div></section><?php require __DIR__.'/../includes/footer.php';?>
