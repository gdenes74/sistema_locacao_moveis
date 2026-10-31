<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/ProducaoPendente.php';
$conn = (new Database())->getConnection();
$producao = new ProducaoPendente($conn);
$page_title = 'Produção Pendente'; $page_group = 'producao'; $current_page = basename(__FILE__);
$schemaInstalado = $producao->schemaInstalado();
$filtros = ['status' => $_GET['status'] ?? '', 'busca' => $_GET['busca'] ?? '',
    'data_de'=>$_GET['data_de']??'', 'data_ate'=>$_GET['data_ate']??''];
$pendencias = $schemaInstalado ? $producao->listar($filtros) : [];
$mensagem = $_SESSION['success_message'] ?? null; $erro = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']);
require_once __DIR__ . '/../includes/header.php';
function eProducao($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function badgeProducao(string $s): string { return ['PENDENTE'=>'danger','PROGRAMADA'=>'warning','PRODUZIDA'=>'success','CANCELADA'=>'secondary'][$s] ?? 'secondary'; }
?>
<section class="content-header"><div class="container-fluid"><div class="row mb-2"><div class="col-sm-6"><h1>Produção pendente</h1></div><div class="col-sm-6 text-right"><a href="create.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nova pendência</a></div></div></div></section>
<section class="content"><div class="container-fluid">
<?php if (!$schemaInstalado): ?>
<div class="alert alert-warning"><strong>Área pronta, aguardando instalação.</strong> Execute uma vez <code>C:\xampp\php\php.exe tests\instalar_producao_pendente.php</code>. A instalação só cria duas tabelas novas; não altera estoque, pedidos, orçamentos ou produtos.</div>
<?php else: ?>
<?php if ($mensagem): ?><div class="alert alert-success"><?= eProducao($mensagem) ?></div><?php endif; ?><?php if ($erro): ?><div class="alert alert-danger"><?= eProducao($erro) ?></div><?php endif; ?>
<div class="alert alert-info"><i class="fas fa-info-circle"></i> Esta lista organiza o que precisa ser produzido ou regularizado. Alterar o status aqui <strong>não movimenta estoque</strong>; a quantidade real produzida deve ser conferida e registrada no cadastro do produto/componente.</div>
<div class="card"><div class="card-body"><form method="get" class="form-row"><div class="col-md-2 mb-2"><select class="form-control" name="status"><option value="">Todos os status</option><?php foreach (ProducaoPendente::STATUS_VALIDOS as $status): ?><option value="<?= $status ?>" <?= $filtros['status']===$status?'selected':'' ?>><?= $status ?></option><?php endforeach; ?></select></div><div class="col-md-3 mb-2"><input class="form-control" name="busca" value="<?= eProducao($filtros['busca']) ?>" placeholder="Cliente, produto, cor ou descrição"></div><label class="col-md-2 mb-2"><span class="sr-only">Prazo inicial</span><input type="date" class="form-control" name="data_de" value="<?=eProducao($filtros['data_de'])?>" title="Prazo inicial"></label><label class="col-md-2 mb-2"><span class="sr-only">Prazo final</span><input type="date" class="form-control" name="data_ate" value="<?=eProducao($filtros['data_ate'])?>" title="Prazo final"></label><div class="col-md-2 mb-2"><button class="btn btn-outline-primary btn-block"><i class="fas fa-search"></i> Filtrar período</button></div></form></div></div>
<form method="post" action="plano_consolidado.php" class="card">
<div class="card-header d-flex align-items-center"><strong>Fichas do período</strong><button class="btn btn-sm btn-outline-primary ml-auto"><i class="fas fa-layer-group"></i> Plano de corte das selecionadas</button></div>
<div class="card-body table-responsive p-0"><table class="table table-hover mb-0"><thead><tr><th><input type="checkbox" id="selecionar-todas" aria-label="Selecionar todas"></th><th>Status</th><th>Prazo</th><th>Produto / componente</th><th>Cor</th><th>Quantidade</th><th>Cliente / origem</th><th>Ação</th></tr></thead><tbody><?php if (!$pendencias): ?><tr><td colspan="8" class="text-center text-muted py-4">Nenhuma pendência encontrada.</td></tr><?php endif; ?><?php foreach ($pendencias as $item): ?><tr><td><input type="checkbox" name="ids[]" value="<?= (int)$item['id'] ?>" aria-label="Selecionar ficha <?= (int)$item['id'] ?>"></td><td><span class="badge badge-<?= badgeProducao($item['status']) ?>"><?= eProducao($item['status']) ?></span></td><td><?= eProducao($item['data_entrega'] ?: $item['data_evento'] ?: 'Sem data') ?></td><td><strong><?= eProducao($item['produto_nome'] ?: $item['descricao_item'] ?: 'Item não informado') ?></strong><?php if ($item['componente_nome']): ?><br><small><?= strtoupper((string)($item['componente_tipo']??''))==='SERVICO'?'Serviço':'Componente' ?>: <?= eProducao($item['componente_nome']) ?></small><?php endif; ?></td><td><?= eProducao($item['cor'] ?: '—') ?></td><td><strong><?= eProducao(rtrim(rtrim(number_format((float)$item['quantidade_faltante'],2,',','.'),'0'),',')) ?></strong></td><td><?= eProducao($item['cliente_nome'] ?: '—') ?><br><small><?= eProducao($item['origem_tipo']) ?><?= $item['origem_id'] ? ' #' . (int)$item['origem_id'] : '' ?></small></td><td><a href="edit.php?id=<?= (int)$item['id'] ?>" class="btn btn-sm btn-outline-primary">Acompanhar</a></td></tr><?php endforeach; ?></tbody></table></div></form>
<script>document.getElementById('selecionar-todas')?.addEventListener('change',function(){document.querySelectorAll('input[name="ids[]"]').forEach(c=>c.checked=this.checked);});</script>
<?php endif; ?></div></section><?php require_once __DIR__ . '/../includes/footer.php'; ?>
