<?php
require_once __DIR__ . '/../../config/config.php'; require_once __DIR__ . '/../../config/database.php'; require_once __DIR__ . '/../../models/ProducaoPendente.php';
$conn=(new Database())->getConnection(); $producao=new ProducaoPendente($conn); $page_title='Acompanhar produção'; $page_group='producao'; $current_page=basename(__FILE__);
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT); if(!$id||!$producao->schemaInstalado()){header('Location:index.php');exit;}$item=$producao->obterPorId((int)$id);if(!$item){$_SESSION['error_message']='Pendência não encontrada.';header('Location:index.php');exit;}$erro=null;$ehServico=strtoupper((string)($item['componente_tipo']??''))==='SERVICO';
if (empty($_SESSION['producao_edicao_token'])) $_SESSION['producao_edicao_token']=bin2hex(random_bytes(24));
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        $usuario=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:null;
        if (($_POST['acao'] ?? '') === 'editar_dados') {
            if (!hash_equals($_SESSION['producao_edicao_token'], (string)($_POST['token'] ?? ''))) {
                throw new RuntimeException('Sessão do formulário inválida. Reabra a ficha.');
            }
            $producao->atualizarDados((int)$id,$_POST,$usuario);
        } else {
            $producao->alterarStatus((int)$id,(string)($_POST['status']??''),trim((string)($_POST['observacao']??''))?:null,$usuario);
        }
        $_SESSION['success_message']=$ehServico?'Ficha do serviço atualizada. Nenhum estoque foi movimentado.':'Ficha atualizada. A entrada da quantidade produzida no estoque continua manual.';
        header('Location:edit.php?id='.$id);exit;
    } catch(Throwable $e) { $erro=$e->getMessage(); }
}
$h=$conn->prepare('SELECT h.*,u.nome AS usuario_nome FROM producao_pendencias_historico h LEFT JOIN usuarios u ON u.id=h.usuario_id WHERE h.pendencia_id=:id ORDER BY h.id DESC');$h->execute([':id'=>$id]);$historico=$h->fetchAll(PDO::FETCH_ASSOC);require_once __DIR__.'/../../models/PlanoCorteTecido.php';$instrucoes=PlanoCorteTecido::instrucoes($item);require_once __DIR__.'/../includes/header.php';function eProdEdit($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<?php require __DIR__.'/ficha_impressao.php'; ?>
<?php if($ehServico):?><script>document.addEventListener('DOMContentLoaded',()=>{const a=[...document.querySelectorAll('.alert-warning')].find(e=>e.textContent.includes('marcar como produzida'));if(a){a.classList.replace('alert-warning','alert-info');a.innerHTML='<strong>Serviço de forração:</strong> marcar como produzido conclui a preparação deste pedido. Esta ficha não cria capa física e não movimenta estoque.';}});</script><?php endif;?>
<section class="content"><div class="container-fluid">
<form method="post" class="card card-primary mt-3">
<div class="card-header"><h3 class="card-title">Editar dados da ficha de produção #<?= (int)$id ?></h3></div>
<div class="card-body">
<input type="hidden" name="acao" value="editar_dados">
<input type="hidden" name="token" value="<?= eProdEdit($_SESSION['producao_edicao_token']) ?>">
<p class="text-muted">Revise a necessidade e o prazo. Pedido de origem: <?= (int)($item['pedido_id'] ?? 0) ?>. A quantidade produzida será conferida e registrada manualmente no estoque.</p>
<div class="form-row">
<div class="col-md-8 form-group"><label>Descrição do trabalho</label><input name="descricao_item" class="form-control" value="<?= eProdEdit($item['descricao_item']) ?>"></div>
<div class="col-md-4 form-group"><label>Cor</label><input name="cor" class="form-control" value="<?= eProdEdit($item['cor']) ?>"></div>
<div class="col-md-4 form-group"><label>Quantidade solicitada</label><input type="number" min="0" step="0.01" name="quantidade_solicitada" class="form-control" value="<?= eProdEdit($item['quantidade_solicitada']) ?>"></div>
<div class="col-md-4 form-group"><label>Quantidade a produzir</label><input type="number" min="0" step="0.01" name="quantidade_faltante" class="form-control" value="<?= eProdEdit($item['quantidade_faltante']) ?>"></div>
<div class="col-md-4 form-group"><label>Prazo de entrega</label><input type="date" name="data_entrega" class="form-control" value="<?= eProdEdit($item['data_entrega']) ?>"></div>
<div class="col-md-12 form-group"><label>Componente / capa física</label><select name="componente_id" class="form-control"><option value="">Ainda não cadastrado / não vinculado</option><?php foreach($producao->opcoesProdutos() as $opcao): ?><option value="<?= (int)$opcao['id'] ?>" <?= (int)$item['componente_id']===(int)$opcao['id']?'selected':'' ?>><?= eProdEdit($opcao['nome_produto']) ?></option><?php endforeach; ?></select></div>
<div class="col-md-12 form-group"><label>Instruções de produção</label><textarea name="observacoes" class="form-control" rows="6"><?= eProdEdit($instrucoes) ?></textarea></div>
</div></div><div class="card-footer text-right"><button type="submit" class="btn btn-primary">Salvar dados da ficha</button></div>
</form><div class="card"><div class="card-header"><h3 class="card-title">Instruções técnicas completas</h3></div><div class="card-body" style="white-space:pre-wrap"><?= eProdEdit($instrucoes) ?></div></div>
<style>@media print{.main-header,.main-sidebar,.main-footer,form,.btn{display:none!important}.content-wrapper{margin-left:0!important}}</style>
<div class="text-right mb-3"><a class="btn btn-outline-info" href="<?= BASE_URL ?>/views/producao/calculadora_tecido.php?id=<?= (int)$id ?>"><i class="fas fa-ruler-combined"></i> Cálculo de tecido e plano de corte</a> <button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Imprimir ficha</button></div></div></section>
<section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1>Pendência #<?=(int)$item['id']?></h1></div><div class="col-sm-6 text-right"><a href="<?= BASE_URL ?>/views/producao/index.php" class="btn btn-secondary">Voltar à lista de produção</a> <a href="<?= BASE_URL ?>/views/dashboard/index.php" class="btn btn-outline-secondary">Painel principal</a></div></div></div></section><section class="content"><div class="container-fluid"><?php if($erro):?><div class="alert alert-danger"><?=eProdEdit($erro)?></div><?php endif;?><div class="alert alert-warning"><strong>Importante:</strong> marcar como produzida organiza o acompanhamento, mas não soma estoque. Registre a quantidade conferida no produto ou componente físico para atualizar a disponibilidade.</div><div class="card"><div class="card-body"><dl class="row mb-0"><dt class="col-sm-3">Produto</dt><dd class="col-sm-9"><?=eProdEdit($item['produto_nome']?:$item['descricao_item'])?></dd><dt class="col-sm-3">Componente / cor</dt><dd class="col-sm-9"><?=eProdEdit($item['componente_nome']?:'—')?> / <?=eProdEdit($item['cor']?:'—')?></dd><dt class="col-sm-3">Quantidade faltante</dt><dd class="col-sm-9"><strong><?=eProdEdit($item['quantidade_faltante'])?></strong></dd><dt class="col-sm-3">Cliente e prazo</dt><dd class="col-sm-9"><?=eProdEdit($item['cliente_nome']?:'—')?> — entrega <?=eProdEdit($item['data_entrega']?:'não informada')?></dd><dt class="col-sm-3">Observações</dt><dd class="col-sm-9"><?=nl2br(eProdEdit($item['observacoes']?:'—'))?></dd></dl></div></div><form method="post" class="card card-primary"><div class="card-header"><h3 class="card-title">Atualizar acompanhamento</h3></div><div class="card-body"><div class="row"><div class="col-md-4 form-group"><label>Status</label><select name="status" class="form-control"><?php foreach(ProducaoPendente::STATUS_VALIDOS as $s):?><option value="<?=$s?>" <?=$item['status']===$s?'selected':''?>><?=$s?></option><?php endforeach;?></select></div><div class="col-md-8 form-group"><label>Observação da atualização</label><input name="observacao" class="form-control" placeholder="Ex.: cortes programados para terça-feira"></div></div></div><div class="card-footer text-right"><button class="btn btn-primary">Salvar status</button></div></form><div class="card"><div class="card-header"><h3 class="card-title">Histórico</h3></div><div class="card-body p-0"><table class="table mb-0"><thead><tr><th>Quando</th><th>Ação</th><th>Status</th><th>Observação</th></tr></thead><tbody><?php foreach($historico as $linha):?><tr><td><?=eProdEdit($linha['created_at'])?></td><td><?=eProdEdit($linha['acao'])?></td><td><?=eProdEdit(($linha['status_anterior']?:'—').' → '.($linha['status_novo']?:'—'))?></td><td><?=eProdEdit($linha['observacao']?:'—')?></td></tr><?php endforeach;?></tbody></table></div></div></div></section><?php require_once __DIR__.'/../includes/footer.php'; ?>
