<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../../config/Database.php';
require_once __DIR__.'/../../models/MotorCores.php';
if(!isLoggedIn())redirect('views/usuarios/login.php');
if(!hasAccess('admin')){http_response_code(403);exit('Acesso restrito à administração.');}
$db=(new Database())->getConnection();$motor=new MotorCores($db);
if(empty($_SESSION['csrf_motor_cores']))$_SESSION['csrf_motor_cores']=bin2hex(random_bytes(32));
$erro='';$form=[];$familias=$motor->listarFamilias();
foreach($familias as $f)if((int)$f['id']===(int)($_GET['editar']??0))$form=$f+['familia_id'=>$f['id']];
if(!$form && !empty($_GET['produto']))foreach($familias as $f)if((int)$f['produto_generico_id']===(int)$_GET['produto']){$form=$f+['familia_id'=>$f['id']];break;}
if(!$form)$form=['familia_id'=>0,'nome'=>'','produto_generico_id'=>(int)($_GET['produto']??0),'modo_impacto_estoque'=>'FORRACAO_PRODUCAO','politica_preco'=>'MESMO_PRECO','permite_cor_livre'=>1,'exige_revisao'=>1,'observacoes'=>''];
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!hash_equals($_SESSION['csrf_motor_cores'],(string)($_POST['csrf']??'')))throw new DomainException('Sessão do formulário expirada. Reabra a página.');
        $acao=$_POST['acao']??'';$usuario=(int)$_SESSION['user_id'];
        if($acao==='salvar'){
            if(empty($_POST['familia_id']) && !empty($_POST['produto_generico_id'])){
                foreach($familias as $existente)if((int)$existente['produto_generico_id']===(int)$_POST['produto_generico_id']){
                    $_SESSION['success_message']='Este produto já estava configurado. Você está editando a configuração existente.';
                    redirect('views/produtos/configurar_motor.php?editar='.(int)$existente['id']);
                }
            }
            $id=$motor->configurarProdutoNoMotor($_POST,$usuario);
            $_SESSION['success_message']='Configuração salva. Agora você pode criar o mestre e revisar as amostras pela tela.';
            redirect('views/produtos/configurar_motor.php?editar='.$id);
        }elseif($acao==='desativar'){$motor->desativarFamilia((int)$_POST['familia_id'],$usuario);}
        elseif($acao==='reativar'){$motor->reativarFamilia((int)$_POST['familia_id'],$usuario);}
        else throw new DomainException('Ação inválida.');
        $_SESSION['success_message']='Configuração atualizada. O histórico foi preservado.';redirect('views/produtos/configurar_motor.php');
    }catch(Throwable $e){$erro=$e->getMessage();if(($_POST['acao']??'')==='salvar')$form=$_POST;}
}
$catalogo=array_values(array_filter($motor->listarCatalogoProdutos(),fn($p)=>strtoupper((string)$p['tipo_produto'])==='COMPOSTO'));
$page_title='Configurar produtos no motor de cores';require __DIR__.'/../includes/header.php';
function hConfig($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
$modos=['FORRACAO_PRODUCAO'=>'Forração sob demanda (sem capa em estoque)','COMPONENTE_COR'=>'Capas físicas por cor','ESTRUTURA_COMPARTILHADA'=>'Estrutura compartilhada','SEM_IMPACTO_FISICO'=>'Somente apresentação visual'];
?>
<section class="content-header"><div class="container-fluid"><h1>Configurar produtos no motor de cores</h1></div></section>
<section class="content"><div class="container-fluid">
<p><a class="btn btn-secondary" href="cores_imagens.php">Voltar à montagem por cor</a> <a class="btn btn-outline-primary" href="configurar_motor.php">Nova configuração</a></p>
<?php if($erro):?><div class="alert alert-danger" role="alert"><?=hConfig($erro)?></div><?php endif;?>
<?php if(!empty($_SESSION['success_message'])):?><div class="alert alert-success"><?=hConfig($_SESSION['success_message'])?></div><?php unset($_SESSION['success_message']);endif;?>
<div class="card"><div class="card-header"><h2 class="card-title"><?=(int)$form['familia_id']?'Editar configuração #'.(int)$form['familia_id']:'Incluir novo produto no motor'?></h2></div><div class="card-body">
<p>1. Escolha o produto montado. 2. Defina a regra da família. 3. Salve e crie o mestre. Depois confira e aprove a foto-base e as amostras nesta mesma área.</p>
<form method="post" id="configurar-produto-motor">
<input type="hidden" name="csrf" value="<?=hConfig($_SESSION['csrf_motor_cores'])?>"><input type="hidden" name="acao" value="salvar"><input type="hidden" name="familia_id" value="<?=(int)$form['familia_id']?>">
<div class="form-row"><label class="form-group col-md-4">Filtrar a lista de produtos<input class="form-control" id="filtro-produto-motor" placeholder="Ex.: pufe redondo, mesa"><small class="form-text text-muted">A lista ao lado é filtrada enquanto você digita. Depois selecione o produto nela.</small></label>
<label class="form-group col-md-8">Produto-base “Cor a definir”<select class="form-control" name="produto_generico_id" id="config-produto" required><option value="">Selecione um produto composto</option><?php foreach($catalogo as $p):?><option value="<?=(int)$p['produto_id']?>" <?=(int)$form['produto_generico_id']===(int)$p['produto_id']?'selected':''?>>#<?=(int)$p['produto_id']?> — <?=hConfig($p['nome_produto'])?> · <?=hConfig($p['categoria_nome'])?> / <?=hConfig($p['subcategoria_nome'])?></option><?php endforeach;?></select></label></div>
<p id="composicao-config" class="text-muted"></p>
<div class="form-row"><label class="form-group col-md-6">Nome da família / formato<input class="form-control" name="nome" maxlength="150" required value="<?=hConfig($form['nome'])?>" placeholder="Ex.: Pufe redondo 1,20 m"></label>
<label class="form-group col-md-6">Regra do acabamento<select class="form-control" name="modo_impacto_estoque"><?php foreach($modos as $valor=>$rotulo):?><option value="<?=$valor?>" <?=$form['modo_impacto_estoque']===$valor?'selected':''?>><?=hConfig($rotulo)?></option><?php endforeach;?></select></label></div>
<label class="form-group">Como usar o preço<select class="form-control" name="politica_preco"><option value="MESMO_PRECO" <?=$form['politica_preco']==='MESMO_PRECO'?'selected':''?>>Manter o preço do produto-base (recomendado para forração)</option><option value="PRECO_DA_VARIANTE" <?=$form['politica_preco']==='PRECO_DA_VARIANTE'?'selected':''?>>Usar o preço cadastrado em cada produto/variante</option></select><small class="form-text text-muted">Forração: mantenha o preço do produto-base. A segunda opção só é usada quando cada cor é um produto comercial com preço próprio.</small></label>
<div class="form-group"><label><input type="checkbox" name="permite_cor_livre" value="1" <?=!empty($form['permite_cor_livre'])?'checked':''?>> Permitir informar uma cor nova na observação</label><br><label><input type="checkbox" name="exige_revisao" value="1" <?=!empty($form['exige_revisao'])?'checked':''?>> Exigir revisão humana das imagens</label></div>
<label class="form-group d-block">Observações da configuração<textarea class="form-control" name="observacoes"><?=hConfig($form['observacoes']??'')?></textarea></label>
<button class="btn btn-primary">Salvar configuração</button>
<?php if((int)$form['familia_id']):?><a class="btn btn-success" href="novo_mestre.php?produto=<?=(int)$form['produto_generico_id']?>&amp;familia=<?=(int)$form['familia_id']?>">Criar mestre deste produto</a><a class="btn btn-outline-primary" href="cores_imagens.php#amostras">Amostras e cores</a><?php endif;?>
</form></div></div>
<div class="card"><div class="card-header"><h2 class="card-title">Configurações cadastradas</h2></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>Família</th><th>Produto-base</th><th>Regra</th><th>Mestres</th><th>Ações</th></tr></thead><tbody>
<?php foreach($familias as $f):?><tr><td><?=hConfig($f['nome'])?><?=empty($f['ativo'])?' (inativa)':''?></td><td><?=hConfig($f['produto_generico']?:'Ainda sem produto-base')?></td><td><?=hConfig($modos[$f['modo_impacto_estoque']]??$f['modo_impacto_estoque'])?></td><td><?=(int)$f['total_modelos']?></td><td><a class="btn btn-sm btn-outline-primary" href="?editar=<?=(int)$f['id']?>">Editar</a> <form method="post" class="d-inline" onsubmit="return confirm('Confirmar atualização da configuração? Produtos, fotos e histórico serão preservados.');"><input type="hidden" name="csrf" value="<?=hConfig($_SESSION['csrf_motor_cores'])?>"><input type="hidden" name="familia_id" value="<?=(int)$f['id']?>"><input type="hidden" name="acao" value="<?=empty($f['ativo'])?'reativar':'desativar'?>"><button class="btn btn-sm btn-outline-secondary"><?=empty($f['ativo'])?'Reativar':'Desativar'?></button></form></td></tr><?php endforeach;?></tbody></table></div></div>
</div></section>
<script>
(()=>{const select=document.getElementById('config-produto'),search=document.getElementById('filtro-produto-motor'),options=[...select.options].map(o=>({value:o.value,text:o.text})),catalogo=<?=json_encode($catalogo,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
const norm=s=>s.toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g,'');
search.addEventListener('input',()=>{const value=select.value,q=norm(search.value);select.replaceChildren(...options.filter(o=>!o.value||o.value===value||norm(o.text).includes(q)).map(o=>new Option(o.text,o.value,false,o.value===value)));});
select.addEventListener('change',()=>{const p=catalogo.find(p=>String(p.produto_id)===select.value);document.getElementById('composicao-config').textContent=p?'Composição atual: '+(p.composicao_resumo||'Não cadastrada. Edite o produto para informar componentes e serviços.') : '';const nome=document.querySelector('[name="nome"]');if(p&&!nome.value)nome.value=p.nome_produto.replace(/\s+Cor\s+A\s+Definir$/i,'');});select.dispatchEvent(new Event('change'));
})();
</script>
<?php require __DIR__.'/../includes/footer.php'; ?>
